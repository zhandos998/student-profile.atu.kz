import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

const actionClass =
    'inline-flex items-center justify-center rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2f5192] focus:outline-none focus:ring-2 focus:ring-[#355da8] focus:ring-offset-2';

function formatValue(value, fallback = 'Не указано') {
    return value === null || value === undefined || value === ''
        ? fallback
        : value;
}

function StudentPanel({ title, children }) {
    return (
        <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200/80">
            <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4">
                <h3 className="text-base font-semibold text-[#274f93]">
                    {title}
                </h3>
            </div>
            <div className="p-5">{children}</div>
        </section>
    );
}

function DetailRow({ label, value }) {
    return (
        <div className="rounded-md bg-gray-50 px-3 py-2.5 ring-1 ring-gray-200/70">
            <p className="text-xs font-medium text-gray-500">
                {label}
            </p>
            <p className="mt-1 text-sm font-medium text-gray-900">
                {formatValue(value)}
            </p>
        </div>
    );
}

function LatestList({ items, emptyText }) {
    if (items.length === 0) {
        return <p className="text-sm text-gray-500">{emptyText}</p>;
    }

    return (
        <div className="space-y-3">
            {items.map((item, index) => (
                <div
                    key={`${item.title}-${index}`}
                    className="rounded-md bg-gray-50 px-4 py-3 ring-1 ring-gray-200/70"
                >
                    <p className="text-sm font-medium text-gray-900">
                        {item.title}
                    </p>
                    {item.meta && (
                        <p className="mt-1 text-sm text-gray-500">
                            {item.meta}
                        </p>
                    )}
                </div>
            ))}
        </div>
    );
}

function DashboardHero({ title, subtitle, stats = [] }) {
    return (
        <section className="overflow-hidden rounded-xl bg-[#355da8] px-6 py-6 text-white shadow-sm">
            <div className="grid gap-6 lg:grid-cols-[1.1fr_1fr] lg:items-end">
                <div>
                    <p className="text-sm font-medium text-white/75">
                        ATU Student Profile
                    </p>
                    <h1 className="mt-2 text-2xl font-semibold tracking-normal text-white sm:text-3xl">
                        {title}
                    </h1>
                    <p className="mt-3 max-w-2xl text-sm leading-6 text-white/80">
                        {subtitle}
                    </p>
                </div>

                {stats.length > 0 && (
                    <div className="grid gap-3 sm:grid-cols-2">
                        {stats.map((stat) => (
                            <div
                                key={stat.label}
                                className="rounded-lg bg-white/10 p-4 ring-1 ring-white/20"
                            >
                                <p className="text-xs font-medium text-white/70">
                                    {stat.label}
                                </p>
                                <p className="mt-2 text-2xl font-semibold tabular-nums text-white">
                                    {formatValue(stat.value, '0')}
                                </p>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}

function StudentHome({ data }) {
    return (
        <div className="space-y-6">
            <StudentPanel title="Личная информация">
                <div className="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <DetailRow
                            label="ФИО"
                            value={data.personalInfo.fullName}
                        />
                        <DetailRow
                            label="Факультет"
                            value={data.personalInfo.faculty}
                        />
                        <DetailRow
                            label="Группа"
                            value={data.personalInfo.group}
                        />
                        <DetailRow
                            label="Курс"
                            value={data.personalInfo.course}
                        />
                        <DetailRow
                            label="Специальность"
                            value={data.personalInfo.specialty}
                        />
                        <DetailRow
                            label="Контакты"
                            value={data.personalInfo.contactDetails}
                        />
                    </div>
                    <div className="rounded-md bg-[#f4f7fc] p-4 ring-1 ring-[#dbe5f6]">
                        <div className="flex items-center justify-between gap-4">
                            <p className="text-sm font-medium text-gray-700">
                                Заполнение анкеты
                            </p>
                            <p className="text-2xl font-semibold tabular-nums text-gray-900">
                                {data.personalInfo.completion}%
                            </p>
                        </div>
                        <div className="mt-3 h-2 rounded bg-[#dbe5f6]">
                            <div
                                className="h-2 rounded bg-[#355da8]"
                                style={{
                                    width: `${data.personalInfo.completion}%`,
                                }}
                            />
                        </div>
                        <Link
                            href={route('student-profile.edit')}
                            className={`${actionClass} mt-4`}
                        >
                            Открыть анкету
                        </Link>
                    </div>
                </div>
            </StudentPanel>

            <StudentPanel title="Успеваемость">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div className="rounded-md bg-[#355da8] p-4 text-white shadow-sm">
                        <p className="text-sm font-medium text-white/80">
                            GPA
                        </p>
                        <p className="mt-2 text-3xl font-semibold tabular-nums">
                            {formatValue(data.academic.gpa, 'Нет данных')}
                        </p>
                    </div>
                    <DetailRow
                        label="Язык обучения"
                        value={data.academic.educationLanguage}
                    />
                    <DetailRow
                        label="Текущая успеваемость"
                        value={data.academic.currentPerformance}
                    />
                    <DetailRow
                        label="Академическая задолженность"
                        value={data.academic.academicDebt || 'Нет'}
                    />
                    <div className="sm:col-span-2 xl:col-span-4">
                        <DetailRow
                            label="Прогноз успешности"
                            value={data.academic.successForecast}
                        />
                    </div>
                </div>
            </StudentPanel>

            <div className="grid gap-6 xl:grid-cols-2">
                <StudentPanel title="Достижения">
                    <div className="mb-4 flex items-center justify-between gap-4">
                        <p className="text-sm text-gray-600">
                            Всего записей
                        </p>
                        <p className="text-2xl font-semibold tabular-nums text-gray-900">
                            {data.achievements.count}
                        </p>
                    </div>
                    <LatestList
                        items={data.achievements.latest}
                        emptyText="Достижения пока не добавлены."
                    />
                </StudentPanel>

                <StudentPanel title="Портфолио">
                    <div className="mb-4 flex items-center justify-between gap-4">
                        <p className="text-sm text-gray-600">
                            Загружено файлов
                        </p>
                        <p className="text-2xl font-semibold tabular-nums text-gray-900">
                            {data.portfolio.count}
                        </p>
                    </div>
                    <LatestList
                        items={data.portfolio.latest}
                        emptyText="Файлы портфолио пока не добавлены."
                    />
                </StudentPanel>
            </div>

            <StudentPanel title="Рекомендации">
                <div className="grid gap-3 lg:grid-cols-2">
                    {data.recommendations.map((recommendation) => (
                        <div
                            key={recommendation.title}
                            className="rounded-md bg-gray-50 px-4 py-3 ring-1 ring-gray-200/70"
                        >
                            <p className="text-sm font-semibold text-gray-900">
                                {recommendation.title}
                            </p>
                            <p className="mt-1 text-sm text-gray-600">
                                {recommendation.description}
                            </p>
                        </div>
                    ))}
                </div>
            </StudentPanel>
        </div>
    );
}

function MetricTile({ label, value }) {
    return (
        <div className="rounded-md bg-gray-50 p-4 ring-1 ring-gray-200/70">
            <p className="text-sm font-medium text-gray-500">{label}</p>
            <p className="mt-2 text-2xl font-semibold tabular-nums text-gray-900">
                {formatValue(value, '0')}
            </p>
        </div>
    );
}

function CuratorAdvisorHome({ data }) {
    return (
        <div className="space-y-6">
            <StudentPanel title="Список студентов">
                <div className="mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-sm text-gray-600">
                            Всего студентов в доступных анкетах
                        </p>
                        <p className="mt-1 text-3xl font-semibold tabular-nums text-gray-900">
                            {data.students.total}
                        </p>
                    </div>
                    <Link
                        href={route('groups.index')}
                        className={actionClass}
                    >
                        Социальный паспорт группы
                    </Link>
                </div>

                {data.students.items.length === 0 ? (
                    <p className="text-sm text-gray-500">
                        Студенты пока не добавлены.
                    </p>
                ) : (
                    <div className="grid gap-3">
                        {data.students.items.map((student, index) => (
                            <div
                                key={`${student.name}-${index}`}
                                className="grid gap-3 rounded-md bg-gray-50 px-4 py-3 ring-1 ring-gray-200/70 md:grid-cols-[1.3fr_0.8fr_0.5fr_0.5fr]"
                            >
                                <div>
                                    <p className="text-sm font-semibold text-gray-900">
                                        {student.name}
                                    </p>
                                    <p className="mt-1 text-sm text-gray-500">
                                        {formatValue(student.faculty)}
                                    </p>
                                </div>
                                <DetailRow
                                    label="Группа"
                                    value={student.group}
                                />
                                <DetailRow
                                    label="Курс"
                                    value={student.course}
                                />
                                <DetailRow
                                    label="GPA"
                                    value={student.gpa ?? 'Нет данных'}
                                />
                            </div>
                        ))}
                    </div>
                )}
            </StudentPanel>

            <div className="grid gap-6 xl:grid-cols-2">
                <StudentPanel title="Социальный паспорт студента">
                    {data.socialPassports.length === 0 ? (
                        <p className="text-sm text-gray-500">
                            Социальные статусы пока не отмечены.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {data.socialPassports.map((student) => (
                                <div
                                    key={`${student.name}-${student.group}`}
                                    className="rounded-md bg-gray-50 px-4 py-3 ring-1 ring-gray-200/70"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <p className="text-sm font-semibold text-gray-900">
                                                {student.name}
                                            </p>
                                            <p className="mt-1 text-sm text-gray-500">
                                                {formatValue(student.group)}
                                            </p>
                                        </div>
                                        <span className="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                                            {student.statuses.length}
                                        </span>
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {student.statuses.map((status) => (
                                            <span
                                                key={status}
                                                className="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700"
                                            >
                                                {status}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </StudentPanel>

                <StudentPanel title="Группы риска">
                    <div className="grid gap-3 sm:grid-cols-2">
                        {data.riskGroups.map((risk) => (
                            <MetricTile
                                key={risk.label}
                                label={risk.label}
                                value={risk.count}
                            />
                        ))}
                    </div>

                    <div className="mt-5 space-y-3">
                        {data.riskStudents.length === 0 ? (
                            <p className="text-sm text-gray-500">
                                Студенты группы риска пока не выявлены.
                            </p>
                        ) : (
                            data.riskStudents.map((student) => (
                                <div
                                    key={`${student.name}-${student.group}`}
                                    className="rounded-md bg-gray-50 px-4 py-3 ring-1 ring-gray-200/70"
                                >
                                    <p className="text-sm font-semibold text-gray-900">
                                        {student.name}
                                    </p>
                                    <p className="mt-1 text-sm text-gray-500">
                                        {formatValue(student.group)}
                                    </p>
                                    <p className="mt-2 text-sm text-gray-700">
                                        {student.reasons.join(', ')}
                                    </p>
                                </div>
                            ))
                        )}
                    </div>
                </StudentPanel>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <StudentPanel title="Аналитика группы">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <MetricTile
                            label="Студенты"
                            value={data.analytics.totalStudents}
                        />
                        <MetricTile
                            label="Группы"
                            value={data.analytics.groupsCount}
                        />
                        <MetricTile
                            label="Средний GPA"
                            value={data.analytics.averageGpa ?? 'Нет данных'}
                        />
                        <MetricTile
                            label="Вовлеченность"
                            value={`${data.analytics.engagementLevel}%`}
                        />
                    </div>

                    <div className="mt-5 space-y-3">
                        {data.analytics.byGroups.map((group) => (
                            <div
                                key={group.group}
                                className="grid gap-3 rounded-md bg-gray-50 px-4 py-3 ring-1 ring-gray-200/70 sm:grid-cols-4"
                            >
                                <DetailRow label="Группа" value={group.group} />
                                <DetailRow
                                    label="Студенты"
                                    value={group.students}
                                />
                                <DetailRow
                                    label="GPA"
                                    value={group.averageGpa ?? 'Нет данных'}
                                />
                                <DetailRow label="Риски" value={group.risks} />
                            </div>
                        ))}
                    </div>
                </StudentPanel>

                <StudentPanel title="Уведомления">
                    <div className="space-y-3">
                        {data.notifications.map((notification) => (
                            <div
                                key={notification.title}
                                className="rounded-md bg-gray-50 px-4 py-3 ring-1 ring-gray-200/70"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-sm font-semibold text-gray-900">
                                            {notification.title}
                                        </p>
                                        <p className="mt-1 text-sm text-gray-600">
                                            {notification.description}
                                        </p>
                                    </div>
                                    <span className="rounded-md bg-[#355da8] px-3 py-2 text-sm font-semibold tabular-nums text-white">
                                        {notification.target}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                </StudentPanel>
            </div>
        </div>
    );
}

function AdministrationHome({ data }) {
    const toneClasses = {
        good: 'bg-emerald-50 text-emerald-800',
        warning: 'bg-amber-50 text-amber-800',
        danger: 'bg-rose-50 text-rose-800',
        neutral: 'bg-gray-100 text-gray-700',
    };

    return (
        <div className="space-y-6">
            <StudentPanel title="Общая статистика">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {data.statistics.map((metric) => (
                        <div
                            key={metric.label}
                            className="rounded-md bg-gray-50 p-4 ring-1 ring-gray-200/70"
                        >
                            <p className="text-sm font-medium text-gray-500">
                                {metric.label}
                            </p>
                            <p className="mt-2 text-3xl font-semibold tabular-nums text-gray-900">
                                {formatValue(metric.value, 'Нет данных')}
                            </p>
                            <p className="mt-2 text-sm text-gray-600">
                                {metric.hint}
                            </p>
                        </div>
                    ))}
                </div>
                <Link
                    href={route('analytics-dashboard.index')}
                    className={`${actionClass} mt-5`}
                >
                    Открыть аналитическую панель
                </Link>
            </StudentPanel>

            <div className="grid gap-6 xl:grid-cols-2">
                <StudentPanel title="Рейтинги">
                    {data.ratings.length === 0 ? (
                        <p className="text-sm text-gray-500">
                            Нет данных по GPA.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {data.ratings.map((student, index) => (
                                <div
                                    key={`${student.name}-${index}`}
                                    className="flex items-center justify-between gap-4 rounded-md bg-gray-50 px-4 py-3 ring-1 ring-gray-200/70"
                                >
                                    <div className="flex min-w-0 items-center gap-3">
                                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#355da8] text-sm font-semibold text-white">
                                            {index + 1}
                                        </div>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold text-gray-900">
                                                {student.name}
                                            </p>
                                            <p className="truncate text-sm text-gray-500">
                                                {formatValue(student.group)} •{' '}
                                                {formatValue(student.faculty)}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="text-right text-sm">
                                        <p className="font-semibold tabular-nums text-gray-900">
                                            GPA{' '}
                                            {formatValue(
                                                student.gpa,
                                                'нет',
                                            )}
                                        </p>
                                        <p className="text-gray-500">
                                            Достижений: {student.achievements}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </StudentPanel>

                <StudentPanel title="Мониторинг показателей">
                    <div className="space-y-3">
                        {data.monitoring.map((indicator) => (
                            <div
                                key={indicator.label}
                                className="rounded-md bg-gray-50 px-4 py-3 ring-1 ring-gray-200/70"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-sm font-semibold text-gray-900">
                                            {indicator.label}
                                        </p>
                                        <span
                                            className={`mt-2 inline-flex rounded-full px-3 py-1 text-xs font-medium ${
                                                toneClasses[indicator.tone] ??
                                                toneClasses.neutral
                                            }`}
                                        >
                                            {indicator.status}
                                        </span>
                                    </div>
                                    <p className="text-2xl font-semibold tabular-nums text-gray-900">
                                        {formatValue(indicator.value, '0')}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </StudentPanel>
            </div>

            <StudentPanel title="Ответственные лица">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {data.responsiblePersons.map((item) => (
                        <div
                            key={item.risk}
                            className="rounded-md bg-gray-50 p-4 ring-1 ring-gray-200/70"
                        >
                            <p className="text-sm font-semibold text-gray-900">
                                {item.risk}
                            </p>
                            <p className="mt-3 text-sm leading-6 text-gray-600">
                                {item.responsible}
                            </p>
                        </div>
                    ))}
                </div>
            </StudentPanel>

            <StudentPanel title="Отчеты">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {data.reports.map((report) => (
                        <div
                            key={report.type}
                            className="flex flex-col overflow-hidden rounded-md bg-white ring-1 ring-[#dbe5f6]"
                        >
                            <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-4 py-3">
                                <h4 className="text-base font-semibold text-[#274f93]">
                                    {report.title}
                                </h4>
                            </div>
                            <div className="flex flex-1 flex-col p-4">
                                <p className="flex-1 text-sm text-gray-600">
                                    {report.description}
                                </p>
                                <a
                                    href={report.exportUrl}
                                    className={`${actionClass} mt-4`}
                                >
                                    Excel
                                </a>
                            </div>
                        </div>
                    ))}
                </div>
            </StudentPanel>
        </div>
    );
}

function ModuleCard({ title, description, href }) {
    return (
        <div className="flex flex-col overflow-hidden rounded-md bg-white ring-1 ring-[#dbe5f6]">
            <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-4 py-3">
                <h3 className="text-base font-semibold text-[#274f93]">
                    {title}
                </h3>
            </div>
            <div className="flex flex-1 flex-col p-4">
                <p className="flex-1 text-sm text-gray-600">{description}</p>
                <Link href={href} className={`${actionClass} mt-4`}>
                    Открыть
                </Link>
            </div>
        </div>
    );
}

function dashboardHeroData({ studentHome, curatorAdvisorHome, administrationHome }) {
    if (studentHome) {
        return {
            title: 'Главная страница студента',
            subtitle:
                'Личная информация, успеваемость, достижения, портфолио и рекомендации собраны на одной странице.',
            stats: [
                {
                    label: 'GPA',
                    value: studentHome.academic.gpa ?? 'Нет данных',
                },
                {
                    label: 'Анкета',
                    value: `${studentHome.personalInfo.completion}%`,
                },
                {
                    label: 'Достижения',
                    value: studentHome.achievements.count,
                },
                {
                    label: 'Портфолио',
                    value: studentHome.portfolio.count,
                },
            ],
        };
    }

    if (curatorAdvisorHome) {
        const riskTotal = curatorAdvisorHome.riskGroups.reduce(
            (total, risk) => total + risk.count,
            0,
        );

        return {
            title: 'Главная страница куратора и эдвайзера',
            subtitle:
                'Быстрый обзор студентов, социальных статусов, групп риска, аналитики группы и уведомлений.',
            stats: [
                {
                    label: 'Студенты',
                    value: curatorAdvisorHome.students.total,
                },
                {
                    label: 'Группы',
                    value: curatorAdvisorHome.analytics.groupsCount,
                },
                {
                    label: 'Риски',
                    value: riskTotal,
                },
                {
                    label: 'Вовлеченность',
                    value: `${curatorAdvisorHome.analytics.engagementLevel}%`,
                },
            ],
        };
    }

    if (administrationHome) {
        return {
            title: 'Главная страница администрации',
            subtitle:
                'Общая статистика, рейтинги, отчеты и мониторинг показателей для управленческого контроля.',
            stats: administrationHome.statistics.map((metric) => ({
                label: metric.label,
                value: metric.value,
            })),
        };
    }

    return {
        title: 'Панель системы',
        subtitle: 'Выберите нужный раздел для работы со студенческими данными.',
        stats: [],
    };
}

export default function Dashboard({
    studentHome = null,
    curatorAdvisorHome = null,
    administrationHome = null,
}) {
    const {
        user,
        canViewPsychologicalProfile,
        canViewGroupSocialPassport,
        canViewAnalyticsDashboard,
        canManageStudentProfiles,
        canUseOwnStudentProfile,
    } = usePage().props.auth;
    const hero = dashboardHeroData({
        studentHome,
        curatorAdvisorHome,
        administrationHome,
    });

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Панель
                </h2>
            }
        >
            <Head title="Панель" />

            <div className="bg-[#f4f7fc] py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <DashboardHero
                        title={hero.title}
                        subtitle={`${hero.subtitle} Пользователь: ${user.name}`}
                        stats={hero.stats}
                    />

                    {studentHome && <StudentHome data={studentHome} />}
                    {curatorAdvisorHome && (
                        <CuratorAdvisorHome data={curatorAdvisorHome} />
                    )}
                    {administrationHome && (
                        <AdministrationHome data={administrationHome} />
                    )}

                    <StudentPanel title="Разделы системы">
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            {canUseOwnStudentProfile && (
                                <ModuleCard
                                    title="Портрет студента"
                                    description="Карточка, академический профиль, достижения и портфолио."
                                    href={route('student-profile.edit')}
                                />
                            )}
                            {canManageStudentProfiles && (
                                <ModuleCard
                                    title="Портреты студентов"
                                    description="Список студентов, фильтры, создание и редактирование портретов."
                                    href={route('student-profiles.index')}
                                />
                            )}
                            {canViewPsychologicalProfile && (
                                <ModuleCard
                                    title="Психологический профиль"
                                    description="Результаты психотестов и индивидуальные особенности студента."
                                    href={route('psychological-profile.index')}
                                />
                            )}
                            {canViewGroupSocialPassport && (
                                <ModuleCard
                                    title="Социальный паспорт группы"
                                    description="Сведения о группе, студентах и количественный социальный статус."
                                    href={route('groups.index')}
                                />
                            )}
                            {canViewAnalyticsDashboard && (
                                <ModuleCard
                                    title="Аналитика"
                                    description="Количество студентов, GPA, риски, вовлеченность и отчеты."
                                    href={route('analytics-dashboard.index')}
                                />
                            )}
                        </div>
                    </StudentPanel>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
