<?php

namespace App\Http\Controllers;

use App\Models\AcademicProfile;
use App\Models\ExtracurricularAchievement;
use App\Models\PortfolioItem;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentRiskService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly StudentRiskService $riskService)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $user?->loadMissing([
            'role',
            'studentProfile',
            'academicProfile',
            'extracurricularAchievements',
            'portfolioItems',
        ]);

        return Inertia::render('Dashboard', [
            'studentHome' => $user?->canUseOwnStudentProfile()
                ? $this->studentHome($user)
                : null,
            'curatorAdvisorHome' => $user?->canViewCuratorAdvisorDashboard()
                ? $this->curatorAdvisorHome($user)
                : null,
            'administrationHome' => $user?->canViewAnalyticsDashboard()
                ? $this->administrationHome()
                : null,
        ]);
    }

    /**
     * @return array{
     *     personalInfo: array<string, mixed>,
     *     academic: array<string, mixed>,
     *     achievements: array<string, mixed>,
     *     portfolio: array<string, mixed>,
     *     recommendations: array<int, array{title: string, description: string}>
     * }
     */
    private function studentHome(User $user): array
    {
        $profile = $user->studentProfile;
        $academic = $user->academicProfile;

        return [
            'personalInfo' => [
                'fullName' => $profile?->full_name ?: $user->name,
                'faculty' => $profile?->faculty,
                'group' => $profile?->group_name,
                'course' => $profile?->course,
                'specialty' => $profile?->specialty,
                'contactDetails' => $profile?->contact_details,
                'completion' => $this->profileCompletion($user),
            ],
            'academic' => [
                'gpa' => $academic?->gpa !== null ? (float) $academic->gpa : null,
                'educationLanguage' => $academic?->education_language,
                'currentPerformance' => $academic?->current_performance,
                'academicDebt' => $academic?->academic_debt,
                'successForecast' => $academic?->success_forecast,
            ],
            'achievements' => [
                'count' => $user->extracurricularAchievements->count(),
                'latest' => $this->latestActivity($user->extracurricularAchievements, ['activity_type', 'level', 'result']),
            ],
            'portfolio' => [
                'count' => $user->portfolioItems->count(),
                'latest' => $this->latestActivity($user->portfolioItems, ['item_type', 'original_name']),
            ],
            'recommendations' => $this->studentRecommendations(),
        ];
    }

    private function profileCompletion(User $user): int
    {
        return $this->riskService->profileCompletion($user->studentProfile);
    }

    /**
     * @return array{
     *     students: array{total: int, items: array<int, array<string, mixed>>},
     *     socialPassports: array<int, array{name: string, group: string|null, statuses: array<int, string>}>,
     *     riskGroups: array<int, array{label: string, count: int}>,
     *     riskStudents: array<int, array{name: string, group: string|null, reasons: array<int, string>}>,
     *     analytics: array<string, mixed>,
     *     notifications: array<int, array{title: string, description: string, target: int}>
     * }
     */
    private function curatorAdvisorHome(User $user): array
    {
        $profiles = $this->studentProfilesForSupervision($user);

        return [
            'students' => [
                'total' => $profiles->count(),
                'items' => $profiles
                    ->take(8)
                    ->map(fn (StudentProfile $profile): array => [
                        'name' => $profile->full_name ?: $profile->user?->name ?: 'Без имени',
                        'group' => $profile->group_name,
                        'course' => $profile->course,
                        'faculty' => $profile->faculty,
                        'gpa' => $profile->user?->academicProfile?->gpa !== null
                            ? (float) $profile->user->academicProfile->gpa
                            : null,
                    ])
                    ->values()
                    ->all(),
            ],
            'socialPassports' => $this->socialPassportRows($profiles),
            'riskGroups' => $this->curatorRiskGroups($profiles),
            'riskStudents' => $this->riskStudentRows($profiles),
            'analytics' => $this->groupAnalytics($profiles),
            'notifications' => $this->curatorNotifications($profiles),
        ];
    }

    /**
     * @return Collection<int, StudentProfile>
     */
    private function studentProfilesForSupervision(?User $user = null): Collection
    {
        $query = StudentProfile::query()
            ->with(['user.academicProfile', 'user.extracurricularAchievements', 'user.portfolioItems'])
            ->orderBy('group_name')
            ->orderBy('full_name');

        if ($user && ! $user->canManageStudentProfiles() && ! $user->canViewAllStudentData()) {
            $groups = $user->studentGroups()->get(['id', 'name']);
            $groupIds = $groups->pluck('id');
            $groupNames = $groups->pluck('name')->filter()->values();

            $query->where(function ($query) use ($groupIds, $groupNames): void {
                $query->whereIn('student_group_id', $groupIds);

                if ($groupNames->isNotEmpty()) {
                    $query->orWhereIn('group_name', $groupNames);
                }
            });
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, StudentProfile>  $profiles
     * @return array<int, array{name: string, group: string|null, statuses: array<int, string>}>
     */
    private function socialPassportRows(Collection $profiles): array
    {
        return $profiles
            ->filter(fn (StudentProfile $profile): bool => $this->riskService->socialStatusLabels($profile) !== [])
            ->take(6)
            ->map(fn (StudentProfile $profile): array => [
                'name' => $profile->full_name ?: $profile->user?->name ?: 'Без имени',
                'group' => $profile->group_name,
                'statuses' => $this->riskService->socialStatusLabels($profile),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, StudentProfile>  $profiles
     * @return array<int, array{label: string, count: int}>
     */
    private function curatorRiskGroups(Collection $profiles): array
    {
        return [
            [
                'label' => 'Снижение успеваемости',
                'count' => $profiles
                    ->filter(fn (StudentProfile $profile): bool => $this->riskService->hasLowGpa($profile->user?->academicProfile))
                    ->count(),
            ],
            [
                'label' => 'Академическая задолженность',
                'count' => $profiles
                    ->filter(fn (StudentProfile $profile): bool => $this->riskService->hasAcademicDebt($profile->user?->academicProfile?->academic_debt))
                    ->count(),
            ],
            [
                'label' => 'Социальные факторы',
                'count' => $profiles
                    ->filter(fn (StudentProfile $profile): bool => $this->riskService->socialStatusLabels($profile) !== [])
                    ->count(),
            ],
            [
                'label' => 'Нужно обновить данные',
                'count' => $profiles
                    ->filter(fn (StudentProfile $profile): bool => $this->riskService->profileCompletion($profile) < 80)
                    ->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, StudentProfile>  $profiles
     * @return array<int, array{name: string, group: string|null, reasons: array<int, string>}>
     */
    private function riskStudentRows(Collection $profiles): array
    {
        return $profiles
            ->map(fn (StudentProfile $profile): array => [
                'name' => $profile->full_name ?: $profile->user?->name ?: 'Без имени',
                'group' => $profile->group_name,
                'reasons' => $this->riskService->dashboardRiskReasons($profile),
            ])
            ->filter(fn (array $student): bool => $student['reasons'] !== [])
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, StudentProfile>  $profiles
     * @return array<string, mixed>
     */
    private function groupAnalytics(Collection $profiles): array
    {
        $gpaValues = $profiles
            ->map(fn (StudentProfile $profile): mixed => $profile->user?->academicProfile?->gpa)
            ->filter(fn ($gpa): bool => $gpa !== null);

        $engagedCount = $profiles
            ->filter(fn (StudentProfile $profile): bool => ($profile->user?->extracurricularAchievements?->isNotEmpty() ?? false)
                || ($profile->user?->portfolioItems?->isNotEmpty() ?? false))
            ->count();

        return [
            'totalStudents' => $profiles->count(),
            'groupsCount' => $profiles
                ->pluck('group_name')
                ->filter()
                ->unique()
                ->count(),
            'averageGpa' => $gpaValues->isNotEmpty() ? round((float) $gpaValues->avg(), 2) : null,
            'engagementLevel' => $profiles->isNotEmpty()
                ? (int) round(($engagedCount / $profiles->count()) * 100)
                : 0,
            'incompleteProfiles' => $profiles
                ->filter(fn (StudentProfile $profile): bool => $this->riskService->profileCompletion($profile) < 80)
                ->count(),
            'byGroups' => $this->analyticsByGroups($profiles),
        ];
    }

    /**
     * @param  Collection<int, StudentProfile>  $profiles
     * @return array<int, array{group: string, students: int, averageGpa: float|null, risks: int}>
     */
    private function analyticsByGroups(Collection $profiles): array
    {
        return $profiles
            ->groupBy(fn (StudentProfile $profile): string => $profile->group_name ?: 'Не указано')
            ->sortKeys()
            ->take(6)
            ->map(function (Collection $groupProfiles, string $group): array {
                $gpaValues = $groupProfiles
                    ->map(fn (StudentProfile $profile): mixed => $profile->user?->academicProfile?->gpa)
                    ->filter(fn ($gpa): bool => $gpa !== null);

                return [
                    'group' => $group,
                    'students' => $groupProfiles->count(),
                    'averageGpa' => $gpaValues->isNotEmpty() ? round((float) $gpaValues->avg(), 2) : null,
                    'risks' => $groupProfiles
                        ->filter(fn (StudentProfile $profile): bool => $this->riskService->dashboardRiskReasons($profile) !== [])
                        ->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, StudentProfile>  $profiles
     * @return array<int, array{title: string, description: string, target: int}>
     */
    private function curatorNotifications(Collection $profiles): array
    {
        $riskGroups = collect($this->curatorRiskGroups($profiles))->keyBy('label');

        return [
            [
                'title' => 'Проверить снижение успеваемости',
                'description' => 'Студенты с GPA ниже 2.5 требуют консультации куратора или эдвайзера.',
                'target' => $riskGroups['Снижение успеваемости']['count'] ?? 0,
            ],
            [
                'title' => 'Закрыть академическую задолженность',
                'description' => 'Нужно согласовать план работы по проблемным дисциплинам.',
                'target' => $riskGroups['Академическая задолженность']['count'] ?? 0,
            ],
            [
                'title' => 'Обновить данные студентов',
                'description' => 'Анкеты с неполными контактами и адресами нужно актуализировать.',
                'target' => $riskGroups['Нужно обновить данные']['count'] ?? 0,
            ],
        ];
    }

    /**
     * @return array{
     *     statistics: array<int, array{label: string, value: string|int|float|null, hint: string}>,
     *     ratings: array<int, array{name: string, group: string|null, faculty: string|null, gpa: float|null, achievements: int}>,
     *     reports: array<int, array{type: string, title: string, description: string, exportUrl: string}>,
     *     monitoring: array<int, array{label: string, value: string|int|float|null, status: string, tone: string}>,
     *     responsiblePersons: array<int, array{risk: string, responsible: string}>
     * }
     */
    private function administrationHome(): array
    {
        $profiles = $this->studentProfilesForSupervision();
        $studentCount = $profiles->count();
        $averageGpa = $this->averageGpa($profiles);
        $riskCount = $profiles
            ->filter(fn (StudentProfile $profile): bool => $this->riskService->dashboardRiskReasons($profile) !== [])
            ->count();
        $engagedCount = $profiles
            ->filter(fn (StudentProfile $profile): bool => ($profile->user?->extracurricularAchievements?->isNotEmpty() ?? false)
                || ($profile->user?->portfolioItems?->isNotEmpty() ?? false))
            ->count();

        return [
            'statistics' => [
                [
                    'label' => 'Количество студентов',
                    'value' => $studentCount,
                    'hint' => 'Всего заполненных студенческих анкет',
                ],
                [
                    'label' => 'Средний GPA',
                    'value' => $averageGpa,
                    'hint' => 'Средний показатель по имеющимся академическим профилям',
                ],
                [
                    'label' => 'Группы риска',
                    'value' => $riskCount,
                    'hint' => 'Студенты с академическими или социальными факторами риска',
                ],
                [
                    'label' => 'Вовлеченность',
                    'value' => $studentCount > 0 ? (int) round(($engagedCount / $studentCount) * 100).'%' : '0%',
                    'hint' => 'Студенты с достижениями или портфолио',
                ],
            ],
            'ratings' => $this->administrationRatings(),
            'reports' => $this->administrationReports(),
            'monitoring' => $this->monitoringIndicators($profiles),
            'responsiblePersons' => $this->administrationResponsiblePersons(),
        ];
    }

    /**
     * @return array<int, array{risk: string, responsible: string}>
     */
    private function administrationResponsiblePersons(): array
    {
        return [
            [
                'risk' => 'Социальные риски',
                'responsible' => 'Зам.деканы по ВР и кураторы/эдвайзеры',
            ],
            [
                'risk' => 'Академические риски',
                'responsible' => 'Зам.декана по УР и кураторы/эдвайзеры',
            ],
            [
                'risk' => 'Психологические риски',
                'responsible' => 'СПП',
            ],
            [
                'risk' => 'Медицинские риски',
                'responsible' => 'Здравпункт',
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, group: string|null, faculty: string|null, gpa: float|null, achievements: int}>
     */
    private function administrationRatings(): array
    {
        return AcademicProfile::query()
            ->with(['user.studentProfile', 'user.extracurricularAchievements'])
            ->whereNotNull('gpa')
            ->orderByDesc('gpa')
            ->limit(5)
            ->get()
            ->map(fn (AcademicProfile $profile): array => [
                'name' => $profile->user?->studentProfile?->full_name ?: $profile->user?->name ?: 'Без имени',
                'group' => $profile->user?->studentProfile?->group_name,
                'faculty' => $profile->user?->studentProfile?->faculty,
                'gpa' => $profile->gpa !== null ? (float) $profile->gpa : null,
                'achievements' => $profile->user?->extracurricularAchievements?->count() ?? 0,
            ])
            ->all();
    }

    /**
     * @return array<int, array{type: string, title: string, description: string, exportUrl: string}>
     */
    private function administrationReports(): array
    {
        return [
            [
                'type' => 'student',
                'title' => 'По студенту',
                'description' => 'Анкета, GPA, группа, достижения и портфолио.',
                'exportUrl' => route('analytics-dashboard.reports.export', ['type' => 'student']),
            ],
            [
                'type' => 'group',
                'title' => 'По группе',
                'description' => 'Количество студентов, средний GPA и риски по группам.',
                'exportUrl' => route('analytics-dashboard.reports.export', ['type' => 'group']),
            ],
            [
                'type' => 'course',
                'title' => 'По курсу',
                'description' => 'Сводные показатели по курсам обучения.',
                'exportUrl' => route('analytics-dashboard.reports.export', ['type' => 'course']),
            ],
            [
                'type' => 'faculty',
                'title' => 'По факультету',
                'description' => 'Сводные показатели по факультетам.',
                'exportUrl' => route('analytics-dashboard.reports.export', ['type' => 'faculty']),
            ],
            [
                'type' => 'academic-risks',
                'title' => 'По академическим рискам',
                'description' => 'Низкий GPA и академическая задолженность.',
                'exportUrl' => route('analytics-dashboard.reports.export', ['type' => 'academic-risks']),
            ],
            [
                'type' => 'social-risks',
                'title' => 'По социальным рискам',
                'description' => 'Социальные факторы риска и потребность в поддержке.',
                'exportUrl' => route('analytics-dashboard.reports.export', ['type' => 'social-risks']),
            ],
            [
                'type' => 'psychological-risks',
                'title' => 'По психологическим рискам',
                'description' => 'Психологические факторы из профиля и паспорта здоровья.',
                'exportUrl' => route('analytics-dashboard.reports.export', ['type' => 'psychological-risks']),
            ],
            [
                'type' => 'medical-risks',
                'title' => 'По медицинским рискам',
                'description' => 'Медицинские факторы из паспорта здоровья.',
                'exportUrl' => route('analytics-dashboard.reports.export', ['type' => 'medical-risks']),
            ],
        ];
    }

    /**
     * @param  Collection<int, StudentProfile>  $profiles
     * @return array<int, array{label: string, value: string|int|float|null, status: string, tone: string}>
     */
    private function monitoringIndicators(Collection $profiles): array
    {
        $studentCount = $profiles->count();
        $averageCompletion = $studentCount > 0
            ? (int) round($profiles->map(fn (StudentProfile $profile): int => $this->riskService->profileCompletion($profile))->avg())
            : 0;
        $academicDebtCount = $profiles
            ->filter(fn (StudentProfile $profile): bool => $this->riskService->hasAcademicDebt($profile->user?->academicProfile?->academic_debt))
            ->count();
        $lowGpaCount = $profiles
            ->filter(fn (StudentProfile $profile): bool => $this->riskService->hasLowGpa($profile->user?->academicProfile))
            ->count();
        $portfolioFiles = PortfolioItem::query()->count();
        $achievements = ExtracurricularAchievement::query()->count();

        return [
            [
                'label' => 'Заполнение анкет',
                'value' => $averageCompletion.'%',
                'status' => $averageCompletion >= 80 ? 'Норма' : 'Требует внимания',
                'tone' => $averageCompletion >= 80 ? 'good' : 'warning',
            ],
            [
                'label' => 'Академическая задолженность',
                'value' => $academicDebtCount,
                'status' => $academicDebtCount === 0 ? 'Нет задолженности' : 'Есть задолженность',
                'tone' => $academicDebtCount === 0 ? 'good' : 'danger',
            ],
            [
                'label' => 'Снижение успеваемости',
                'value' => $lowGpaCount,
                'status' => $lowGpaCount === 0 ? 'Стабильно' : 'Нужен контроль',
                'tone' => $lowGpaCount === 0 ? 'good' : 'warning',
            ],
            [
                'label' => 'Достижения',
                'value' => $achievements,
                'status' => 'Активность студентов',
                'tone' => 'neutral',
            ],
            [
                'label' => 'Файлы портфолио',
                'value' => $portfolioFiles,
                'status' => 'Цифровое портфолио',
                'tone' => 'neutral',
            ],
        ];
    }

    /**
     * @param  Collection<int, StudentProfile>  $profiles
     */
    private function averageGpa(Collection $profiles): ?float
    {
        $gpaValues = $profiles
            ->map(fn (StudentProfile $profile): mixed => $profile->user?->academicProfile?->gpa)
            ->filter(fn ($gpa): bool => $gpa !== null);

        return $gpaValues->isNotEmpty() ? round((float) $gpaValues->avg(), 2) : null;
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @param  array<int, string>  $metaFields
     * @return array<int, array{title: string, meta: string|null}>
     */
    private function latestActivity(Collection $items, array $metaFields): array
    {
        return $items
            ->sortByDesc('created_at')
            ->take(3)
            ->map(fn ($item): array => [
                'title' => $item->title,
                'meta' => collect($metaFields)
                    ->map(fn (string $field): mixed => $item->{$field})
                    ->filter(fn ($value): bool => filled($value))
                    ->implode(' • ') ?: null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{title: string, description: string}>
     */
    private function studentRecommendations(): array
    {
        return [
            [
                'title' => 'Индивидуальное консультирование',
                'description' => 'Консультация с куратором, эдвайзером или психологом по учебным и личным вопросам.',
            ],
            [
                'title' => 'Мониторинг академической успеваемости',
                'description' => 'Контроль GPA, текущей успеваемости и академической задолженности.',
            ],
            [
                'title' => 'План ментора',
                'description' => 'Персональный план сопровождения с целями, сроками и ответственными.',
            ],
            [
                'title' => 'Социальная поддержка',
                'description' => 'Материальная помощь, скидки и другие меры поддержки при наличии оснований.',
            ],
            [
                'title' => 'Работа с семьей',
                'description' => 'Взаимодействие с семьей только по согласованию со студентом.',
            ],
            [
                'title' => 'Контроль проживания в общежитии',
                'description' => 'Проверка условий проживания и актуальности данных по общежитию.',
            ],
        ];
    }

}
