import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link } from "@inertiajs/react";

export default function TestScores({
    snapshots,
    filters,
    tests,
    studentCount,
    testCount,
    indexUrl,
    exportUrl,
    xlsxUrl,
}) {
    const rows = snapshots.data.flatMap((snapshot) =>
        (snapshot.metrics.length ? snapshot.metrics : [null]).map(
            (metric, index) => ({
                ...snapshot,
                metric,
                rowKey: `${snapshot.id}-${index}`,
            }),
        ),
    );

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800">
                    Баллы по тестам
                </h2>
            }
        >
            <Head title="Баллы по тестам" />
            <div className="w-full space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                    <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4">
                        <h3 className="text-base font-semibold text-[#274f93]">
                            Фильтр результатов
                        </h3>
                    </div>
                    <form
                        action={indexUrl}
                        method="get"
                        className="flex flex-wrap items-end gap-4 p-5"
                    >
                        <label className="min-w-64 flex-1 text-sm font-medium text-gray-800">
                            Студент или ИИН
                            <input
                                name="q"
                                defaultValue={filters.q}
                                placeholder="ФИО или ИИН"
                                className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
                            />
                        </label>
                        <label className="min-w-56 flex-1 text-sm font-medium text-gray-800">
                            Тест
                            <select
                                name="test"
                                defaultValue={filters.test}
                                className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
                            >
                                <option value="">Все тесты</option>
                                {Object.entries(tests).map(([key, name]) => (
                                    <option key={key} value={key}>
                                        {name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <button
                            type="submit"
                            className="rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white hover:bg-[#2f5192]"
                        >
                            Показать
                        </button>
                        <a
                            href={indexUrl}
                            className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Сбросить
                        </a>
                        {/* <a href={exportUrl} className="rounded-md border border-[#355da8] px-4 py-2 text-sm font-semibold text-[#355da8] hover:bg-[#edf3ff]">Скачать CSV</a> */}
                        <a
                            href={xlsxUrl}
                            className="rounded-md border border-[#355da8] px-4 py-2 text-sm font-semibold text-[#355da8] hover:bg-[#edf3ff]"
                        >
                            Скачать XLSX
                        </a>
                    </form>
                </section>

                <section className="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4">
                        <h3 className="text-base font-semibold text-[#274f93]">
                            Результаты
                        </h3>
                        <div className="flex flex-wrap gap-4 text-sm text-[#274f93]">
                            <span><span>Результатов тестов:</span> <strong>{snapshots.total}</strong></span>
                            <span><span>Студентов:</span> <strong>{studentCount}</strong></span>
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[900px] divide-y divide-gray-200 text-left text-sm">
                            <thead className="bg-gray-50 text-xs font-semibold text-gray-600">
                                <tr>
                                    {[
                                        "Студент",
                                        "Группа",
                                        "Тест",
                                        "Шкала",
                                        "Балл",
                                        "Обновлено",
                                    ].map((title) => (
                                        <th key={title} className="px-4 py-3">
                                            {title}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {rows.map((row) => (
                                    <tr key={row.rowKey}>
                                        <td className="px-4 py-3 align-top">
                                            {row.student_url ? (
                                                <a
                                                    href={row.student_url}
                                                    className="font-medium text-[#355da8] hover:underline"
                                                >
                                                    {row.student_name ||
                                                        "Не указано"}
                                                </a>
                                            ) : (
                                                row.student_name || "Не указано"
                                            )}
                                            <div className="text-xs text-gray-500">
                                                {row.iin}
                                            </div>
                                            <div className="mt-1 text-xs text-[#355da8]">
                                                <span>Рассчитано тестов:</span> {row.completed_tests} <span>из</span> {testCount}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 align-top text-gray-600">
                                            {row.group || "—"}
                                        </td>
                                        <td className="px-4 py-3 align-top font-medium text-gray-800">
                                            {row.test}
                                        </td>
                                        <td className="px-4 py-3 align-top text-gray-700">
                                            {row.metric?.label || "—"}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 align-top font-semibold text-gray-900">
                                            {row.metric?.score ?? "—"}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 align-top text-gray-500">
                                            {row.updated_at || "—"}
                                        </td>
                                    </tr>
                                ))}
                                {rows.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-4 py-10 text-center text-gray-500"
                                        >
                                            Сохранённых результатов нет.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {snapshots.total > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-4 border-t border-gray-200 px-5 py-4 text-sm">
                            <span className="text-gray-600">
                                <span>Результаты</span> {snapshots.from}–{snapshots.to}{" "}
                                <span>из</span> {snapshots.total}
                            </span>
                            <nav aria-label="Пагинация" className="flex flex-wrap gap-1">
                                {snapshots.links.map((link, index) => {
                                    const first = index === 0;
                                    const last = index === snapshots.links.length - 1;
                                    const label = first ? '←' : last ? '→' : link.label;
                                    const className = `inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-2 text-sm font-medium ${link.active ? 'border-[#355da8] bg-[#355da8] text-white' : link.url ? 'border-gray-300 text-[#355da8] hover:bg-[#edf3ff]' : 'border-gray-200 text-gray-400'}`;
                                    return link.url ? (
                                        <Link key={index} href={link.url} preserveScroll className={className} aria-current={link.active ? 'page' : undefined} aria-label={first ? 'Назад' : last ? 'Далее' : undefined} title={first ? 'Назад' : last ? 'Далее' : undefined}>
                                            {label}
                                        </Link>
                                    ) : (
                                        <span key={index} className={className} aria-disabled="true">{label}</span>
                                    );
                                })}
                            </nav>
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
