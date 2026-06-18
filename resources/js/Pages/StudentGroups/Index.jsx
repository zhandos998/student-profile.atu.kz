import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import Modal from "@/Components/Modal";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import TextInput from "@/Components/TextInput";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router } from "@inertiajs/react";
import { useState } from "react";

const valueOrEmpty = (value) => value ?? "";

function SectionCard({ title, description = null, badge = null, children }) {
    return (
        <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200/80">
            <div className="flex flex-col gap-2 border-b border-[#dbe5f6] bg-[#edf3ff] px-6 py-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 className="text-base font-semibold text-[#274f93]">
                        {title}
                    </h3>
                    {description && (
                        <p className="mt-1 text-sm text-[#426aa8]">
                            {description}
                        </p>
                    )}
                </div>
                {badge}
            </div>
            <div className="p-6">{children}</div>
        </section>
    );
}

export default function Index({
    groups = [],
    filters = { faculty: "", course: "", curator_id: "" },
    options = { faculties: [], courses: [], curators: [] },
}) {
    const [data, setData] = useState({
        faculty: "",
        name: "",
    });
    const [filterData, setFilterData] = useState(filters);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [showCreateModal, setShowCreateModal] = useState(false);

    const closeCreateModal = () => {
        if (processing) {
            return;
        }

        setShowCreateModal(false);
        setErrors({});
    };

    const submit = (event) => {
        event.preventDefault();

        router.post(route("groups.store"), data, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onError: (validationErrors) => setErrors(validationErrors),
            onSuccess: () => {
                setErrors({});
                setData({ faculty: "", name: "" });
                setShowCreateModal(false);
            },
            onFinish: () => setProcessing(false),
        });
    };

    const submitFilters = (event) => {
        event.preventDefault();

        router.get(route("groups.index"), filterData, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const resetFilters = () => {
        const emptyFilters = {
            faculty: "",
            course: "",
            curator_id: "",
        };

        setFilterData(emptyFilters);
        router.get(route("groups.index"), emptyFilters, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Группы
                </h2>
            }
        >
            <Head title="Группы" />

            <div className="bg-[#f4f7fc] py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-end">
                        <button
                            type="button"
                            onClick={() => setShowCreateModal(true)}
                            className="inline-flex items-center justify-center rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2f5192] focus:outline-none focus:ring-2 focus:ring-[#355da8] focus:ring-offset-2"
                        >
                            Создать группу
                        </button>
                    </div>

                    <SectionCard title="Фильтр групп">
                        <form
                            onSubmit={submitFilters}
                            className="grid gap-4 md:grid-cols-2 xl:grid-cols-[1fr_0.5fr_1fr_auto]"
                        >
                            <div>
                                <InputLabel value="Факультет" />
                                <select
                                    value={filterData.faculty}
                                    onChange={(event) =>
                                        setFilterData((current) => ({
                                            ...current,
                                            faculty: event.target.value,
                                        }))
                                    }
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
                                >
                                    <option value="">Все факультеты</option>
                                    {options.faculties.map((faculty) => (
                                        <option
                                            key={faculty.value}
                                            value={faculty.value}
                                        >
                                            {faculty.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <InputLabel value="Курс" />
                                <select
                                    value={filterData.course}
                                    onChange={(event) =>
                                        setFilterData((current) => ({
                                            ...current,
                                            course: event.target.value,
                                        }))
                                    }
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
                                >
                                    <option value="">Все</option>
                                    {options.courses.map((course) => (
                                        <option key={course} value={course}>
                                            {course}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <InputLabel value="Куратор/эдвайзер" />
                                <select
                                    value={filterData.curator_id}
                                    onChange={(event) =>
                                        setFilterData((current) => ({
                                            ...current,
                                            curator_id: event.target.value,
                                        }))
                                    }
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
                                >
                                    <option value="">Все</option>
                                    {options.curators.map((curator) => (
                                        <option
                                            key={curator.value}
                                            value={curator.value}
                                        >
                                            {curator.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex items-end gap-3">
                                <button
                                    type="submit"
                                    className="inline-flex items-center justify-center rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2f5192]"
                                >
                                    Фильтр
                                </button>
                                <button
                                    type="button"
                                    onClick={resetFilters}
                                    className="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-[#f4f7fc]"
                                >
                                    Сброс
                                </button>
                            </div>
                        </form>
                    </SectionCard>

                    <SectionCard
                        title="Список групп"
                        description="У каждой группы свой социальный паспорт и свой список студентов."
                        badge={
                            <div className="rounded-md bg-[#355da8] px-3 py-2 text-sm font-semibold text-white">
                                {groups.length}
                            </div>
                        }
                    >
                        {groups.length === 0 ? (
                            <div className="rounded-md border border-dashed border-gray-300 bg-gray-50 p-6 text-sm text-gray-600">
                                Группы пока не созданы.
                            </div>
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {groups.map((group) => (
                                    <article
                                        key={group.id}
                                        className="flex flex-col overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm"
                                    >
                                        <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-4 py-3">
                                            <h4 className="text-base font-semibold text-[#274f93]">
                                                {group.name}
                                            </h4>
                                            <p className="mt-1 text-sm text-[#426aa8]">
                                                {valueOrEmpty(
                                                    group.faculty,
                                                ) || "Факультет не указан"}
                                            </p>
                                        </div>

                                        <div className="flex flex-1 flex-col p-4">
                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="rounded-md bg-gray-50 p-3 ring-1 ring-gray-200/70">
                                                    <p className="text-xs font-medium text-gray-500">
                                                        Студенты
                                                    </p>
                                                    <p className="mt-1 text-xl font-semibold tabular-nums text-gray-950">
                                                        {group.students_count}
                                                    </p>
                                                </div>
                                                <div className="rounded-md bg-gray-50 p-3 ring-1 ring-gray-200/70">
                                                    <p className="text-xs font-medium text-gray-500">
                                                        Куратор
                                                    </p>
                                                    <p className="mt-1 truncate text-sm font-semibold text-gray-950">
                                                        {valueOrEmpty(
                                                            group.curator_name,
                                                        ) || "Не указан"}
                                                    </p>
                                                </div>
                                            </div>

                                            <Link
                                                href={group.passport_url}
                                                className="mt-4 inline-flex items-center justify-center rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2f5192]"
                                            >
                                                Открыть соцпаспорт
                                            </Link>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        )}
                    </SectionCard>
                </div>
            </div>

            <Modal
                show={showCreateModal}
                maxWidth="lg"
                closeable={!processing}
                onClose={closeCreateModal}
            >
                <form onSubmit={submit}>
                    <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-6 py-4">
                        <h3 className="text-base font-semibold text-[#274f93]">
                            Создать группу
                        </h3>
                        <p className="mt-1 text-sm text-[#426aa8]">
                            После создания откроется социальный паспорт этой
                            группы.
                        </p>
                    </div>

                    <div className="space-y-5 p-6">
                        <div>
                            <InputLabel value="Факультет" />
                            <select
                                value={data.faculty}
                                onChange={(event) =>
                                    setData((current) => ({
                                        ...current,
                                        faculty: event.target.value,
                                    }))
                                }
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
                            >
                                <option value="">Не указано</option>
                                {options.faculties.map((faculty) => (
                                    <option
                                        key={faculty.value}
                                        value={faculty.value}
                                    >
                                        {faculty.label}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={errors.faculty}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel value="Группа" />
                            <TextInput
                                value={data.name}
                                onChange={(event) =>
                                    setData((current) => ({
                                        ...current,
                                        name: event.target.value,
                                    }))
                                }
                                className="mt-1 block w-full"
                                placeholder="Например, IS-23-1"
                            />
                            <InputError message={errors.name} className="mt-2" />
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
                        <SecondaryButton
                            type="button"
                            disabled={processing}
                            onClick={closeCreateModal}
                        >
                            Отмена
                        </SecondaryButton>
                        <PrimaryButton disabled={processing}>
                            Создать
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
