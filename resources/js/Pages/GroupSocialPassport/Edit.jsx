import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PrimaryButton from "@/Components/PrimaryButton";
import TextInput from "@/Components/TextInput";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router } from "@inertiajs/react";
import { useState } from "react";

const valueOrEmpty = (value) => value ?? "";

const displayValue = (value) => {
    const text = valueOrEmpty(value);

    return text === "" ? "—" : text;
};

const compactValue = (value, limit = 80) => {
    const text = displayValue(value);

    return text.length > limit ? `${text.slice(0, limit)}...` : text;
};

const studentColumns = [
    ["full_name", "ФИО"],
    ["birth_date", "Дата рождения"],
    ["study_form", "Форма обучения"],
    ["nationality", "Национальность"],
    ["iin", "ИИН"],
    ["identity_document_number", "№ уд.личн."],
    ["contact_details", "Контактные данные(сотовый телефон)"],
    ["stay_address", "Адрес пребывания"],
    ["residence_address", "Адрес проживания"],
    ["parent_details", "Сведения о родителях"],
    ["social_status", "Социальный статус"],
    ["religion_details", "Вероисповедание"],
];

const summaryFields = [
    ["disabled_students", "Студенты инвалиды"],
    ["orphan_students", "Студенты сироты"],
    ["incomplete_family_students", "Студенты из неполной семьи"],
    ["large_family_students", "Студенты из многодетной семьи"],
    ["low_income_students", "Студенты из малообеспеченной семьи"],
    ["married_students", "Семейные студенты"],
    ["foreign_students", "Студенты иностранцы"],
    ["dormitory_students", "Студенты, проживающие в общежитии"],
    ["relatives_living_students", "Студенты, проживающие у родственников"],
    ["rental_housing_students", "Студенты, арендующие жилье"],
    ["total_students", "Общее количество студентов в группе"],
];

const sectionHeadingClass =
    "-mx-6 -mt-6 mb-6 border-b border-[#dbe5f6] bg-[#edf3ff] px-6 py-4 text-base font-semibold text-[#274f93]";

const sectionHeadingBlockClass =
    "-mx-6 -mt-6 mb-6 border-b border-[#dbe5f6] bg-[#edf3ff] px-6 py-4";

const sectionHeadingTitleClass = "text-base font-semibold text-[#274f93]";

const sectionHeadingDescriptionClass = "mt-1 text-sm text-[#426aa8]";

function emptySummary() {
    return Object.fromEntries(summaryFields.map(([key]) => [key, ""]));
}

function Field({ label, error, children }) {
    return (
        <div>
            <InputLabel value={label} />
            <div className="mt-1">{children}</div>
            <InputError message={error} className="mt-2" />
        </div>
    );
}

export default function Edit({
    passport,
    updateRoute = route("group-social-passport.update"),
    groupsIndexUrl = route("groups.index"),
    groupOptions = [],
}) {
    const [data, setData] = useState({
        faculty: valueOrEmpty(passport.faculty),
        student_group_id: valueOrEmpty(passport.student_group_id),
        group_name: valueOrEmpty(passport.group_name),
        leader_full_name: valueOrEmpty(passport.leader_full_name),
        leader_phone: valueOrEmpty(passport.leader_phone),
        leader_email: valueOrEmpty(passport.leader_email),
        curator_full_name: valueOrEmpty(passport.curator_full_name),
        curator_phone: valueOrEmpty(passport.curator_phone),
        curator_email: valueOrEmpty(passport.curator_email),
        deputy_dean_ur_full_name: valueOrEmpty(
            passport.deputy_dean_ur_full_name,
        ),
        deputy_dean_ur_phone: valueOrEmpty(passport.deputy_dean_ur_phone),
        deputy_dean_ur_email: valueOrEmpty(passport.deputy_dean_ur_email),
        deputy_dean_vr_full_name: valueOrEmpty(
            passport.deputy_dean_vr_full_name,
        ),
        deputy_dean_vr_phone: valueOrEmpty(passport.deputy_dean_vr_phone),
        deputy_dean_vr_email: valueOrEmpty(passport.deputy_dean_vr_email),
        students: passport.students ?? [],
        summary: {
            ...emptySummary(),
            ...(passport.summary ?? {}),
        },
        departed_students: passport.departed_students ?? [],
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [recentlySuccessful, setRecentlySuccessful] = useState(false);
    const [selectedStudentIndex, setSelectedStudentIndex] = useState(null);
    const selectedStudent =
        selectedStudentIndex === null
            ? null
            : data.students[selectedStudentIndex] ?? null;
    const selectedGroup = groupOptions.find(
        (group) => String(group.value) === String(data.student_group_id),
    );

    const setField = (field, value) => {
        setData((current) => ({
            ...current,
            [field]: value,
        }));
    };

    const changeGroup = (studentGroupId) => {
        const group = groupOptions.find(
            (option) => String(option.value) === String(studentGroupId),
        );

        setData((current) => ({
            ...current,
            student_group_id: studentGroupId,
            group_name: group?.name ?? "",
            faculty: group?.faculty ?? "",
        }));

        if (studentGroupId) {
            router.get(route("groups.social-passport.edit", studentGroupId));
        }
    };

    const submit = (event) => {
        event.preventDefault();

        router.post(updateRoute, data, {
            preserveScroll: true,
            onStart: () => {
                setProcessing(true);
                setRecentlySuccessful(false);
            },
            onError: (validationErrors) => setErrors(validationErrors),
            onSuccess: () => {
                setErrors({});
                setRecentlySuccessful(true);
                window.setTimeout(() => setRecentlySuccessful(false), 2000);
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Социальный паспорт группы
                </h2>
            }
        >
            <Head title="Социальный паспорт группы" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-4 flex justify-end">
                        <Link
                            href={groupsIndexUrl}
                            className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
                        >
                            Все группы
                        </Link>
                    </div>
                    <form
                        onSubmit={submit}
                        className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                    >
                        <section className="border-b border-gray-200 p-6">
                            <div className="space-y-6">
                                <div>
                                    <h3 className={sectionHeadingClass}>
                                        Группа
                                    </h3>
                                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                        <Field
                                            label="Факультет"
                                            error={errors.faculty}
                                        >
                                            <div className="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900">
                                                {displayValue(
                                                    selectedGroup?.faculty ??
                                                        data.faculty,
                                                )}
                                            </div>
                                        </Field>

                                        <Field
                                            label="Группа"
                                            error={
                                                errors.student_group_id ||
                                                errors.group_name
                                            }
                                        >
                                            <select
                                                value={data.student_group_id}
                                                onChange={(event) =>
                                                    changeGroup(
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
                                            >
                                                {groupOptions.length === 0 && (
                                                    <option value="">
                                                        Группы не найдены
                                                    </option>
                                                )}
                                                {groupOptions.map((group) => (
                                                    <option
                                                        key={group.value}
                                                        value={group.value}
                                                    >
                                                        {group.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </Field>
                                    </div>
                                </div>

                                <div className="border-t border-gray-100 pt-6">
                                    <h3 className={sectionHeadingClass}>
                                        Староста
                                    </h3>
                                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                        <Field
                                            label="ФИО"
                                            error={errors.leader_full_name}
                                        >
                                            <TextInput
                                                value={data.leader_full_name}
                                                onChange={(event) =>
                                                    setField(
                                                        "leader_full_name",
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full"
                                            />
                                        </Field>
                                        <Field
                                            label="Телефон"
                                            error={errors.leader_phone}
                                        >
                                            <TextInput
                                                value={data.leader_phone}
                                                onChange={(event) =>
                                                    setField(
                                                        "leader_phone",
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full"
                                            />
                                        </Field>
                                        <Field
                                            label="Эл.адрес"
                                            error={errors.leader_email}
                                        >
                                            <TextInput
                                                type="email"
                                                value={data.leader_email}
                                                onChange={(event) =>
                                                    setField(
                                                        "leader_email",
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full"
                                            />
                                        </Field>
                                    </div>
                                </div>

                                <div className="border-t border-gray-100 pt-6">
                                    <h3 className={sectionHeadingClass}>
                                        Куратор / эдвайзер
                                    </h3>
                                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                        <Field
                                            label="ФИО"
                                            error={errors.curator_full_name}
                                        >
                                            <TextInput
                                                value={data.curator_full_name}
                                                onChange={(event) =>
                                                    setField(
                                                        "curator_full_name",
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full"
                                            />
                                        </Field>
                                        <Field
                                            label="Телефон"
                                            error={errors.curator_phone}
                                        >
                                            <TextInput
                                                value={data.curator_phone}
                                                onChange={(event) =>
                                                    setField(
                                                        "curator_phone",
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full"
                                            />
                                        </Field>
                                        <Field
                                            label="Эл.адрес"
                                            error={errors.curator_email}
                                        >
                                            <TextInput
                                                type="email"
                                                value={data.curator_email}
                                                onChange={(event) =>
                                                    setField(
                                                        "curator_email",
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full"
                                            />
                                        </Field>
                                    </div>
                                </div>

                                <div className="border-t border-gray-100 pt-6">
                                    <h3 className={sectionHeadingClass}>
                                        Заместитель декана по УР
                                    </h3>
                                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                        <Field
                                            label="ФИО"
                                            error={
                                                errors.deputy_dean_ur_full_name
                                            }
                                        >
                                            <TextInput
                                                value={
                                                    data.deputy_dean_ur_full_name
                                                }
                                                onChange={(event) =>
                                                    setField(
                                                        "deputy_dean_ur_full_name",
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full"
                                            />
                                        </Field>
                                        <Field
                                            label="Телефон"
                                            error={
                                                errors.deputy_dean_ur_phone
                                            }
                                        >
                                            <TextInput
                                                value={
                                                    data.deputy_dean_ur_phone
                                                }
                                                onChange={(event) =>
                                                    setField(
                                                        "deputy_dean_ur_phone",
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full"
                                            />
                                        </Field>
                                        <Field
                                            label="Эл.адрес"
                                            error={
                                                errors.deputy_dean_ur_email
                                            }
                                        >
                                            <TextInput
                                                type="email"
                                                value={
                                                    data.deputy_dean_ur_email
                                                }
                                                onChange={(event) =>
                                                    setField(
                                                        "deputy_dean_ur_email",
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full"
                                            />
                                        </Field>
                                    </div>
                                </div>

                                <div className="border-t border-gray-100 pt-6">
                                    <h3 className={sectionHeadingClass}>
                                        Заместитель декана по ВР
                                    </h3>
                                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                        <Field
                                            label="ФИО"
                                            error={
                                                errors.deputy_dean_vr_full_name
                                            }
                                        >
                                            <TextInput
                                                value={
                                                    data.deputy_dean_vr_full_name
                                                }
                                                onChange={(event) =>
                                                    setField(
                                                        "deputy_dean_vr_full_name",
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full"
                                            />
                                        </Field>
                                        <Field
                                            label="Телефон"
                                            error={
                                                errors.deputy_dean_vr_phone
                                            }
                                        >
                                            <TextInput
                                                value={
                                                    data.deputy_dean_vr_phone
                                                }
                                                onChange={(event) =>
                                                    setField(
                                                        "deputy_dean_vr_phone",
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full"
                                            />
                                        </Field>
                                        <Field
                                            label="Эл.адрес"
                                            error={
                                                errors.deputy_dean_vr_email
                                            }
                                        >
                                            <TextInput
                                                type="email"
                                                value={
                                                    data.deputy_dean_vr_email
                                                }
                                                onChange={(event) =>
                                                    setField(
                                                        "deputy_dean_vr_email",
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full"
                                            />
                                        </Field>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section className="border-b border-gray-200 p-6">
                            <div className={sectionHeadingBlockClass}>
                                <h3 className={sectionHeadingTitleClass}>
                                    Студенты группы
                                </h3>
                                <p className={sectionHeadingDescriptionClass}>
                                    Список формируется из анкет студентов,
                                    которые выбрали эту группу.
                                </p>
                            </div>

                            {data.students.length === 0 ? (
                                <p className="rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-500 ring-1 ring-gray-200/70">
                                    Студенты появятся здесь после того, как выберут эту группу в анкете.
                                </p>
                            ) : (
                                <div className="overflow-hidden rounded-md border border-gray-200">
                                    <div className="hidden grid-cols-[48px_1.4fr_120px_120px_1.2fr_1.4fr_120px] gap-3 bg-gray-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 lg:grid">
                                        <span>№</span>
                                        <span>ФИО</span>
                                        <span>Дата рожд.</span>
                                        <span>ИИН</span>
                                        <span>Контакты</span>
                                        <span>Социальный статус</span>
                                        <span className="text-right">
                                            Действие
                                        </span>
                                    </div>
                                    <div className="divide-y divide-gray-200">
                                        {data.students.map((student, index) => (
                                            <div
                                                key={
                                                    student.profile_id ??
                                                    student.user_id ??
                                                    index
                                                }
                                                className="grid gap-3 px-4 py-4 text-sm lg:grid-cols-[48px_1.4fr_120px_120px_1.2fr_1.4fr_120px] lg:items-center"
                                            >
                                                <div className="font-semibold text-gray-500">
                                                    {index + 1}
                                                </div>
                                                <div>
                                                    <p className="font-medium text-gray-950">
                                                        {displayValue(
                                                            student.full_name,
                                                        )}
                                                    </p>
                                                    <p className="mt-1 text-xs text-gray-500 lg:hidden">
                                                        ИИН:{" "}
                                                        {displayValue(
                                                            student.iin,
                                                        )}
                                                    </p>
                                                </div>
                                                <div className="text-gray-700">
                                                    {displayValue(
                                                        student.birth_date,
                                                    )}
                                                </div>
                                                <div className="hidden text-gray-700 lg:block">
                                                    {displayValue(student.iin)}
                                                </div>
                                                <div className="text-gray-700">
                                                    {compactValue(
                                                        student.contact_details,
                                                        60,
                                                    )}
                                                </div>
                                                <div className="text-gray-700">
                                                    {compactValue(
                                                        student.social_status,
                                                        85,
                                                    )}
                                                </div>
                                                <div className="flex justify-start lg:justify-end">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setSelectedStudentIndex(
                                                                index,
                                                            )
                                                        }
                                                        className="inline-flex items-center justify-center rounded-md border border-[#355da8] px-3 py-2 text-sm font-semibold text-[#355da8] transition hover:bg-[#f4f7fc]"
                                                    >
                                                        Подробнее
                                                    </button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </section>

                        <section className="border-b border-gray-200 p-6">
                            <div className={sectionHeadingBlockClass}>
                                <h3 className={sectionHeadingTitleClass}>
                                    Социальный статус
                                </h3>
                                <p className={sectionHeadingDescriptionClass}>
                                    Показатели считаются автоматически по
                                    анкетам студентов этой группы.
                                </p>
                            </div>
                            <div className="divide-y divide-gray-200 rounded-md border border-gray-200">
                                {summaryFields.map(([key, label]) => (
                                    <div
                                        key={key}
                                        className="flex items-center justify-between gap-4 px-4 py-3"
                                    >
                                        <div>
                                            <p className="text-sm font-medium text-gray-700">
                                                {label}
                                            </p>
                                            <InputError
                                                message={
                                                    errors[`summary.${key}`]
                                                }
                                                className="mt-1"
                                            />
                                        </div>
                                        <TextInput
                                            type="number"
                                            min="0"
                                            value={valueOrEmpty(
                                                data.summary[key],
                                            )}
                                            readOnly
                                            className="w-24 shrink-0 bg-gray-50 text-right text-gray-700"
                                        />
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section className="border-b border-gray-200 p-6">
                            <div className={sectionHeadingBlockClass}>
                                <h3 className={sectionHeadingTitleClass}>
                                    Выбывшие студенты
                                </h3>
                                <p className={sectionHeadingDescriptionClass}>
                                    Список формируется автоматически из
                                    портретов студентов со статусом
                                    &quot;Выбыл&quot;.
                                </p>
                            </div>

                            {data.departed_students.length === 0 ? (
                                <p className="rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-500 ring-1 ring-gray-200/70">
                                    Выбывших студентов в этой группе нет.
                                </p>
                            ) : (
                                <div className="overflow-hidden rounded-md border border-gray-200">
                                    <div className="hidden grid-cols-[48px_1.4fr_1.2fr_1.2fr_120px_120px_1.4fr] gap-3 bg-gray-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 lg:grid">
                                        <span>№</span>
                                        <span>ФИО</span>
                                        <span>Факультет</span>
                                        <span>ОП</span>
                                        <span>Группа</span>
                                        <span>Дата</span>
                                        <span>Причина</span>
                                    </div>
                                    <div className="divide-y divide-gray-200">
                                        {data.departed_students.map(
                                            (student, index) => (
                                                <div
                                                    key={
                                                        student.profile_id ??
                                                        student.user_id ??
                                                        index
                                                    }
                                                    className="grid gap-3 px-4 py-4 text-sm lg:grid-cols-[48px_1.4fr_1.2fr_1.2fr_120px_120px_1.4fr] lg:items-center"
                                                >
                                                    <div className="font-semibold text-gray-500">
                                                        {index + 1}
                                                    </div>
                                                    <div className="font-medium text-gray-950">
                                                        {displayValue(
                                                            student.full_name,
                                                        )}
                                                    </div>
                                                    <div className="text-gray-700">
                                                        {compactValue(
                                                            student.faculty,
                                                            60,
                                                        )}
                                                    </div>
                                                    <div className="text-gray-700">
                                                        {compactValue(
                                                            student.education_program,
                                                            60,
                                                        )}
                                                    </div>
                                                    <div className="text-gray-700">
                                                        {displayValue(
                                                            student.group_name,
                                                        )}
                                                    </div>
                                                    <div className="text-gray-700">
                                                        {displayValue(
                                                            student.departed_at,
                                                        )}
                                                    </div>
                                                    <div className="text-gray-700">
                                                        {displayValue(
                                                            student.reason_label,
                                                        )}
                                                        {student.reason_other && (
                                                            <span className="block text-xs text-gray-500">
                                                                {
                                                                    student.reason_other
                                                                }
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </div>
                            )}
                        </section>

                        <div className="flex items-center justify-end gap-4 bg-gray-50 px-6 py-4">
                            {recentlySuccessful && (
                                <p className="text-sm text-gray-600">
                                    Сохранено
                                </p>
                            )}
                            <PrimaryButton disabled={processing}>
                                Сохранить
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>

            {selectedStudent && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/45 px-4 py-6">
                    <div className="max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-lg bg-white shadow-xl">
                        <div className="flex items-start justify-between gap-4 border-b border-[#dbe5f6] bg-[#edf3ff] px-6 py-4">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-[#426aa8]">
                                    Студент группы
                                </p>
                                <h3 className="mt-1 text-lg font-semibold text-[#274f93]">
                                    {displayValue(selectedStudent.full_name)}
                                </h3>
                            </div>
                            <button
                                type="button"
                                onClick={() => setSelectedStudentIndex(null)}
                                className="rounded-md px-3 py-2 text-sm font-semibold text-gray-500 transition hover:bg-gray-100 hover:text-gray-800"
                            >
                                Закрыть
                            </button>
                        </div>

                        <div className="max-h-[calc(90vh-150px)] overflow-y-auto px-6 py-5">
                            <div className="grid gap-4 md:grid-cols-2">
                                {studentColumns.map(([key, label]) => (
                                    <div
                                        key={key}
                                        className="rounded-md border border-gray-200 bg-gray-50 px-4 py-3"
                                    >
                                        <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            {label}
                                        </p>
                                        <p className="mt-1 whitespace-pre-wrap text-sm text-gray-900">
                                            {displayValue(
                                                selectedStudent[key],
                                            )}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
                            {selectedStudent.profile_url && (
                                <Link
                                    href={selectedStudent.profile_url}
                                    className="inline-flex items-center justify-center rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#2f5192]"
                                >
                                    Открыть портрет
                                </Link>
                            )}
                            <button
                                type="button"
                                onClick={() => setSelectedStudentIndex(null)}
                                className="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                            >
                                Закрыть
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
