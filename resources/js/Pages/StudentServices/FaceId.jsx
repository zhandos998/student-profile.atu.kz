import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, useForm } from "@inertiajs/react";
import { useEffect, useState } from "react";

const inputClass =
    "mt-1.5 block h-11 w-full rounded-md border-[#cbd5e1] bg-white px-3 text-sm text-gray-900 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]";

const suitablePhotoRules = [
    "Смотрите прямо в камеру, держите голову ровно.",
    "Лицо полностью открыто, оба глаза видны.",
    "Ровное освещение без теней и засветов.",
    "Светлый однотонный фон.",
    "Расстояние до камеры примерно 0,5–1 м.",
];

const unsuitablePhotoRules = [
    "Голова повёрнута в сторону.",
    "Фото слишком тёмное или с сильными тенями.",
    "Тёмные очки закрывают глаза.",
    "Маска закрывает лицо.",
    "Кепка или капюшон закрывают лоб.",
    "Лицо слишком далеко от камеры.",
    "Лицо слишком близко или обрезано.",
    "Сильная улыбка или открытый рот.",
    "Размытое фото.",
];

export default function FaceId({ initial, faculties = [], hasOpenRequest, integrationReady = true }) {
    const form = useForm({ ...initial, service_type: "face_id", photo: null });
    const [previewUrl, setPreviewUrl] = useState(null);

    useEffect(() => {
        if (!form.data.photo) {
            setPreviewUrl(null);
            return;
        }

        const url = URL.createObjectURL(form.data.photo);
        setPreviewUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [form.data.photo]);

    const submit = (event) => {
        event.preventDefault();

        if (form.data.photo?.size > 16 * 1024 * 1024) {
            form.setError("photo", "Фото должно быть не больше 16 МБ.");
            return;
        }

        form.post(route("student-services.store"), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800">
                    Заявка на Face ID
                </h2>
            }
        >
            <Head title="Заявка на Face ID" />
            <div className="bg-[#f4f7fc] px-4 py-8 sm:px-6 lg:px-8">
                <div className="mx-auto w-full max-w-7xl space-y-6">
                    <Link
                        href={route("student-services.index")}
                        className="text-sm font-medium text-[#355da8] hover:underline"
                    >
                        Назад к услугам
                    </Link>
                    {hasOpenRequest ? (
                        <section className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200/80">
                            <p className="font-medium text-gray-900">
                                Заявка на Face ID уже подана.
                            </p>
                            <p className="mt-2 text-sm text-gray-600">
                                Её статус можно посмотреть в разделе «Услуги».
                            </p>
                        </section>
                    ) : (
                        <form onSubmit={submit} className="space-y-6">
                            {!integrationReady && (
                                <p role="status" className="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">
                                    Автоматическая отправка в Hikvision недоступна. Заявка сохранится со статусом «Ожидает».
                                </p>
                            )}
                            {form.errors.service_type && (
                                <p role="alert" className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                                    {form.errors.service_type}
                                </p>
                            )}
                            <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200/80">
                                <h3 className="border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4 text-base font-semibold text-[#274f93]">
                                    Данные студента
                                </h3>
                                <div className="grid gap-x-5 gap-y-4 p-5 sm:grid-cols-2 sm:p-6">
                                    <label className="block text-sm font-medium text-gray-800">
                                        ИИН
                                        <input
                                            type="text"
                                            inputMode="numeric"
                                            maxLength={12}
                                            required
                                            value={form.data.iin ?? ""}
                                            onChange={(event) =>
                                                form.setData(
                                                    "iin",
                                                    event.target.value,
                                                )
                                            }
                                            className={inputClass}
                                        />
                                        {form.errors.iin && (
                                            <span className="mt-1 block text-xs text-red-700">
                                                {form.errors.iin}
                                            </span>
                                        )}
                                    </label>
                                    <label className="block text-sm font-medium text-gray-800">
                                        Фамилия
                                        <input
                                            type="text"
                                            maxLength={100}
                                            required
                                            value={form.data.last_name ?? ""}
                                            onChange={(event) =>
                                                form.setData(
                                                    "last_name",
                                                    event.target.value,
                                                )
                                            }
                                            className={inputClass}
                                        />
                                        {form.errors.last_name && (
                                            <span className="mt-1 block text-xs text-red-700">
                                                {form.errors.last_name}
                                            </span>
                                        )}
                                    </label>
                                    <label className="block text-sm font-medium text-gray-800">
                                        Имя
                                        <input
                                            type="text"
                                            maxLength={100}
                                            required
                                            value={form.data.first_name ?? ""}
                                            onChange={(event) =>
                                                form.setData(
                                                    "first_name",
                                                    event.target.value,
                                                )
                                            }
                                            className={inputClass}
                                        />
                                        {form.errors.first_name && (
                                            <span className="mt-1 block text-xs text-red-700">
                                                {form.errors.first_name}
                                            </span>
                                        )}
                                    </label>
                                    <label className="block text-sm font-medium text-gray-800">
                                        Отчество (необязательно)
                                        <input
                                            type="text"
                                            maxLength={50}
                                            value={form.data.middle_name ?? ""}
                                            onChange={(event) =>
                                                form.setData(
                                                    "middle_name",
                                                    event.target.value,
                                                )
                                            }
                                            className={inputClass}
                                        />
                                        {form.errors.middle_name && (
                                            <span className="mt-1 block text-xs text-red-700">
                                                {form.errors.middle_name}
                                            </span>
                                        )}
                                    </label>
                                </div>
                                <div className="grid gap-x-5 gap-y-4 border-t border-[#e5ebf4] bg-[#f8fbff] p-5 sm:grid-cols-2 sm:p-6">
                                    <label className="block text-sm font-medium text-gray-800 sm:col-span-2">
                                        Факультет
                                        <select
                                            required
                                            value={form.data.faculty ?? ""}
                                            onChange={(event) =>
                                                form.setData(
                                                    "faculty",
                                                    event.target.value,
                                                )
                                            }
                                            className={inputClass}
                                        >
                                            <option value="">
                                                Выберите факультет
                                            </option>
                                            {faculties.map((faculty) => (
                                                <option
                                                    key={faculty.value}
                                                    value={faculty.value}
                                                >
                                                    {faculty.label}
                                                </option>
                                            ))}
                                        </select>
                                        {form.errors.faculty && (
                                            <span className="mt-1 block text-xs text-red-700">
                                                {form.errors.faculty}
                                            </span>
                                        )}
                                    </label>
                                    <label className="block text-sm font-medium text-gray-800">
                                        Курс
                                        <select
                                            required
                                            value={form.data.course ?? ""}
                                            onChange={(event) =>
                                                form.setData(
                                                    "course",
                                                    event.target.value,
                                                )
                                            }
                                            className={inputClass}
                                        >
                                            <option value="">
                                                Выберите курс
                                            </option>
                                            {Array.from(
                                                { length: 8 },
                                                (_, index) => (
                                                    <option
                                                        key={index + 1}
                                                        value={index + 1}
                                                    >
                                                        {index + 1}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        {form.errors.course && (
                                            <span className="mt-1 block text-xs text-red-700">
                                                {form.errors.course}
                                            </span>
                                        )}
                                    </label>
                                    <label className="block text-sm font-medium text-gray-800">
                                        Уровень образования
                                        <select
                                            required
                                            value={
                                                form.data.education_level ?? ""
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    "education_level",
                                                    event.target.value,
                                                )
                                            }
                                            className={inputClass}
                                        >
                                            <option value="">
                                                Выберите уровень образования
                                            </option>
                                            <option value="bachelor">
                                                Бакалавриат
                                            </option>
                                            <option value="master">
                                                Магистратура
                                            </option>
                                            <option value="doctorate">
                                                Докторантура
                                            </option>
                                        </select>
                                        {form.errors.education_level && (
                                            <span className="mt-1 block text-xs text-red-700">
                                                {form.errors.education_level}
                                            </span>
                                        )}
                                    </label>
                                </div>
                            </section>
                            <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200/80">
                                <h3 className="border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4 text-base font-semibold text-[#274f93]">
                                    Фото для Face ID
                                </h3>
                                <div className="space-y-4 p-5 sm:p-6">
                                    <div className="min-w-0 w-full">
                                        <p className="text-sm font-medium text-gray-800">
                                            Фото студента
                                        </p>
                                        <label className="mt-2 flex min-h-28 cursor-pointer flex-wrap items-center gap-4 rounded-md border border-dashed border-[#aabfe0] bg-[#f8fbff] px-5 py-4 transition hover:border-[#355da8] hover:bg-[#f0f5ff] focus-within:ring-2 focus-within:ring-[#355da8] focus-within:ring-offset-2">
                                            <input
                                                type="file"
                                                accept="image/jpeg,image/png"
                                                required
                                                aria-label="Фото студента для Face ID"
                                                onChange={(event) => {
                                                    form.setData(
                                                        "photo",
                                                        event.target.files[0] ||
                                                            null,
                                                    );
                                                    form.clearErrors("photo");
                                                }}
                                                className="sr-only"
                                            />
                                            <span className="inline-flex shrink-0 rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white">
                                                Выбрать фото
                                            </span>
                                            <span className="min-w-0 text-sm text-gray-600">
                                                {form.data.photo?.name ||
                                                    "JPG или PNG, до 16 МБ."}
                                            </span>
                                        </label>
                                        {form.errors.photo && (
                                            <span className="mt-2 block text-xs text-red-700">
                                                {form.errors.photo}
                                            </span>
                                        )}
                                    </div>
                                    {previewUrl && (
                                        <img
                                            src={previewUrl}
                                            alt="Выбранное фото"
                                            className="h-36 w-28 rounded-md border border-gray-200 object-cover"
                                        />
                                    )}
                                </div>
                            </section>
                            <div className="flex justify-end">
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="rounded-md bg-[#355da8] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#2f5192] disabled:opacity-50"
                                >
                                    Отправить заявку
                                </button>
                            </div>
                            <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200/80">
                                <h3 className="border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4 text-base font-semibold text-[#274f93]">
                                    Как сделать фото для Face ID
                                </h3>
                                <div className="grid divide-y divide-gray-200 lg:grid-cols-[280px_minmax(0,1fr)] lg:divide-x lg:divide-y-0">
                                    <div className="p-5">
                                        <h4 className="text-sm font-semibold text-emerald-800">
                                            Подходит
                                        </h4>
                                        <img
                                            src="/images/face-id/correct-example.png"
                                            alt="Пример подходящего фото для Face ID"
                                            loading="lazy"
                                            className="mx-auto mt-3 h-52 w-48 max-w-full rounded border border-gray-200 object-cover object-center"
                                        />
                                        <ul className="mt-4 space-y-2 text-xs leading-5 text-gray-700">
                                            {suitablePhotoRules.map((rule) => (
                                                <li
                                                    key={rule}
                                                    className="flex gap-2.5"
                                                >
                                                    <span
                                                        aria-hidden="true"
                                                        className="font-bold text-emerald-700"
                                                    >
                                                        ✓
                                                    </span>
                                                    <span>{rule}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                    <div className="min-w-0 p-5">
                                        <h4 className="text-sm font-semibold text-red-800">
                                            Не подходит
                                        </h4>
                                        <div className="mt-3 grid grid-cols-2 gap-x-4 gap-y-4 sm:grid-cols-3">
                                            {unsuitablePhotoRules.map(
                                                (rule, index) => (
                                                    <figure
                                                        key={rule}
                                                        className="mx-auto w-full min-w-0 max-w-[220px]"
                                                    >
                                                        <div
                                                            aria-hidden="true"
                                                            className="aspect-[3/2] w-full rounded bg-gray-100"
                                                            style={{
                                                                backgroundImage:
                                                                    "url('/images/face-id/incorrect-examples.png')",
                                                                backgroundSize:
                                                                    "300% 300%",
                                                                backgroundPosition: `${(index % 3) * 50}% ${Math.floor(index / 3) * 50}%`,
                                                            }}
                                                        />
                                                        <figcaption className="mt-1.5 flex justify-center gap-1.5 text-center text-xs leading-4 text-gray-700">
                                                            <span
                                                                aria-hidden="true"
                                                                className="font-bold text-red-700"
                                                            >
                                                                ×
                                                            </span>
                                                            <span>{rule}</span>
                                                        </figcaption>
                                                    </figure>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                </div>
                                <p className="border-t border-gray-200 bg-gray-50 px-5 py-3 text-sm text-gray-700 sm:px-6">
                                    <span className="font-semibold">
                                        Совет:
                                    </span>{" "}
                                    уберите волосы с лица и снимите всё, что
                                    закрывает глаза и лоб.
                                </p>
                            </section>
                        </form>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
