import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import Modal from "@/Components/Modal";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import TextInput from "@/Components/TextInput";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";

const valueOrEmpty = (value) => value ?? "";

function SectionCard({ title, description = null, badge = null, bodyClassName = "p-6", children }) {
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
            <div className={bodyClassName}>{children}</div>
        </section>
    );
}

function GroupPagination({ groups }) {
    if (groups.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-gray-600">
                Страница {groups.current_page} из {groups.last_page}
            </p>
            <nav aria-label="Страницы групп" className="flex flex-wrap gap-1.5">
                {groups.links.map((link, index) => {
                    const label = index === 0
                        ? "Назад"
                        : index === groups.links.length - 1
                          ? "Далее"
                          : link.label;
                    const className = `inline-flex min-h-9 min-w-9 items-center justify-center rounded border px-2.5 text-sm font-medium ${
                        link.active
                            ? "border-[#355da8] bg-[#355da8] text-white"
                            : "border-gray-200 bg-white text-gray-700 hover:bg-[#edf3ff]"
                    }`;

                    return link.url ? (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            preserveState
                            aria-current={link.active ? "page" : undefined}
                            className={className}
                        >
                            {label}
                        </Link>
                    ) : (
                        <span
                            key={index}
                            aria-disabled="true"
                            className={`${className} cursor-default opacity-50`}
                        >
                            {label}
                        </span>
                    );
                })}
            </nav>
        </div>
    );
}

export default function Index({
    groups = { data: [], total: 0, last_page: 1, links: [] },
    filters = { name: "", faculty: "", course: "", curator_id: "", per_page: "20" },
    options = { faculties: [], courses: [], curators: [] },
}) {
    const roleSlug = usePage().props.auth?.user?.role?.slug;
    const isAdministrator = ["administrator_dit", "administration"].includes(roleSlug);
    const [data, setData] = useState({
        faculty: "",
        name: "",
    });
    const [filterData, setFilterData] = useState(filters);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [groupToRename, setGroupToRename] = useState(null);
    const [renameData, setRenameData] = useState({ name: "" });
    const [renameErrors, setRenameErrors] = useState({});
    const [renameProcessing, setRenameProcessing] = useState(false);
    const [groupToDelete, setGroupToDelete] = useState(null);
    const [deleteErrors, setDeleteErrors] = useState({});
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    useEffect(() => {
        setFilterData(filters);
    }, [filters.name, filters.faculty, filters.course, filters.curator_id, filters.per_page]);

    const closeCreateModal = () => {
        if (processing) {
            return;
        }

        setShowCreateModal(false);
        setErrors({});
    };

    const openRenameModal = (group) => {
        setGroupToRename(group);
        setRenameData({ name: group.name || "" });
        setRenameErrors({});
    };

    const closeRenameModal = () => {
        if (renameProcessing) {
            return;
        }

        setGroupToRename(null);
        setRenameErrors({});
    };

    const closeDeleteModal = () => {
        if (deleteProcessing) {
            return;
        }

        setGroupToDelete(null);
        setDeleteErrors({});
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

    const renameGroup = (event) => {
        event.preventDefault();

        if (!groupToRename) {
            return;
        }

        router.patch(route("groups.update", groupToRename.id), renameData, {
            preserveScroll: true,
            onStart: () => setRenameProcessing(true),
            onError: (validationErrors) => setRenameErrors(validationErrors),
            onSuccess: () => {
                setRenameErrors({});
                setGroupToRename(null);
            },
            onFinish: () => setRenameProcessing(false),
        });
    };

    const destroyGroup = () => {
        if (!groupToDelete) {
            return;
        }

        router.delete(route("groups.destroy", groupToDelete.id), {
            preserveScroll: true,
            onStart: () => setDeleteProcessing(true),
            onError: (validationErrors) => setDeleteErrors(validationErrors),
            onSuccess: () => {
                setDeleteErrors({});
                setGroupToDelete(null);
            },
            onFinish: () => setDeleteProcessing(false),
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
            name: "",
            faculty: "",
            course: "",
            curator_id: "",
            per_page: "20",
        };

        setFilterData(emptyFilters);
        router.get(route("groups.index"), emptyFilters, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const changePageSize = (event) => {
        const nextFilters = { ...filters, per_page: event.target.value };

        setFilterData(nextFilters);
        router.get(route("groups.index"), nextFilters, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const createGroupButton = (
        <button
            type="button"
            onClick={() => setShowCreateModal(true)}
            className={`inline-flex items-center justify-center rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2f5192] focus:outline-none focus:ring-2 focus:ring-[#355da8] focus:ring-offset-2 ${isAdministrator ? "w-full sm:w-auto" : ""}`}
        >
            Создать группу
        </button>
    );

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Группы
                    </h2>
                    {isAdministrator && createGroupButton}
                </div>
            }
        >
            <Head title="Группы" />

            <div className="bg-[#f4f7fc] py-8">
                <div className="w-full space-y-6 px-4 sm:px-6 lg:px-8">
                    {!isAdministrator && (
                        <div className="flex justify-end">
                            {createGroupButton}
                        </div>
                    )}
                    <SectionCard title="Фильтр групп">
                        <form
                            onSubmit={submitFilters}
                            className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                        >
                            <div className="xl:col-span-2">
                                <InputLabel value="Название группы" />
                                <TextInput
                                    value={filterData.name}
                                    onChange={(event) =>
                                        setFilterData((current) => ({
                                            ...current,
                                            name: event.target.value,
                                        }))
                                    }
                                    className="mt-1 block w-full"
                                    placeholder="Например, IS-23-1"
                                />
                            </div>

                            <div className="xl:col-span-2">
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
                                <InputLabel value="Ответственный" />
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

                            <div className="flex items-end gap-3 xl:col-span-2">
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
                        bodyClassName="p-0"
                        badge={
                            <div className="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                                <span>Всего: {groups.total}</span>
                                <label className="flex items-center gap-2">
                                    На странице
                                    <select
                                        value={filters.per_page}
                                        onChange={changePageSize}
                                        className="rounded border-gray-300 py-1 text-sm shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
                                    >
                                        {[10, 20, 50, 100].map((size) => (
                                            <option key={size} value={size}>
                                                {size}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            </div>
                        }
                    >
                        {groups.data.length === 0 ? (
                            <p className="px-5 py-8 text-sm text-gray-600">
                                {filters.name || filters.faculty || filters.course || filters.curator_id
                                    ? "Группы не найдены."
                                    : "Группы пока не созданы."}
                            </p>
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[1000px] table-fixed text-left text-sm">
                                        <colgroup>
                                            <col className="w-[20%]" />
                                            <col className="w-[27%]" />
                                            <col className="w-[9%]" />
                                            <col className="w-[18%]" />
                                            <col className="w-[26%]" />
                                        </colgroup>
                                        <thead className="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase text-gray-600">
                                            <tr>
                                                <th scope="col" className="px-5 py-3">Группа</th>
                                                <th scope="col" className="px-5 py-3">Факультет</th>
                                                <th scope="col" className="px-5 py-3 text-right">Студенты</th>
                                                <th scope="col" className="px-5 py-3">Ответственный</th>
                                                <th scope="col" className="px-5 py-3 text-right">Действия</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {groups.data.map((group) => (
                                                <tr key={group.id} className="hover:bg-[#f7f9fd]">
                                                    <td className="px-5 py-3 align-middle">
                                                        <Link
                                                            href={group.passport_url}
                                                            className="font-semibold text-[#274f93] hover:underline"
                                                        >
                                                            {group.name}
                                                        </Link>
                                                    </td>
                                                    <td className="px-5 py-3 align-middle text-gray-700">
                                                        {valueOrEmpty(group.faculty) || "Факультет не указан"}
                                                    </td>
                                                    <td className="px-5 py-3 text-right align-middle font-semibold tabular-nums text-gray-900">
                                                        {group.students_count}
                                                    </td>
                                                    <td className="px-5 py-3 align-middle text-gray-700">
                                                        {valueOrEmpty(group.curator_name) || "Не указан"}
                                                    </td>
                                                    <td className="px-5 py-3 align-middle">
                                                        <div className="flex flex-wrap items-center justify-end gap-x-3 gap-y-2">
                                                            <Link
                                                                href={group.passport_url}
                                                                className="font-medium text-[#355da8] hover:underline"
                                                            >
                                                                Открыть
                                                            </Link>
                                                            {group.can_rename && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => openRenameModal(group)}
                                                                    className="font-medium text-gray-700 hover:text-[#355da8]"
                                                                >
                                                                    Переименовать
                                                                </button>
                                                            )}
                                                            {(group.can_delete || group.delete_blocked_reason) && (
                                                                <button
                                                                    type="button"
                                                                    disabled={!group.can_delete}
                                                                    title={group.delete_blocked_reason || ""}
                                                                    onClick={() => setGroupToDelete(group)}
                                                                    className="font-medium text-red-700 hover:text-red-800 disabled:cursor-not-allowed disabled:text-gray-400"
                                                                >
                                                                    Удалить
                                                                </button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <GroupPagination groups={groups} />
                            </>
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
                                required
                                onChange={(event) =>
                                    setData((current) => ({
                                        ...current,
                                        faculty: event.target.value,
                                    }))
                                }
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
                            >
                                <option value="" disabled>
                                    Выберите факультет
                                </option>
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

            <Modal
                show={Boolean(groupToRename)}
                maxWidth="lg"
                closeable={!renameProcessing}
                onClose={closeRenameModal}
            >
                <form onSubmit={renameGroup}>
                    <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-6 py-4">
                        <h3 className="text-base font-semibold text-[#274f93]">
                            Переименовать группу
                        </h3>
                        <p className="mt-1 text-sm text-[#426aa8]">
                            Новое название будет применено к группе, социальному
                            паспорту и карточкам студентов этой группы.
                        </p>
                    </div>

                    <div className="space-y-5 p-6">
                        <div>
                            <InputLabel value="Название группы" />
                            <TextInput
                                value={renameData.name}
                                onChange={(event) =>
                                    setRenameData({
                                        name: event.target.value,
                                    })
                                }
                                className="mt-1 block w-full"
                                placeholder="Например, IS-23-1"
                                autoFocus
                            />
                            <InputError
                                message={renameErrors.name}
                                className="mt-2"
                            />
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
                        <SecondaryButton
                            type="button"
                            disabled={renameProcessing}
                            onClick={closeRenameModal}
                        >
                            Отмена
                        </SecondaryButton>
                        <PrimaryButton disabled={renameProcessing}>
                            Сохранить
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            <Modal
                show={Boolean(groupToDelete)}
                maxWidth="lg"
                closeable={!deleteProcessing}
                onClose={closeDeleteModal}
            >
                <div className="border-b border-red-100 bg-red-50 px-6 py-4">
                    <h3 className="text-base font-semibold text-red-800">
                        Удалить группу
                    </h3>
                    <p className="mt-1 text-sm text-red-700">
                        {groupToDelete?.name}
                    </p>
                </div>

                <div className="space-y-3 p-6 text-sm text-gray-700">
                    <p>
                        Группа будет удалена вместе с социальным паспортом. Это
                        действие нельзя отменить.
                    </p>
                    {deleteErrors.group_delete && (
                        <p className="rounded-md bg-red-50 px-3 py-2 text-red-700 ring-1 ring-red-100">
                            {deleteErrors.group_delete}
                        </p>
                    )}
                </div>

                <div className="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
                    <SecondaryButton
                        type="button"
                        disabled={deleteProcessing}
                        onClick={closeDeleteModal}
                    >
                        Отмена
                    </SecondaryButton>
                    <button
                        type="button"
                        disabled={deleteProcessing}
                        onClick={destroyGroup}
                        className="inline-flex items-center justify-center rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100 disabled:opacity-50"
                    >
                        Удалить
                    </button>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
