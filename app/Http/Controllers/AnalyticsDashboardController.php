<?php

namespace App\Http\Controllers;

use App\Models\AcademicProfile;
use App\Models\ExtracurricularAchievement;
use App\Models\HealthPassport;
use App\Models\PortfolioItem;
use App\Models\PsychologicalProfile;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentRiskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsDashboardController extends Controller
{
    public function __construct(private readonly StudentRiskService $riskService)
    {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canViewAnalyticsDashboard(), 403);

        $studentCount = $this->studentCount();
        $averageGpa = AcademicProfile::query()
            ->whereIn('user_id', $this->activeStudentUserIds())
            ->whereNotNull('gpa')
            ->avg('gpa');
        $engagedStudentCount = $this->engagedStudentCount();

        return Inertia::render('AnalyticsDashboard/Index', [
            'metrics' => [
                'studentCount' => $studentCount,
                'averageGpa' => $averageGpa !== null ? round((float) $averageGpa, 2) : null,
                'riskGroupCount' => $this->riskGroupCount(),
                'engagementLevel' => $studentCount > 0
                    ? round(($engagedStudentCount / $studentCount) * 100)
                    : 0,
            ],
            'riskGroups' => $this->riskGroups(),
            'topStudents' => $this->topStudents(),
            'developmentDynamics' => $this->developmentDynamics(),
            'recommendations' => $this->recommendations(),
            'reports' => $this->reports(),
            'testScoresUrl' => $request->user()->canViewPsychologicalProfile()
                ? route('reports.test-scores.index')
                : null,
        ]);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless($request->user()?->canViewAnalyticsDashboard(), 403);

        $report = $this->reportData($type);
        abort_if($report === null, 404);

        return response()->streamDownload(function () use ($report): void {
            $writer = new XlsxWriter();
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($report['columns']));
            $gpaColumn = array_search('GPA', $report['columns'], true);
            if ($gpaColumn === false) {
                $gpaColumn = array_search('Средний GPA', $report['columns'], true);
            }
            $gpaStyle = (new Style())->setFormat('0.00');

            foreach ($report['rows'] as $values) {
                $writer->addRow(new Row(array_map(
                    static function ($value, int $column) use ($gpaColumn, $gpaStyle): Cell {
                        if ($column === $gpaColumn && $value !== null && $value !== '') {
                            return new NumericCell((float) $value, $gpaStyle);
                        }

                        return is_string($value) && $value !== ''
                            ? new StringCell($value, null)
                            : Cell::fromValue($value);
                    },
                    $values,
                    array_keys($values),
                )));
            }

            $writer->close();
        }, sprintf('%s-report-%s.xlsx', $type, now()->format('Y-m-d')), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function studentCount(): int
    {
        $count = User::query()
            ->whereHas('role', fn ($query) => $query->whereIn('slug', User::STUDENT_DATA_ROLES))
            ->where(function ($query): void {
                $query
                    ->doesntHave('studentProfile')
                    ->orWhereHas('studentProfile', fn ($query) => $query->active());
            })
            ->count();

        return $count > 0 ? $count : $this->activeStudentProfiles()->count();
    }

    private function engagedStudentCount(): int
    {
        $activeStudentUserIds = $this->activeStudentUserIds();

        return ExtracurricularAchievement::query()
            ->whereIn('user_id', $activeStudentUserIds)
            ->distinct()
            ->pluck('user_id')
            ->merge(
                PortfolioItem::query()
                    ->whereIn('user_id', $activeStudentUserIds)
                    ->distinct()
                    ->pluck('user_id')
            )
            ->unique()
            ->count();
    }

    private function riskGroupCount(): int
    {
        return $this->academicRiskUserIds()
            ->merge($this->socialRiskUserIds())
            ->merge($this->psychologicalRiskUserIds())
            ->merge($this->medicalRiskUserIds())
            ->unique()
            ->count();
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function riskGroups(): array
    {
        return [
            [
                'label' => 'Академические риски',
                'count' => $this->academicRiskUserIds()->count(),
            ],
            [
                'label' => 'Социальные риски',
                'count' => $this->socialRiskUserIds()->count(),
            ],
            [
                'label' => 'Психологические риски',
                'count' => $this->psychologicalRiskUserIds()->count(),
            ],
            [
                'label' => 'Медицинские риски',
                'count' => $this->medicalRiskUserIds()->count(),
            ],
        ];
    }

    /**
     * @return Collection<int, int>
     */
    private function academicRiskUserIds(): Collection
    {
        return AcademicProfile::query()
            ->whereIn('user_id', $this->activeStudentUserIds())
            ->get(['user_id', 'gpa', 'academic_debt'])
            ->filter(fn (AcademicProfile $profile): bool => $this->riskService->academicRiskReasons($profile) !== [])
            ->pluck('user_id')
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function socialRiskUserIds(): Collection
    {
        return $this->activeStudentProfiles()
            ->get([
                'user_id',
                'disability_group',
                'disabled_parent_group',
                'disabled_sibling_group',
                'is_orphan',
                'is_half_orphan',
                'is_incomplete_family',
                'is_large_family',
                'is_low_income',
                'benefits',
                'social_support_need_status',
            ])
            ->filter(fn (StudentProfile $profile): bool => $this->riskService->hasSocialRisk($profile))
            ->pluck('user_id')
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function psychologicalRiskUserIds(): Collection
    {
        $activeStudentUserIds = $this->activeStudentUserIds();

        return PsychologicalProfile::query()
            ->whereIn('user_id', $activeStudentUserIds)
            ->get(['user_id', 'testing_results', 'individual_features'])
            ->filter(fn (PsychologicalProfile $profile): bool => $this->riskService->hasMeaningfulText($profile->testing_results)
                || $this->riskService->hasMeaningfulText($profile->individual_features))
            ->pluck('user_id')
            ->merge(
                HealthPassport::query()
                    ->whereIn('user_id', $activeStudentUserIds)
                    ->get(['user_id', 'psychological_diagnosis'])
                    ->filter(fn (HealthPassport $passport): bool => $this->riskService->hasMeaningfulText($passport->psychological_diagnosis))
                    ->pluck('user_id'),
            )
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function medicalRiskUserIds(): Collection
    {
        return HealthPassport::query()
            ->whereIn('user_id', $this->activeStudentUserIds())
            ->get([
                'user_id',
                'dispensary_accounting',
                'diagnosis',
                'disability_group',
                'pregnancy',
            ])
            ->filter(fn (HealthPassport $passport): bool => $this->riskService->medicalRiskReasons($passport) !== [])
            ->pluck('user_id')
            ->unique()
            ->values();
    }

    /**
     * @return array<int, array{name: string, group: string, gpa: float|null, achievements: int}>
     */
    private function topStudents(): array
    {
        return AcademicProfile::query()
            ->with(['user.studentProfile.studentGroup', 'user.extracurricularAchievements'])
            ->whereIn('user_id', $this->activeStudentUserIds())
            ->whereNotNull('gpa')
            ->orderByDesc('gpa')
            ->limit(5)
            ->get()
            ->map(fn (AcademicProfile $profile): array => [
                'name' => $profile->user?->studentProfile?->full_name
                    ?: $profile->user?->name
                    ?: 'Без имени',
                'group' => $profile->user?->studentProfile?->currentGroupName() ?: 'Не указано',
                'gpa' => $profile->gpa !== null ? (float) $profile->gpa : null,
                'achievements' => $profile->user?->extracurricularAchievements?->count() ?? 0,
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: int|float|null}>
     */
    private function developmentDynamics(): array
    {
        return [
            [
                'label' => 'Анкеты студентов',
                'value' => $this->activeStudentProfiles()->count(),
            ],
            [
                'label' => 'Академические профили',
                'value' => AcademicProfile::query()
                    ->whereIn('user_id', $this->activeStudentUserIds())
                    ->count(),
            ],
            [
                'label' => 'Внеучебные достижения',
                'value' => ExtracurricularAchievement::query()
                    ->whereIn('user_id', $this->activeStudentUserIds())
                    ->count(),
            ],
            [
                'label' => 'Файлы портфолио',
                'value' => PortfolioItem::query()
                    ->whereIn('user_id', $this->activeStudentUserIds())
                    ->count(),
            ],
        ];
    }

    /**
     * @return array<int, array{type: string, title: string, basis: string, target: int, action: string}>
     */
    private function recommendations(): array
    {
        $activeStudentUserIds = $this->activeStudentUserIds();

        $lowGpaCount = AcademicProfile::query()
            ->whereIn('user_id', $activeStudentUserIds)
            ->whereNotNull('gpa')
            ->where('gpa', '<', 2.5)
            ->count();

        $academicDebtCount = AcademicProfile::query()
            ->whereIn('user_id', $activeStudentUserIds)
            ->whereNotNull('academic_debt')
            ->whereNotIn('academic_debt', ['', 'Нет', 'нет', 'НЕТ'])
            ->count();

        $inactiveStudentCount = max(
            $this->studentCount() - $this->engagedStudentCount(),
            0,
        );

        $highGpaCount = AcademicProfile::query()
            ->whereIn('user_id', $activeStudentUserIds)
            ->whereNotNull('gpa')
            ->where('gpa', '>=', 3.5)
            ->count();

        $socialRiskCount = $this->activeStudentProfiles()
            ->where(function ($query) {
                $query
                    ->where('is_orphan', true)
                    ->orWhere('is_half_orphan', true)
                    ->orWhere('is_incomplete_family', true)
                    ->orWhere('is_low_income', true);
            })
            ->count();

        return [
            [
                'type' => 'Дополнительные курсы',
                'title' => 'Поддержка академической успеваемости',
                'basis' => 'На основе GPA и академической задолженности',
                'target' => max($lowGpaCount, $academicDebtCount),
                'action' => 'Назначить дополнительные курсы и консультации по проблемным дисциплинам.',
            ],
            [
                'type' => 'Мероприятия',
                'title' => 'Повышение вовлеченности',
                'basis' => 'На основе активности во внеучебной деятельности и портфолио',
                'target' => $inactiveStudentCount,
                'action' => 'Пригласить студентов к клубам, волонтерским проектам и факультетским мероприятиям.',
            ],
            [
                'type' => 'Конкурсы',
                'title' => 'Развитие сильных студентов',
                'basis' => 'На основе успеваемости, компетенций и достижений',
                'target' => $highGpaCount,
                'action' => 'Рекомендовать олимпиады, конкурсы, научные публикации и проектные команды.',
            ],
            [
                'type' => 'Консультации психолога',
                'title' => 'Индивидуальное сопровождение',
                'basis' => 'На основе социальных факторов и групп риска',
                'target' => $socialRiskCount,
                'action' => 'Передать список психологу для мягкого сопровождения и первичной консультации.',
            ],
            [
                'type' => 'Наставничество',
                'title' => 'Peer-to-peer поддержка',
                'basis' => 'На основе успеваемости, интересов и активности',
                'target' => min($highGpaCount, $inactiveStudentCount),
                'action' => 'Связать активных студентов с теми, кому нужна академическая или адаптационная поддержка.',
            ],
        ];
    }

    /**
     * @return array<int, array{type: string, title: string, description: string, exportUrl: string}>
     */
    private function reports(): array
    {
        return collect($this->reportDefinitions())
            ->map(fn (array $report, string $type): array => [
                'type' => $type,
                'title' => $report['title'],
                'description' => $report['description'],
                'exportUrl' => route('analytics-dashboard.reports.export', ['type' => $type]),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{title: string, description: string}>
     */
    private function reportDefinitions(): array
    {
        return [
            'student' => [
                'title' => 'Отчет по студенту',
                'description' => 'Свод по анкетам, GPA, группам, достижениям и портфолио.',
            ],
            'group' => [
                'title' => 'Отчет по группе',
                'description' => 'Количество студентов, средний GPA и академические риски по группам.',
            ],
            'course' => [
                'title' => 'Отчет по курсу',
                'description' => 'Сводные показатели по курсам обучения.',
            ],
            'faculty' => [
                'title' => 'Отчет по факультету',
                'description' => 'Сводные показатели по факультетам.',
            ],
            'academic-risks' => [
                'title' => 'Отчет по академическим рискам',
                'description' => 'Студенты с низким GPA или академической задолженностью.',
            ],
            'social-risks' => [
                'title' => 'Отчет по социальным рискам',
                'description' => 'Студенты с социальными факторами риска и потребностью в поддержке.',
            ],
            'psychological-risks' => [
                'title' => 'Отчет по психологическим рискам',
                'description' => 'Данные из психологического профиля и паспорта здоровья.',
            ],
            'medical-risks' => [
                'title' => 'Отчет по медицинским рискам',
                'description' => 'Студенты с медицинскими факторами риска из паспорта здоровья.',
            ],
        ];
    }

    /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}|null
     */
    private function reportData(string $type): ?array
    {
        return match ($type) {
            'student' => [
                'title' => 'Отчет по студенту',
                'columns' => ['ФИО', 'Email', 'Факультет', 'Группа', 'Курс', 'GPA', 'Академическая задолженность', 'Достижения', 'Портфолио'],
                'rows' => $this->studentReportRows(),
            ],
            'group' => [
                'title' => 'Отчет по группе',
                'columns' => ['Группа', 'Факультет', 'Количество студентов', 'Средний GPA', 'Студенты с задолженностью'],
                'rows' => $this->groupedReportRows('student_group_id'),
            ],
            'course' => [
                'title' => 'Отчет по курсу',
                'columns' => ['Курс', 'Количество студентов', 'Средний GPA', 'Студенты с задолженностью'],
                'rows' => $this->groupedReportRows('course'),
            ],
            'faculty' => [
                'title' => 'Отчет по факультету',
                'columns' => ['Факультет', 'Количество студентов', 'Средний GPA', 'Студенты с задолженностью'],
                'rows' => $this->groupedReportRows('faculty'),
            ],
            'academic-risks' => [
                'title' => 'Отчет по академическим рискам',
                'columns' => ['ФИО', 'Email', 'Факультет', 'Группа', 'Курс', 'GPA', 'Академическая задолженность', 'Факторы риска'],
                'rows' => $this->academicRiskReportRows(),
            ],
            'social-risks' => [
                'title' => 'Отчет по социальным рискам',
                'columns' => ['ФИО', 'Email', 'Факультет', 'Группа', 'Курс', 'Социальные факторы'],
                'rows' => $this->socialRiskReportRows(),
            ],
            'psychological-risks' => [
                'title' => 'Отчет по психологическим рискам',
                'columns' => ['ФИО', 'Email', 'Факультет', 'Группа', 'Психологические факторы'],
                'rows' => $this->psychologicalRiskReportRows(),
            ],
            'medical-risks' => [
                'title' => 'Отчет по медицинским рискам',
                'columns' => ['ФИО', 'Email', 'Факультет', 'Группа', 'Медицинские факторы'],
                'rows' => $this->medicalRiskReportRows(),
            ],
            default => null,
        };
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    private function studentReportRows(): array
    {
        return $this->activeStudentProfiles()
            ->with(['user.academicProfile', 'user.extracurricularAchievements', 'user.portfolioItems'])
            ->orderBy('full_name')
            ->get()
            ->map(fn (StudentProfile $profile): array => [
                $profile->full_name ?: $profile->user?->name ?: 'Без имени',
                $profile->user?->email,
                $profile->faculty,
                $profile->currentGroupName(),
                $profile->course,
                $profile->user?->academicProfile?->gpa,
                $profile->user?->academicProfile?->academic_debt,
                $profile->user?->extracurricularAchievements?->count() ?? 0,
                $profile->user?->portfolioItems?->count() ?? 0,
            ])
            ->all();
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    private function groupedReportRows(string $field): array
    {
        return $this->activeStudentProfiles()
            ->with('user.academicProfile')
            ->get()
            ->groupBy(fn (StudentProfile $profile): string => $field === 'student_group_id' && $profile->student_group_id === null
                ? 'unassigned:'.($profile->faculty ?: 'Не указано')
                : (string) ($profile->{$field} ?: 'Не указано'))
            ->sortKeys()
            ->map(fn (Collection $profiles, string $label): array => $this->groupedReportRow($profiles, $field, $label))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, StudentProfile>  $profiles
     * @return array<int, string|int|float|null>
     */
    private function groupedReportRow(Collection $profiles, string $field, string $label): array
    {
        $gpaValues = $profiles
            ->map(fn (StudentProfile $profile): mixed => $profile->user?->academicProfile?->gpa)
            ->filter(fn ($gpa): bool => $gpa !== null);

        $debtCount = $profiles
            ->filter(fn (StudentProfile $profile): bool => $this->riskService->hasAcademicDebt($profile->user?->academicProfile?->academic_debt))
            ->count();

        $base = [
            $field === 'student_group_id'
                ? ($profiles->first()?->currentGroupName() ?: 'Не указано')
                : $label,
        ];

        if ($field === 'student_group_id') {
            $base[] = $profiles->first()?->faculty ?: 'Не указано';
        }

        return [
            ...$base,
            $profiles->count(),
            $gpaValues->isNotEmpty() ? round((float) $gpaValues->avg(), 2) : null,
            $debtCount,
        ];
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    private function academicRiskReportRows(): array
    {
        return AcademicProfile::query()
            ->with('user.studentProfile.studentGroup')
            ->whereIn('user_id', $this->activeStudentUserIds())
            ->get()
            ->filter(fn (AcademicProfile $profile): bool => $this->riskService->academicRiskReasons($profile) !== [])
            ->map(function (AcademicProfile $profile): array {
                $student = $profile->user?->studentProfile;

                return [
                    $this->reportStudentName($profile->user),
                    $profile->user?->email,
                    $student?->faculty,
                    $student?->currentGroupName(),
                    $student?->course,
                    $profile->gpa !== null ? (float) $profile->gpa : null,
                    $profile->academic_debt,
                    $this->riskReasonText($this->riskService->academicRiskReasons($profile)),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    private function socialRiskReportRows(): array
    {
        return $this->activeStudentProfiles()
            ->with('user')
            ->get()
            ->filter(fn (StudentProfile $profile): bool => $this->riskService->hasSocialRisk($profile))
            ->map(fn (StudentProfile $profile): array => [
                $this->reportStudentName($profile->user),
                $profile->user?->email,
                $profile->faculty,
                $profile->currentGroupName(),
                $profile->course,
                $this->riskReasonText($this->riskService->socialRiskReasons($profile)),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    private function psychologicalRiskReportRows(): array
    {
        return User::query()
            ->with(['studentProfile.studentGroup', 'psychologicalProfile', 'healthPassport'])
            ->whereIn('id', $this->activeStudentUserIds())
            ->where(function ($query) {
                $query
                    ->whereHas('psychologicalProfile')
                    ->orWhereHas('healthPassport');
            })
            ->get()
            ->map(fn (User $user): array => [
                'user' => $user,
                'reasons' => $this->riskService->psychologicalRiskReasons($user),
            ])
            ->filter(fn (array $row): bool => $row['reasons'] !== [])
            ->map(function (array $row): array {
                /** @var User $user */
                $user = $row['user'];
                $student = $user->studentProfile;

                return [
                    $this->reportStudentName($user),
                    $user->email,
                    $student?->faculty,
                    $student?->currentGroupName(),
                    $this->riskReasonText($row['reasons']),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    private function medicalRiskReportRows(): array
    {
        return HealthPassport::query()
            ->with('user.studentProfile.studentGroup')
            ->whereIn('user_id', $this->activeStudentUserIds())
            ->get()
            ->map(fn (HealthPassport $passport): array => [
                'passport' => $passport,
                'reasons' => $this->riskService->medicalRiskReasons($passport),
            ])
            ->filter(fn (array $row): bool => $row['reasons'] !== [])
            ->map(function (array $row): array {
                /** @var HealthPassport $passport */
                $passport = $row['passport'];
                $student = $passport->user?->studentProfile;

                return [
                    $this->reportStudentName($passport->user),
                    $passport->user?->email,
                    $student?->faculty,
                    $student?->currentGroupName(),
                    $this->riskReasonText($row['reasons']),
                ];
            })
            ->values()
            ->all();
    }

    private function reportStudentName(?User $user): string
    {
        return $user?->studentProfile?->full_name
            ?: $user?->name
            ?: 'Без имени';
    }

    /**
     * @return Builder<StudentProfile>
     */
    private function activeStudentProfiles(): Builder
    {
        return StudentProfile::query()->active()->with('studentGroup');
    }

    /**
     * @return Collection<int, int>
     */
    private function activeStudentUserIds(): Collection
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->whereIn('slug', User::STUDENT_DATA_ROLES))
            ->where(function ($query): void {
                $query
                    ->doesntHave('studentProfile')
                    ->orWhereHas('studentProfile', fn ($query) => $query->active());
            })
            ->pluck('id')
            ->unique()
            ->values();
    }

    /**
     * @param  array<int, string|null>  $reasons
     */
    private function riskReasonText(array $reasons): string
    {
        return collect($reasons)
            ->filter()
            ->unique()
            ->implode(', ');
    }

}
