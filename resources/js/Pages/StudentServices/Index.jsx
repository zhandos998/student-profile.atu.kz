import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";

const services = [
    { type: "id_card", title: "ID-карта" },
    { type: "face_id", title: "Face ID" },
];

const statusLabels = {
    pending: "Ожидает",
    in_progress: "В работе",
    completed: "Выполнено",
    rejected: "Отказано",
};

const statusClasses = {
    pending: "bg-amber-50 text-amber-800 ring-amber-200",
    in_progress: "bg-[#edf3ff] text-[#274f93] ring-[#c9d8f0]",
    completed: "bg-emerald-50 text-emerald-800 ring-emerald-200",
    rejected: "bg-red-50 text-red-800 ring-red-200",
};

function Section({ title, count, children }) {
    return (
        <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200/80">
            <div className="flex items-center justify-between gap-3 border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4 sm:px-6">
                <h3 className="text-base font-semibold text-[#274f93]">
                    {title}
                </h3>
                {count !== undefined && (
                    <span className="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-[#355da8] ring-1 ring-[#c9d8f0]">
                        {count}
                    </span>
                )}
            </div>
            {children}
        </section>
    );
}

function StatusBadge({ status }) {
    return (
        <span
            className={`inline-flex shrink-0 rounded px-2 py-1 text-xs font-semibold ring-1 ${statusClasses[status] || statusClasses.pending}`}
        >
            {statusLabels[status] || statusLabels.pending}
        </span>
    );
}

function formatDate(value, locale) {
    if (!value) return "";

    return new Intl.DateTimeFormat(
        locale === "kk" ? "kk-KZ" : locale === "en" ? "en-US" : "ru-RU",
        {
            dateStyle: "medium",
            timeStyle: "short",
        },
    ).format(new Date(value));
}

function ServiceOption({ service, hasOpenRequest }) {
    return (
        <div className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200/80 sm:p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h3 className="text-base font-semibold text-[#274f93]">
                    {service.title}
                </h3>
                {hasOpenRequest ? (
                    <span className="text-sm font-medium text-gray-600">
                        Заявка уже подана
                    </span>
                ) : (
                    <Link
                        href={route(
                            service.type === "id_card"
                                ? "student-services.id-card.create"
                                : "student-services.face-id.create",
                        )}
                        className="rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2f5192]"
                    >
                        Подать заявку
                    </Link>
                )}
            </div>
        </div>
    );
}

function RequestList({ requests, canManage, locale }) {
    const title = canManage ? "Заявки студентов" : "Мои заявки";

    return (
        <Section title={title} count={requests.total}>
            {!requests.data.length ? (
                <p className="px-5 py-6 text-sm text-gray-500 sm:px-6">
                    Заявок пока нет.
                </p>
            ) : (
                <div className="divide-y divide-gray-100">
                    {requests.data.map((request) => (
                        <div key={request.id} className="px-5 py-4 sm:px-6">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-semibold text-[#274f93]">
                                        {
                                            services.find(
                                                (service) =>
                                                    service.type ===
                                                    request.service_type,
                                            )?.title
                                        }
                                    </p>
                                    {canManage && (
                                        <p className="mt-1 break-words text-sm text-gray-900">
                                            {request.student_name}
                                            {request.student_iin
                                                ? ` · ИИН: ${request.student_iin}`
                                                : ""}
                                        </p>
                                    )}
                                    <p className="mt-1 text-xs text-gray-500">
                                        {formatDate(request.created_at, locale)}
                                    </p>
                                </div>
                                <StatusBadge status={request.status} />
                            </div>
                            {request.student_comment && (
                                <p className="mt-3 whitespace-pre-wrap break-words text-sm text-gray-700">
                                    {request.student_comment}
                                </p>
                            )}
                            {request.staff_comment && (
                                <p className="mt-3 whitespace-pre-wrap break-words border-l-2 border-[#c9d8f0] pl-3 text-sm text-gray-700">
                                    {request.staff_comment}
                                </p>
                            )}
                            {request.handled_at && (
                                <p className="mt-2 text-xs text-gray-500">
                                    {request.handled_by} ·{" "}
                                    {formatDate(request.handled_at, locale)}
                                </p>
                            )}
                            <Link
                                href={route(
                                    "student-services.show",
                                    request.id,
                                )}
                                className="mt-3 inline-block text-sm font-semibold text-[#355da8] hover:underline"
                            >
                                Подробнее
                            </Link>
                        </div>
                    ))}
                </div>
            )}
            {requests.last_page > 1 && (
                <nav
                    aria-label="Страницы заявок"
                    className="flex flex-wrap gap-2 border-t border-gray-200 px-5 py-4 sm:px-6"
                >
                    {requests.links.map((link, index) => {
                        const label =
                            index === 0
                                ? "Назад"
                                : index === requests.links.length - 1
                                  ? "Далее"
                                  : link.label;
                        return link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                preserveScroll
                                className={`rounded-md border px-3 py-2 text-sm ${link.active ? "border-[#355da8] bg-[#355da8] text-white" : "border-gray-200 text-gray-700 hover:bg-[#edf3ff]"}`}
                            >
                                {label}
                            </Link>
                        ) : (
                            <span
                                key={index}
                                className="rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-400"
                            >
                                {label}
                            </span>
                        );
                    })}
                </nav>
            )}
        </Section>
    );
}

function RequestFilters({ filters }) {
    const [values, setValues] = useState({
        service_type: filters.service_type || "",
        status: filters.status || "",
    });

    const submit = (event) => {
        event.preventDefault();
        router.get(route("student-services.index"), values, {
            preserveScroll: true,
        });
    };

    return (
        <Section title="Фильтр заявок">
            <form
                onSubmit={submit}
                className="flex flex-wrap items-end gap-3 p-5 sm:p-6"
            >
                <label className="min-w-44 flex-1 text-sm font-medium text-gray-800">
                    Услуга
                    <select
                        value={values.service_type}
                        onChange={(event) =>
                            setValues({
                                ...values,
                                service_type: event.target.value,
                            })
                        }
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
                    >
                        <option value="">Все</option>
                        {services.map((service) => (
                            <option key={service.type} value={service.type}>
                                {service.title}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="min-w-44 flex-1 text-sm font-medium text-gray-800">
                    Статус
                    <select
                        value={values.status}
                        onChange={(event) =>
                            setValues({ ...values, status: event.target.value })
                        }
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
                    >
                        <option value="">Все</option>
                        {Object.entries(statusLabels).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                </label>
                <button
                    type="submit"
                    className="rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white hover:bg-[#2f5192]"
                >
                    Фильтр
                </button>
                <Link
                    href={route("student-services.index")}
                    className="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                >
                    Сбросить
                </Link>
            </form>
        </Section>
    );
}

export default function Index({
    canSubmit,
    canManage,
    requests,
    filters = {},
    activeTypes = [],
}) {
    const { flash, locale } = usePage().props;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800">Услуги</h2>
            }
        >
            <Head title="Услуги" />
            <div className="bg-[#f4f7fc] py-8">
                <div className="mx-auto w-full max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {flash?.status === "student-service-request-created" && (
                        <p
                            role="status"
                            className="border-l-4 border-emerald-500 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
                        >
                            Заявка отправлена.
                        </p>
                    )}
                    {flash?.status === "face-id-synced" && (
                        <p
                            role="status"
                            className="border-l-4 border-emerald-500 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
                        >
                            Face ID передан в Hikvision.
                        </p>
                    )}
                    {flash?.status === "face-id-pending" && (
                        <p role="status" className="border-l-4 border-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            Заявка Face ID сохранена и ожидает обработки. Автоматическая отправка в Hikvision не удалась.
                        </p>
                    )}
                    {flash?.status === "student-service-request-updated" && (
                        <p
                            role="status"
                            className="border-l-4 border-emerald-500 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
                        >
                            Статус обновлён.
                        </p>
                    )}
                    {canSubmit && (
                        <section className="space-y-3">
                            <h3 className="text-base font-semibold text-[#274f93]">
                                Доступные услуги
                            </h3>
                            <div className="grid gap-4 md:grid-cols-2">
                                {services.map((service) => (
                                    <ServiceOption
                                        key={service.type}
                                        service={service}
                                        hasOpenRequest={activeTypes.includes(
                                            service.type,
                                        )}
                                    />
                                ))}
                            </div>
                        </section>
                    )}
                    {canManage && <RequestFilters filters={filters} />}
                    <RequestList
                        requests={requests}
                        canManage={canManage}
                        locale={locale}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
