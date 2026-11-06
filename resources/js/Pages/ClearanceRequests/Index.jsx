import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { useState } from "react";

const statusLabels = {
    pending: "Ожидает",
    approved: "Разрешено",
    rejected: "Отказано",
};

const statusClasses = {
    pending: "bg-amber-50 text-amber-800 ring-amber-200",
    approved: "bg-emerald-50 text-emerald-800 ring-emerald-200",
    rejected: "bg-red-50 text-red-800 ring-red-200",
};

function SectionCard({ title, count, children }) {
    return (
        <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200/80">
            <div className="flex items-center justify-between gap-3 border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4 sm:px-6">
                <h3 className="text-base font-semibold text-[#274f93]">{title}</h3>
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
        <span className={`inline-flex shrink-0 rounded px-2 py-1 text-xs font-semibold ring-1 ${statusClasses[status] || statusClasses.pending}`}>
            {statusLabels[status] || statusLabels.pending}
        </span>
    );
}

function formatDate(value, locale) {
    if (!value) return "";

    return new Intl.DateTimeFormat(locale === "kk" ? "kk-KZ" : "ru-RU", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(value));
}

function Pagination({ pagination }) {
    if (!pagination || pagination.last_page <= 1) return null;

    return (
        <nav aria-label="Страницы заявок" className="flex flex-wrap gap-2 border-t border-gray-200 px-5 py-4 sm:px-6">
            {pagination.links.map((link, index) => {
                const label = index === 0
                    ? "Назад"
                    : index === pagination.links.length - 1
                      ? "Далее"
                      : link.label;

                return link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        preserveState
                        aria-current={link.active ? "page" : undefined}
                        className={`inline-flex min-h-9 min-w-9 items-center justify-center rounded border px-3 text-sm ${link.active ? "border-[#355da8] bg-[#355da8] text-white" : "border-gray-300 bg-white text-gray-700 hover:bg-[#edf3ff]"}`}
                    >
                        {label}
                    </Link>
                ) : (
                    <span key={index} className="inline-flex min-h-9 items-center rounded border border-gray-200 px-3 text-sm text-gray-400">
                        {label}
                    </span>
                );
            })}
        </nav>
    );
}

function ResponseForm({ response }) {
    const form = useForm({ decision: "approved", comment: "" });

    const submit = (event) => {
        event.preventDefault();
        form.post(route("clearance-responses.respond", response.id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="mt-3 space-y-3 border-t border-gray-100 pt-3">
            <div className="inline-flex rounded-md border border-gray-300 p-0.5" role="group" aria-label="Решение">
                {[
                    ["approved", "Разрешить"],
                    ["rejected", "Отказать"],
                ].map(([value, label]) => (
                    <button
                        key={value}
                        type="button"
                        onClick={() => form.setData("decision", value)}
                        aria-pressed={form.data.decision === value}
                        className={`rounded-md px-3 py-1.5 text-sm font-medium ${form.data.decision === value ? "bg-[#355da8] text-white" : "text-gray-700 hover:bg-gray-50"}`}
                    >
                        {label}
                    </button>
                ))}
            </div>
            {form.errors.decision && <p className="text-sm text-red-700">{form.errors.decision}</p>}
            <label className="block text-sm font-medium text-gray-800">
                Комментарий
                <textarea
                    value={form.data.comment}
                    onChange={(event) => form.setData("comment", event.target.value)}
                    required
                    maxLength={2000}
                    rows={3}
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
                />
            </label>
            {form.errors.comment && <p className="text-sm text-red-700">{form.errors.comment}</p>}
            <button type="submit" disabled={form.processing} className="rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2f5192] disabled:opacity-50">
                Отправить решение
            </button>
        </form>
    );
}

function RequestList({ title, pagination, canRespond, locale, emptyMessage }) {
    return (
        <SectionCard title={title} count={pagination?.total || 0}>
            {!pagination?.data?.length && <p className="px-5 py-6 text-sm text-gray-500 sm:px-6">{emptyMessage}</p>}
            <div className="divide-y divide-gray-100">
                {pagination?.data?.map((request) => (
                    <article key={request.id} className="px-5 py-5 sm:px-6">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h4 className="font-semibold text-[#274f93]">{request.student_name}</h4>
                                <p className="mt-1 text-xs text-gray-600">
                                    ИИН: {request.student_iin || "Не указано"} · {formatDate(request.created_at, locale)}
                                </p>
                            </div>
                            <StatusBadge status={request.status} />
                        </div>
                        <div className="mt-3">
                            <p className="text-sm text-gray-600">
                                {request.student_faculty || "Факультет не указан"}
                                {request.student_group ? ` · ${request.student_group}` : ""}
                                {request.creator_name ? ` · Отправил: ${request.creator_name}` : ""}
                            </p>
                            <div className="mt-4 divide-y divide-gray-100 border-t border-gray-100">
                                {request.responses.map((response) => (
                                    <div key={response.id} className="py-3 last:pb-0">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium text-gray-900">{response.reviewer_name}</p>
                                                <p className="text-xs text-gray-500">{response.reviewer_position || "Сотрудник"}</p>
                                            </div>
                                            <StatusBadge status={response.decision} />
                                        </div>
                                        {response.comment && <p className="mt-2 whitespace-pre-wrap text-sm text-gray-700">{response.comment}</p>}
                                        {response.responded_at && (
                                            <p className="mt-1 text-xs text-gray-500">{formatDate(response.responded_at, locale)}</p>
                                        )}
                                        {canRespond && response.is_mine && response.decision === "pending" && (
                                            <ResponseForm response={response} />
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </article>
                ))}
            </div>
            <Pagination pagination={pagination} />
        </SectionCard>
    );
}

function CreateRequest({ recipientOptions, csrfToken }) {
    const [iin, setIin] = useState("");
    const [student, setStudent] = useState(null);
    const [lookupError, setLookupError] = useState("");
    const [lookupProcessing, setLookupProcessing] = useState(false);
    const [recipientSearch, setRecipientSearch] = useState("");
    const form = useForm({ iin: "", student_profile_id: "", reviewer_ids: [] });
    const recipients = recipientOptions.filter((recipient) =>
        `${recipient.name} ${recipient.position || ""} ${recipient.email || ""}`
            .toLocaleLowerCase()
            .includes(recipientSearch.toLocaleLowerCase()),
    );

    const lookup = async (event) => {
        event.preventDefault();
        setLookupProcessing(true);
        setLookupError("");
        setStudent(null);
        form.setData({ iin: "", student_profile_id: "", reviewer_ids: [] });

        try {
            const response = await fetch(route("clearance-requests.lookup"), {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                },
                body: JSON.stringify({ iin }),
            });
            const result = await response.json();

            if (!response.ok) {
                setLookupError(result.errors?.iin?.[0] || result.message || "Не удалось найти студента.");
                return;
            }

            setStudent(result.student);
            form.setData({ iin: result.student.iin, student_profile_id: result.student.id, reviewer_ids: [] });
        } catch {
            setLookupError("Не удалось найти студента.");
        } finally {
            setLookupProcessing(false);
        }
    };

    const toggleReviewer = (id) => {
        form.setData("reviewer_ids", form.data.reviewer_ids.includes(id)
            ? form.data.reviewer_ids.filter((selected) => selected !== id)
            : [...form.data.reviewer_ids, id]);
    };

    const submit = (event) => {
        event.preventDefault();
        form.post(route("clearance-requests.store"), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setStudent(null);
                setIin("");
                setRecipientSearch("");
            },
        });
    };

    return (
        <SectionCard title="Новая заявка">
            <div className="space-y-5 p-5 sm:p-6">
                <form onSubmit={lookup} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <label className="block w-full max-w-sm text-sm font-medium text-gray-800">
                        ИИН студента
                        <input
                            value={iin}
                            onChange={(event) => {
                                setIin(event.target.value.replace(/\D/g, "").slice(0, 12));
                                setStudent(null);
                            }}
                            required
                            inputMode="numeric"
                            pattern="[0-9]{12}"
                            maxLength={12}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
                        />
                    </label>
                    <button type="submit" disabled={lookupProcessing} className="rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2f5192] disabled:opacity-50">
                        Найти
                    </button>
                </form>
                {lookupError && <p role="alert" className="text-sm text-red-700">{lookupError}</p>}
                {form.errors.iin && <p role="alert" className="text-sm text-red-700">{form.errors.iin}</p>}
                {student && (
                    <form onSubmit={submit} className="space-y-5">
                        <div className="rounded-md border border-[#dbe5f6] bg-[#f4f7fc] px-4 py-3">
                            <p className="font-semibold text-[#274f93]">{student.name}</p>
                            <p className="mt-1 text-sm text-gray-700">
                                {student.faculty || "Факультет не указан"} · {student.group || "Группа не указана"}
                            </p>
                        </div>
                        <div>
                            <label className="block text-sm font-semibold text-gray-800">
                                Получатели
                                <input
                                    value={recipientSearch}
                                    onChange={(event) => setRecipientSearch(event.target.value)}
                                    placeholder="Поиск сотрудника"
                                    className="mt-2 block w-full max-w-md rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
                                />
                            </label>
                            <div className="mt-3 max-h-64 overflow-y-auto rounded-md border border-gray-200 bg-white">
                                {recipients.map((recipient) => (
                                    <label key={recipient.id} className="flex cursor-pointer items-start gap-3 border-b border-gray-100 px-4 py-3 last:border-0 hover:bg-[#f7f9fd]">
                                        <input
                                            type="checkbox"
                                            checked={form.data.reviewer_ids.includes(recipient.id)}
                                            onChange={() => toggleReviewer(recipient.id)}
                                            className="mt-0.5 rounded border-gray-300 text-[#355da8] focus:ring-[#355da8]"
                                        />
                                        <span className="min-w-0">
                                            <span className="block text-sm font-medium text-gray-900">{recipient.name}</span>
                                            <span className="block text-xs text-gray-600">{recipient.position || "Сотрудник"} · {recipient.email}</span>
                                        </span>
                                    </label>
                                ))}
                                {!recipients.length && <p className="px-4 py-3 text-sm text-gray-500">Сотрудники не найдены.</p>}
                            </div>
                            {form.errors.reviewer_ids && <p role="alert" className="mt-2 text-sm text-red-700">{form.errors.reviewer_ids}</p>}
                        </div>
                        <button type="submit" disabled={form.processing || !form.data.reviewer_ids.length} className="rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2f5192] disabled:opacity-50">
                            Отправить заявку
                        </button>
                    </form>
                )}
            </div>
        </SectionCard>
    );
}

export default function Index({
    canCreate = false,
    canReview = false,
    recipientOptions = [],
    sentRequests = null,
    assignedRequests = null,
    studentRequests = null,
}) {
    const { csrfToken, locale, flash } = usePage().props;

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Обходной лист</h2>}>
            <Head title="Обходной лист" />
            <div className="bg-[#f4f7fc] py-8">
                <div className="w-full space-y-6 px-4 sm:px-6 lg:px-8">
                    {flash?.status === "clearance-request-created" && (
                        <p role="status" className="border-l-4 border-emerald-500 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">Заявка отправлена.</p>
                    )}
                    {flash?.status === "clearance-response-saved" && (
                        <p role="status" className="border-l-4 border-emerald-500 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">Решение сохранено.</p>
                    )}
                    {canCreate && <CreateRequest recipientOptions={recipientOptions} csrfToken={csrfToken} />}
                    {canReview && (
                        <RequestList
                            title="Входящие заявки"
                            pagination={assignedRequests}
                            canRespond
                            locale={locale}
                            emptyMessage="Входящих заявок нет."
                        />
                    )}
                    {canCreate && (
                        <RequestList
                            title="Отправленные заявки"
                            pagination={sentRequests}
                            locale={locale}
                            emptyMessage="Отправленных заявок нет."
                        />
                    )}
                    {!canReview && (
                        <RequestList
                            title="Мои заявки"
                            pagination={studentRequests}
                            locale={locale}
                            emptyMessage="Заявок пока нет."
                        />
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
