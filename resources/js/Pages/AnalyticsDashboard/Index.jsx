import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

function formatValue(value, fallback = '0') {
    return value === null || value === undefined ? fallback : value;
}

function MetricCard({ label, value, hint }) {
    return (
        <div className="rounded-md border border-gray-200 bg-white p-5 shadow-sm">
            <p className="text-sm font-medium text-gray-500">{label}</p>
            <p className="mt-2 text-3xl font-semibold text-gray-900">
                {value}
            </p>
            {hint && <p className="mt-2 text-sm text-gray-600">{hint}</p>}
        </div>
    );
}

function BarList({ items }) {
    const max = Math.max(...items.map((item) => item.count), 1);

    return (
        <div className="space-y-4">
            {items.map((item) => {
                const width = Math.round((item.count / max) * 100);

                return (
                    <div key={item.label}>
                        <div className="mb-1 flex items-center justify-between gap-4 text-sm">
                            <span className="font-medium text-gray-700">
                                {item.label}
                            </span>
                            <span className="tabular-nums text-gray-600">
                                {item.count}
                            </span>
                        </div>
                        <div className="h-2 rounded bg-gray-100">
                            <div
                                className="h-2 rounded bg-gray-800"
                                style={{ width: `${width}%` }}
                            />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function LineChart({ items }) {
    const values = items.map((item) => item.value ?? 0);
    const max = Math.max(...values, 1);
    const points = values
        .map((value, index) => {
            const x =
                items.length === 1 ? 50 : (index / (items.length - 1)) * 100;
            const y = 100 - (value / max) * 80 - 10;

            return `${x},${y}`;
        })
        .join(' ');

    return (
        <div>
            <svg
                viewBox="0 0 100 110"
                className="h-56 w-full"
                role="img"
                aria-label="График динамики развития"
            >
                {[20, 40, 60, 80, 100].map((y) => (
                    <line
                        key={y}
                        x1="0"
                        x2="100"
                        y1={y}
                        y2={y}
                        stroke="#e5e7eb"
                        strokeWidth="0.5"
                    />
                ))}
                <polyline
                    points={points}
                    fill="none"
                    stroke="#111827"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
                {values.map((value, index) => {
                    const x =
                        items.length === 1
                            ? 50
                            : (index / (items.length - 1)) * 100;
                    const y = 100 - (value / max) * 80 - 10;

                    return (
                        <g key={items[index].label}>
                            <circle cx={x} cy={y} r="2.2" fill="#111827" />
                            <text
                                x={x}
                                y="108"
                                textAnchor="middle"
                                className="fill-gray-500 text-[4px]"
                            >
                                {index + 1}
                            </text>
                        </g>
                    );
                })}
            </svg>
            <div className="mt-3 grid gap-2 text-sm text-gray-600 sm:grid-cols-2">
                {items.map((item, index) => (
                    <div key={item.label} className="flex gap-2">
                        <span className="font-medium text-gray-900">
                            {index + 1}.
                        </span>
                        <span>
                            {item.label}: {formatValue(item.value)}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function DonutChart({ value, label }) {
    const normalized = Math.max(0, Math.min(100, value ?? 0));
    const circumference = 2 * Math.PI * 42;
    const offset = circumference - (normalized / 100) * circumference;

    return (
        <div className="flex items-center gap-5">
            <svg
                viewBox="0 0 100 100"
                className="h-36 w-36 shrink-0"
                role="img"
                aria-label={label}
            >
                <circle
                    cx="50"
                    cy="50"
                    r="42"
                    fill="none"
                    stroke="#e5e7eb"
                    strokeWidth="10"
                />
                <circle
                    cx="50"
                    cy="50"
                    r="42"
                    fill="none"
                    stroke="#111827"
                    strokeWidth="10"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    strokeLinecap="round"
                    transform="rotate(-90 50 50)"
                />
                <text
                    x="50"
                    y="54"
                    textAnchor="middle"
                    className="fill-gray-900 text-xl font-semibold"
                >
                    {normalized}%
                </text>
            </svg>
            <div>
                <p className="text-sm font-medium text-gray-500">{label}</p>
                <p className="mt-1 text-sm text-gray-600">
                    Доля студентов с достижениями или файлами портфолио.
                </p>
            </div>
        </div>
    );
}

function HeatMap({ items }) {
    const max = Math.max(...items.map((item) => item.count), 1);

    return (
        <div className="grid gap-3 sm:grid-cols-3">
            {items.map((item) => {
                const intensity = item.count / max;
                const shade =
                    intensity >= 0.75
                        ? 'bg-gray-900 text-white'
                        : intensity >= 0.5
                          ? 'bg-gray-700 text-white'
                          : intensity > 0
                            ? 'bg-gray-300 text-gray-900'
                            : 'bg-gray-100 text-gray-600';

                return (
                    <div
                        key={item.label}
                        className={`rounded-md p-4 ${shade}`}
                    >
                        <p className="text-sm font-medium">{item.label}</p>
                        <p className="mt-2 text-2xl font-semibold tabular-nums">
                            {item.count}
                        </p>
                    </div>
                );
            })}
        </div>
    );
}

function RankingList({ students }) {
    if (students.length === 0) {
        return <p className="text-sm text-gray-500">Нет данных по GPA.</p>;
    }

    return (
        <div className="space-y-3">
            {students.map((student, index) => (
                <div
                    key={`${student.name}-${index}`}
                    className="flex items-center justify-between gap-4 rounded-md border border-gray-200 px-4 py-3"
                >
                    <div className="flex min-w-0 items-center gap-3">
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-900 text-sm font-semibold text-white">
                            {index + 1}
                        </div>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium text-gray-900">
                                {student.name}
                            </p>
                            <p className="text-sm text-gray-500">
                                {student.group}
                            </p>
                        </div>
                    </div>
                    <div className="text-right text-sm">
                        <p className="font-semibold tabular-nums text-gray-900">
                            GPA {formatValue(student.gpa, 'нет')}
                        </p>
                        <p className="text-gray-500">
                            Достижений: {student.achievements}
                        </p>
                    </div>
                </div>
            ))}
        </div>
    );
}

function RecommendationGrid({ items }) {
    return (
        <div className="grid gap-4 xl:grid-cols-2">
            {items.map((item) => (
                <div
                    key={item.type}
                    className="overflow-hidden rounded-md border border-gray-200"
                >
                    <div className="flex items-start justify-between gap-4 border-b border-[#dbe5f6] bg-[#edf3ff] px-4 py-3">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-[#426aa8]">
                                {item.type}
                            </p>
                            <h4 className="mt-1 text-sm font-semibold text-[#274f93]">
                                {item.title}
                            </h4>
                        </div>
                        <div className="rounded-md bg-[#355da8] px-3 py-2 text-center text-white">
                            <p className="text-lg font-semibold tabular-nums">
                                {item.target}
                            </p>
                            <p className="text-[11px] uppercase tracking-wide">
                                студентов
                            </p>
                        </div>
                    </div>
                    <div className="p-4">
                        <p className="text-sm text-gray-600">{item.basis}</p>
                        <p className="mt-3 text-sm text-gray-800">
                            {item.action}
                        </p>
                    </div>
                </div>
            ))}
        </div>
    );
}

function NotificationChannels({ channels }) {
    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            {channels.map((channel) => (
                <div
                    key={channel.name}
                    className="overflow-hidden rounded-md border border-gray-200"
                >
                    <div className="flex items-center justify-between gap-3 border-b border-[#dbe5f6] bg-[#edf3ff] px-4 py-3">
                        <h4 className="text-base font-semibold text-[#274f93]">
                            {channel.name}
                        </h4>
                        <span className="rounded-full bg-white px-3 py-1 text-xs font-medium text-[#355da8] ring-1 ring-[#dbe5f6]">
                            Канал
                        </span>
                    </div>
                    <div className="p-4">
                        <p className="text-sm text-gray-600">
                            {channel.description}
                        </p>
                        <p className="mt-3 text-sm font-medium text-gray-800">
                            {channel.status}
                        </p>
                    </div>
                </div>
            ))}
        </div>
    );
}

function NotificationEvents({ events }) {
    return (
        <div className="grid gap-4 lg:grid-cols-3">
            {events.map((event) => (
                <div
                    key={event.name}
                    className="overflow-hidden rounded-md border border-gray-200"
                >
                    <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-4 py-3">
                        <p className="text-xs font-semibold uppercase tracking-wide text-[#426aa8]">
                            Событие
                        </p>
                        <h4 className="mt-1 text-base font-semibold text-[#274f93]">
                            {event.name}
                        </h4>
                    </div>
                    <div className="p-4">
                        <p className="text-sm text-gray-600">
                            {event.description}
                        </p>
                        <p className="mt-3 text-sm font-medium text-gray-800">
                            Получатели: {event.audience}
                        </p>
                    </div>
                </div>
            ))}
        </div>
    );
}

function ReportGrid({ reports }) {
    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            {reports.map((report) => (
                <div
                    key={report.type}
                    className="flex flex-col overflow-hidden rounded-md border border-gray-200"
                >
                    <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-4 py-3">
                        <p className="text-xs font-semibold uppercase tracking-wide text-[#426aa8]">
                            Отчет
                        </p>
                        <h4 className="mt-1 text-base font-semibold text-[#274f93]">
                            {report.title}
                        </h4>
                    </div>
                    <div className="flex flex-1 flex-col p-4">
                        <p className="flex-1 text-sm text-gray-600">
                            {report.description}
                        </p>
                        <a
                            href={report.exportUrl}
                            className="mt-4 inline-flex items-center justify-center rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#2f5192] focus:outline-none focus:ring-2 focus:ring-[#355da8] focus:ring-offset-2"
                        >
                            Excel
                        </a>
                    </div>
                </div>
            ))}
        </div>
    );
}

function IntegrationGrid({ integrations }) {
    return (
        <div className="grid gap-4 lg:grid-cols-2">
            {integrations.map((integration) => (
                <div
                    key={integration.name}
                    className="overflow-hidden rounded-md border border-gray-200"
                >
                    <div className="flex items-start justify-between gap-4 border-b border-[#dbe5f6] bg-[#edf3ff] px-4 py-3">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-[#426aa8]">
                                Интеграция
                            </p>
                            <h4 className="mt-1 text-base font-semibold text-[#274f93]">
                                {integration.name}
                            </h4>
                        </div>
                        <span className="rounded-full bg-white px-3 py-1 text-xs font-medium text-[#355da8] ring-1 ring-[#dbe5f6]">
                            {integration.status}
                        </span>
                    </div>
                    <div className="p-4">
                        <p className="text-sm text-gray-600">
                            {integration.description}
                        </p>
                        <p className="mt-3 text-sm font-medium text-gray-800">
                            Данные: {integration.data}
                        </p>
                    </div>
                </div>
            ))}
        </div>
    );
}

function Panel({ title, children }) {
    return (
        <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
            <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4">
                <h3 className="text-base font-semibold text-[#274f93]">
                    {title}
                </h3>
            </div>
            <div className="p-5">{children}</div>
        </section>
    );
}

export default function Index({
    metrics,
    riskGroups,
    topStudents,
    developmentDynamics,
    recommendations,
    notificationChannels,
    notificationEvents,
    reports,
    integrations,
}) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Аналитическая панель
                </h2>
            }
        >
            <Head title="Аналитическая панель" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <MetricCard
                            label="Количество студентов"
                            value={metrics.studentCount}
                        />
                        <MetricCard
                            label="Средний GPA"
                            value={formatValue(metrics.averageGpa, 'Нет данных')}
                        />
                        <MetricCard
                            label="Группы риска"
                            value={metrics.riskGroupCount}
                            hint="Уникальные студенты с факторами риска"
                        />
                        <MetricCard
                            label="Уровень вовлеченности"
                            value={`${metrics.engagementLevel}%`}
                            hint="Студенты с достижениями или портфолио"
                        />
                    </div>

                    <div className="grid gap-6 xl:grid-cols-2">
                        <Panel title="Диаграмма вовлеченности">
                            <DonutChart
                                value={metrics.engagementLevel}
                                label="Уровень вовлеченности"
                            />
                        </Panel>

                        <Panel title="График динамики развития">
                            <LineChart items={developmentDynamics} />
                        </Panel>

                        <Panel title="Группы риска">
                            <BarList items={riskGroups} />
                        </Panel>

                        <Panel title="Тепловая карта рисков">
                            <HeatMap items={riskGroups} />
                        </Panel>
                    </div>

                    <Panel title="Рейтинг студентов">
                        <RankingList students={topStudents} />
                    </Panel>

                    <Panel title="Рекомендательная система">
                        <RecommendationGrid items={recommendations} />
                    </Panel>

                    <Panel title="Уведомления">
                        <NotificationChannels channels={notificationChannels} />
                    </Panel>

                    <Panel title="События уведомлений">
                        <NotificationEvents events={notificationEvents} />
                    </Panel>

                    <Panel title="Отчетность">
                        <ReportGrid reports={reports} />
                    </Panel>

                    <Panel title="Интеграции">
                        <IntegrationGrid integrations={integrations} />
                    </Panel>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
