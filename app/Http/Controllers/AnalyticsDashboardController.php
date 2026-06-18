<?php

namespace App\Http\Controllers;

use App\Models\AcademicProfile;
use App\Models\ExtracurricularAchievement;
use App\Models\HealthPassport;
use App\Models\PortfolioItem;
use App\Models\PsychologicalProfile;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentRiskService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AnalyticsDashboardController extends Controller
{
    public function __construct(private readonly StudentRiskService $riskService)
    {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canViewAnalyticsDashboard(), 403);

        $studentCount = $this->studentCount();
        $averageGpa = AcademicProfile::query()->whereNotNull('gpa')->avg('gpa');
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
            'notificationChannels' => $this->notificationChannels(),
            'notificationEvents' => $this->notificationEvents(),
            'reports' => $this->reports(),
            'integrations' => $this->integrations(),
        ]);
    }

    public function export(Request $request, string $type): SymfonyResponse
    {
        abort_unless($request->user()?->canViewAnalyticsDashboard(), 403);

        $report = $this->reportData($type);
        abort_if($report === null, 404);

        return response($this->excelHtml($report['title'], $report['columns'], $report['rows']), 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => sprintf(
                'attachment; filename="%s-report-%s.xls"',
                $type,
                now()->format('Y-m-d'),
            ),
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function studentCount(): int
    {
        $count = User::query()
            ->whereHas('role', fn ($query) => $query->where('slug', Role::STUDENT))
            ->count();

        return $count > 0 ? $count : StudentProfile::query()->count();
    }

    private function engagedStudentCount(): int
    {
        return ExtracurricularAchievement::query()
            ->distinct()
            ->pluck('user_id')
            ->merge(PortfolioItem::query()->distinct()->pluck('user_id'))
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
        return StudentProfile::query()
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
        return PsychologicalProfile::query()
            ->get(['user_id', 'testing_results', 'individual_features'])
            ->filter(fn (PsychologicalProfile $profile): bool => $this->riskService->hasMeaningfulText($profile->testing_results)
                || $this->riskService->hasMeaningfulText($profile->individual_features))
            ->pluck('user_id')
            ->merge(
                HealthPassport::query()
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
            ->with(['user.studentProfile', 'user.extracurricularAchievements'])
            ->whereNotNull('gpa')
            ->orderByDesc('gpa')
            ->limit(5)
            ->get()
            ->map(fn (AcademicProfile $profile): array => [
                'name' => $profile->user?->studentProfile?->full_name
                    ?: $profile->user?->name
                    ?: 'Без имени',
                'group' => $profile->user?->studentProfile?->group_name ?: 'Не указано',
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
                'value' => StudentProfile::query()->count(),
            ],
            [
                'label' => 'Академические профили',
                'value' => AcademicProfile::query()->count(),
            ],
            [
                'label' => 'Внеучебные достижения',
                'value' => ExtracurricularAchievement::query()->count(),
            ],
            [
                'label' => 'Файлы портфолио',
                'value' => PortfolioItem::query()->count(),
            ],
        ];
    }

    /**
     * @return array<int, array{type: string, title: string, basis: string, target: int, action: string}>
     */
    private function recommendations(): array
    {
        $lowGpaCount = AcademicProfile::query()
            ->whereNotNull('gpa')
            ->where('gpa', '<', 2.5)
            ->count();

        $academicDebtCount = AcademicProfile::query()
            ->whereNotNull('academic_debt')
            ->whereNotIn('academic_debt', ['', 'Нет', 'нет', 'НЕТ'])
            ->count();

        $inactiveStudentCount = max(
            $this->studentCount() - $this->engagedStudentCount(),
            0,
        );

        $highGpaCount = AcademicProfile::query()
            ->whereNotNull('gpa')
            ->where('gpa', '>=', 3.5)
            ->count();

        $socialRiskCount = StudentProfile::query()
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
     * @return array<int, array{name: string, description: string, status: string}>
     */
    private function notificationChannels(): array
    {
        return [
            [
                'name' => 'Web',
                'description' => 'Уведомления внутри личного кабинета.',
                'status' => 'Готово для системных сообщений',
            ],
            [
                'name' => 'Email',
                'description' => 'Отправка важных уведомлений на электронную почту.',
                'status' => 'Канал для официальных писем',
            ],
            [
                'name' => 'Push',
                'description' => 'Быстрые уведомления в браузере или мобильном приложении.',
                'status' => 'Подключается после настройки устройства',
            ],
            [
                'name' => 'WhatsApp',
                'description' => 'Оперативные сообщения через WhatsApp.',
                'status' => 'Нужна интеграция с провайдером',
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, description: string, audience: string}>
     */
    private function notificationEvents(): array
    {
        return [
            [
                'name' => 'Снижение успеваемости',
                'description' => 'Фиксируется при падении GPA или появлении академической задолженности.',
                'audience' => 'Куратор, эдвайзер, администрация',
            ],
            [
                'name' => 'Новые достижения',
                'description' => 'Фиксируется после добавления олимпиад, конкурсов, публикаций, проектов или файлов портфолио.',
                'audience' => 'Куратор, эдвайзер, администрация',
            ],
            [
                'name' => 'Необходимость обновления данных',
                'description' => 'Фиксируется, когда анкета студента или контактные данные требуют актуализации.',
                'audience' => 'Студент, староста, куратор',
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
     * @return array<int, array{name: string, description: string, data: string, status: string}>
     */
    private function integrations(): array
    {
        return [
            [
                'name' => 'LMS',
                'description' => 'Платонус и другие LMS-системы для синхронизации учебных данных.',
                'data' => 'Дисциплины, оценки, посещаемость, академическая задолженность',
                'status' => 'Обязательная интеграция',
            ],
            [
                'name' => 'Электронный журнал',
                'description' => 'Источник текущей успеваемости и посещаемости студентов.',
                'data' => 'Текущие оценки, пропуски, динамика успеваемости',
                'status' => 'Обязательная интеграция',
            ],
            [
                'name' => 'Платформа тестирования',
                'description' => 'Получение результатов психологических и академических тестирований.',
                'data' => 'Результаты тестов, индивидуальные особенности, группы риска',
                'status' => 'Обязательная интеграция',
            ],
            [
                'name' => 'База студентов',
                'description' => 'Основной источник анкетных, академических и контактных данных.',
                'data' => 'ФИО, ИИН, факультет, группа, курс, статус обучения',
                'status' => 'Обязательная интеграция',
            ],
            [
                'name' => 'Google Workspace / Microsoft 365',
                'description' => 'Интеграция с корпоративными аккаунтами, календарями и почтой.',
                'data' => 'Email, группы доступа, календарные события, документы',
                'status' => 'Обязательная интеграция',
            ],
        ];
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
                'rows' => $this->groupedReportRows('group_name'),
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
        return StudentProfile::query()
            ->with(['user.academicProfile', 'user.extracurricularAchievements', 'user.portfolioItems'])
            ->orderBy('full_name')
            ->get()
            ->map(fn (StudentProfile $profile): array => [
                $profile->full_name ?: $profile->user?->name ?: 'Без имени',
                $profile->user?->email,
                $profile->faculty,
                $profile->group_name,
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
        return StudentProfile::query()
            ->with('user.academicProfile')
            ->get()
            ->groupBy(fn (StudentProfile $profile): string => (string) ($profile->{$field} ?: 'Не указано'))
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
            $label,
        ];

        if ($field === 'group_name') {
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
            ->with('user.studentProfile')
            ->get()
            ->filter(fn (AcademicProfile $profile): bool => $this->riskService->academicRiskReasons($profile) !== [])
            ->map(function (AcademicProfile $profile): array {
                $student = $profile->user?->studentProfile;

                return [
                    $this->reportStudentName($profile->user),
                    $profile->user?->email,
                    $student?->faculty,
                    $student?->group_name,
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
        return StudentProfile::query()
            ->with('user')
            ->get()
            ->filter(fn (StudentProfile $profile): bool => $this->riskService->hasSocialRisk($profile))
            ->map(fn (StudentProfile $profile): array => [
                $this->reportStudentName($profile->user),
                $profile->user?->email,
                $profile->faculty,
                $profile->group_name,
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
            ->with(['studentProfile', 'psychologicalProfile', 'healthPassport'])
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
                    $student?->group_name,
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
            ->with('user.studentProfile')
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
                    $student?->group_name,
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
     * @param  array<int, string|null>  $reasons
     */
    private function riskReasonText(array $reasons): string
    {
        return collect($reasons)
            ->filter()
            ->unique()
            ->implode(', ');
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    private function excelHtml(string $title, array $columns, array $rows): string
    {
        $headerCells = collect($columns)
            ->map(fn (string $column): string => '<th>'.e($column).'</th>')
            ->implode('');

        $bodyRows = collect($rows)
            ->map(fn (array $row): string => '<tr>'.collect($row)
                ->map(fn ($value): string => '<td>'.e((string) ($value ?? '')).'</td>')
                ->implode('').'</tr>')
            ->implode('');

        if ($bodyRows === '') {
            $bodyRows = '<tr><td colspan="'.count($columns).'">Нет данных</td></tr>';
        }

        return '<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 6px; }
        th { background: #f3f4f6; font-weight: bold; }
    </style>
</head>
<body>
    <h1>'.e($title).'</h1>
    <table>
        <thead><tr>'.$headerCells.'</tr></thead>
        <tbody>'.$bodyRows.'</tbody>
    </table>
</body>
</html>';
    }
}
