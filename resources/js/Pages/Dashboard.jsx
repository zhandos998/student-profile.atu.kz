import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

function formatValue(value, fallback = 'Не указано') {
    return value === null || value === undefined || value === ''
        ? fallback
        : value;
}

function StudentPanel({ title, children }) {
    return (
        <section className="rounded-md border border-gray-200 bg-white shadow-sm">
            <div className="border-b border-gray-200 px-5 py-4">
                <h3 className="text-base font-semibold text-gray-900">
                    {title}
                </h3>
            </div>
            <div className="p-5">{children}</div>
        </section>
    );
}

function DetailRow({ label, value }) {
    return (
        <div>
            <p className="text-xs font-medium uppercase tracking-wide text-gray-500">
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
                    className="rounded-md border border-gray-200 px-4 py-3"
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
                    <div className="rounded-md bg-gray-50 p-4">
                        <div className="flex items-center justify-between gap-4">
                            <p className="text-sm font-medium text-gray-700">
                                Заполнение анкеты
                            </p>
                            <p className="text-2xl font-semibold tabular-nums text-gray-900">
                                {data.personalInfo.completion}%
                            </p>
                        </div>
                        <div className="mt-3 h-2 rounded bg-gray-200">
                            <div
                                className="h-2 rounded bg-gray-900"
                                style={{
                                    width: `${data.personalInfo.completion}%`,
                                }}
                            />
                        </div>
                        <Link
                            href={route('student-profile.edit')}
                            className="mt-4 inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700"
                        >
                            Открыть анкету
                        </Link>
                    </div>
                </div>
            </StudentPanel>

            <StudentPanel title="Успеваемость">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div className="rounded-md bg-gray-900 p-4 text-white">
                        <p className="text-sm font-medium text-gray-300">
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
                            className="rounded-md border border-gray-200 px-4 py-3"
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
        <div className="rounded-md bg-gray-50 p-4">
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
                        href={route('group-social-passport.edit')}
                        className="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700"
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
                                className="grid gap-3 rounded-md border border-gray-200 px-4 py-3 md:grid-cols-[1.3fr_0.8fr_0.5fr_0.5fr]"
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
                                    className="rounded-md border border-gray-200 px-4 py-3"
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
                                    className="rounded-md border border-gray-200 px-4 py-3"
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
                                className="grid gap-3 rounded-md border border-gray-200 px-4 py-3 sm:grid-cols-4"
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
                                className="rounded-md border border-gray-200 px-4 py-3"
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
                                    <span className="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold tabular-nums text-white">
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
                            className="rounded-md border border-gray-200 p-4"
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
                    className="mt-5 inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700"
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
                                    className="flex items-center justify-between gap-4 rounded-md border border-gray-200 px-4 py-3"
                                >
                                    <div className="flex min-w-0 items-center gap-3">
                                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-900 text-sm font-semibold text-white">
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
                                className="rounded-md border border-gray-200 px-4 py-3"
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

            <StudentPanel title="Отчеты">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {data.reports.map((report) => (
                        <div
                            key={report.type}
                            className="flex flex-col rounded-md border border-gray-200 p-4"
                        >
                            <h4 className="text-base font-semibold text-gray-900">
                                {report.title}
                            </h4>
                            <p className="mt-3 flex-1 text-sm text-gray-600">
                                {report.description}
                            </p>
                            <a
                                href={report.exportUrl}
                                className="mt-4 inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700"
                            >
                                Excel
                            </a>
                        </div>
                    ))}
                </div>
            </StudentPanel>
        </div>
    );
}

export default function Dashboard({
    studentHome = null,
    curatorAdvisorHome = null,
    administrationHome = null,
}) {
    const {
        canViewPsychologicalProfile,
        canViewGroupSocialPassport,
        canViewAnalyticsDashboard,
    } = usePage().props.auth;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Панель
                </h2>
            }
        >
            <Head title="Панель" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {studentHome && <StudentHome data={studentHome} />}
                    {curatorAdvisorHome && (
                        <CuratorAdvisorHome data={curatorAdvisorHome} />
                    )}
                    {administrationHome && (
                        <AdministrationHome data={administrationHome} />
                    )}

                    <div className="space-y-4">
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="flex flex-col gap-4 p-6 text-gray-900 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 className="text-base font-semibold">
                                        Портрет студента
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-600">
                                        Карточка, академический профиль,
                                        достижения и портфолио.
                                    </p>
                                </div>
                                <Link
                                    href={route('student-profile.edit')}
                                    className="inline-flex items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                                >
                                    Открыть
                                </Link>
                            </div>
                        </div>
                        {canViewPsychologicalProfile && (
                            <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                                <div className="flex flex-col gap-4 p-6 text-gray-900 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h3 className="text-base font-semibold">
                                            Психолого-педагогический профиль
                                        </h3>
                                        <p className="mt-1 text-sm text-gray-600">
                                            Результаты психотестов и
                                            индивидуальные особенности студента.
                                        </p>
                                    </div>
                                    <Link
                                        href={route(
                                            'psychological-profile.index',
                                        )}
                                        className="inline-flex items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                                    >
                                        Открыть
                                    </Link>
                                </div>
                            </div>
                        )}
                        {canViewGroupSocialPassport && (
                            <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                                <div className="flex flex-col gap-4 p-6 text-gray-900 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h3 className="text-base font-semibold">
                                            Социальный паспорт группы
                                        </h3>
                                        <p className="mt-1 text-sm text-gray-600">
                                            Сведения о группе, студентах и
                                            количественный социальный статус.
                                        </p>
                                    </div>
                                    <Link
                                        href={route(
                                            'group-social-passport.edit',
                                        )}
                                        className="inline-flex items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                                    >
                                        Открыть
                                    </Link>
                                </div>
                            </div>
                        )}
                        {canViewAnalyticsDashboard && (
                            <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                                <div className="flex flex-col gap-4 p-6 text-gray-900 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h3 className="text-base font-semibold">
                                            Аналитическая панель
                                        </h3>
                                        <p className="mt-1 text-sm text-gray-600">
                                            Количество студентов, GPA, риски,
                                            вовлеченность и топ студентов.
                                        </p>
                                    </div>
                                    <Link
                                        href={route(
                                            'analytics-dashboard.index',
                                        )}
                                        className="inline-flex items-center justify-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                                    >
                                        Открыть
                                    </Link>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
