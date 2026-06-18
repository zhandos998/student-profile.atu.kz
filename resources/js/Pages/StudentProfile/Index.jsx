import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router } from "@inertiajs/react";
import { useState } from "react";

const inputClass =
    "w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]";

function formatValue(value, fallback = "Не указано") {
    return value === null || value === undefined || value === ""
        ? fallback
        : value;
}

function completionClass(value) {
    if (value >= 80) {
        return "bg-emerald-50 text-emerald-700";
    }

    if (value >= 40) {
        return "bg-amber-50 text-amber-700";
    }

    return "bg-rose-50 text-rose-700";
}

export default function Index({
    students,
    filters,
    options,
    availableGroups = [],
    profileStatusOptions = [],
    canCreateStudentProfiles = true,
}) {
    const [filterData, setFilterData] = useState(filters);
    const visibleGroupOptions = availableGroups.filter(
        (group) =>
            !filterData.faculty ||
            !group.faculty ||
            group.faculty === filterData.faculty,
    );

    const setFilter = (field, value) => {
        setFilterData((current) => ({
            ...current,
            [field]: value,
        }));
    };

    const setFacultyFilter = (faculty) => {
        setFilterData((current) => {
            const selectedGroup = availableGroups.find(
                (group) =>
                    String(group.value) ===
                    String(current.student_group_id),
            );

            return {
                ...current,
                faculty,
                student_group_id:
                    selectedGroup?.faculty && selectedGroup.faculty !== faculty
                        ? ""
                        : current.student_group_id,
                group_name:
                    selectedGroup?.faculty && selectedGroup.faculty !== faculty
                        ? ""
                        : current.group_name,
            };
        });
    };

    const setStudentGroupFilter = (studentGroupId) => {
        const selectedGroup = availableGroups.find(
            (group) => String(group.value) === String(studentGroupId),
        );

        setFilterData((current) => ({
            ...current,
            student_group_id: studentGroupId,
            group_name: selectedGroup?.name || "",
            faculty: selectedGroup?.faculty || current.faculty,
        }));
    };

    const submitFilters = (event) => {
        event.preventDefault();

        router.get(route("student-profiles.index"), filterData, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const resetFilters = () => {
        const emptyFilters = {
            search: "",
            faculty: "",
            student_group_id: "",
            group_name: "",
            course: "",
            profile_status: "",
        };

        setFilterData(emptyFilters);
        router.get(route("student-profiles.index"), emptyFilters, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Портреты студентов
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Список анкет студентов с фильтрами и созданием
                            нового портрета.
                        </p>
                    </div>
                    {canCreateStudentProfiles && (
                        <Link
                            href={route("student-profiles.create")}
                            className="inline-flex items-center justify-center rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2f5192]"
                        >
                            Создать портрет
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Портреты студентов" />

            <div className="bg-[#f4f7fc] py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200/80">
                        <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4">
                            <h3 className="text-base font-semibold text-[#274f93]">
                                Фильтр портретов
                            </h3>
                        </div>
                        <form
                            onSubmit={submitFilters}
                            className="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-5"
                        >
                            <div className="xl:col-span-2">
                                <label className="text-sm font-medium text-gray-700">
                                    Поиск
                                </label>
                                <input
                                    value={filterData.search}
                                    onChange={(event) =>
                                        setFilter(
                                            "search",
                                            event.target.value,
                                        )
                                    }
                                    placeholder="ФИО, email, ИИН, группа"
                                    className={`${inputClass} mt-1`}
                                />
                            </div>

                            <div>
                                <label className="text-sm font-medium text-gray-700">
                                    Факультет
                                </label>
                                <select
                                    value={filterData.faculty}
                                    onChange={(event) =>
                                        setFacultyFilter(event.target.value)
                                    }
                                    className={`${inputClass} mt-1`}
                                >
                                    <option value="">Все</option>
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
                                <label className="text-sm font-medium text-gray-700">
                                    Группа
                                </label>
                                <select
                                    value={filterData.student_group_id}
                                    onChange={(event) =>
                                        setStudentGroupFilter(
                                            event.target.value,
                                        )
                                    }
                                    disabled={availableGroups.length === 0}
                                    className={`${inputClass} mt-1 disabled:cursor-not-allowed disabled:bg-gray-50`}
                                >
                                    <option value="">
                                        {availableGroups.length === 0
                                            ? "Сначала создайте группу"
                                            : "Все группы"}
                                    </option>
                                    {visibleGroupOptions.map((group) => (
                                        <option
                                            key={group.value}
                                            value={group.value}
                                        >
                                            {group.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="text-sm font-medium text-gray-700">
                                    Курс
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    max="8"
                                    value={filterData.course}
                                    onChange={(event) =>
                                        setFilter("course", event.target.value)
                                    }
                                    className={`${inputClass} mt-1`}
                                />
                            </div>

                            <div>
                                <label className="text-sm font-medium text-gray-700">
                                    Статус анкеты
                                </label>
                                <select
                                    value={filterData.profile_status}
                                    onChange={(event) =>
                                        setFilter(
                                            "profile_status",
                                            event.target.value,
                                        )
                                    }
                                    className={`${inputClass} mt-1`}
                                >
                                    <option value="">Все</option>
                                    <option value="with_profile">
                                        Есть портрет
                                    </option>
                                    <option value="without_profile">
                                        Нет портрета
                                    </option>
                                    {profileStatusOptions.map((status) => (
                                        <option
                                            key={status.value}
                                            value={status.value}
                                        >
                                            {status.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex items-end gap-3 xl:col-span-4">
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
                                    Сбросить
                                </button>
                            </div>
                        </form>
                    </section>

                    <section className="rounded-lg bg-white shadow-sm ring-1 ring-gray-200/80">
                        <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4">
                            <h3 className="text-base font-semibold text-[#274f93]">
                                Список портретов
                            </h3>
                        </div>

                        {students.data.length === 0 ? (
                            <p className="px-5 py-6 text-sm text-gray-500">
                                Студенты не найдены.
                            </p>
                        ) : (
                            <div className="divide-y divide-gray-100">
                                {students.data.map((student) => (
                                    <div
                                        key={student.id}
                                        className="grid gap-4 px-5 py-4 lg:grid-cols-[1.4fr_1fr_0.6fr_0.6fr_auto] lg:items-center"
                                    >
                                        <div>
                                            <p className="text-sm font-semibold text-gray-900">
                                                {student.fullName}
                                            </p>
                                            <p className="mt-1 text-sm text-gray-500">
                                                {student.email}
                                            </p>
                                        </div>

                                        <div className="text-sm text-gray-700">
                                            <p>{formatValue(student.faculty)}</p>
                                            <p className="mt-1 text-gray-500">
                                                {formatValue(
                                                    student.specialty,
                                                )}
                                            </p>
                                        </div>

                                        <div className="text-sm text-gray-700">
                                            <p>
                                                Группа:{" "}
                                                {formatValue(
                                                    student.groupName,
                                                )}
                                            </p>
                                            <p className="mt-1 text-gray-500">
                                                Курс:{" "}
                                                {formatValue(student.course)}
                                            </p>
                                        </div>

                                        <div className="flex flex-wrap gap-2">
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-medium ${completionClass(
                                                    student.completion,
                                                )}`}
                                            >
                                                {student.completion}%
                                            </span>
                                            <span className="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                                                GPA{" "}
                                                {formatValue(
                                                    student.gpa,
                                                    "нет",
                                                )}
                                            </span>
                                            <span className="rounded-full bg-[#f4f7fc] px-3 py-1 text-xs font-medium text-[#355da8]">
                                                {student.profileStatusLabel}
                                            </span>
                                        </div>

                                        <Link
                                            href={student.editUrl}
                                            className="inline-flex items-center justify-center rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#2f5192]"
                                        >
                                            Открыть
                                        </Link>
                                    </div>
                                ))}
                            </div>
                        )}

                        {students.links.length > 3 && (
                            <div className="flex flex-wrap gap-2 border-t border-gray-200 px-5 py-4">
                                {students.links.map((link, index) => (
                                    <Link
                                        key={`${link.label}-${index}`}
                                        href={link.url ?? "#"}
                                        preserveScroll
                                        className={`rounded-md px-3 py-2 text-sm ${
                                            link.active
                                                ? "bg-[#355da8] text-white"
                                                : "bg-white text-gray-700 ring-1 ring-gray-200"
                                        } ${
                                            link.url
                                                ? "hover:bg-[#f4f7fc]"
                                                : "pointer-events-none opacity-50"
                                        }`}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ))}
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
