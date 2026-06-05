import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const valueOrEmpty = (value) => value ?? '';

const studentColumns = [
    ['full_name', 'ФИО'],
    ['birth_date', 'Дата рождения'],
    ['study_form', 'Форма обучения'],
    ['nationality', 'Национальность'],
    ['iin', 'ИИН'],
    ['identity_document_number', '№ уд.личн.'],
    ['contact_details', 'Контактные данные'],
    ['stay_address', 'Адрес пребывания'],
    ['residence_address', 'Адрес проживания'],
    ['parent_details', 'Сведения о родителях'],
    ['social_status', 'Социальный статус'],
    ['religion_details', 'Вероисповедание'],
];

const summaryFields = [
    ['disabled_students', 'Студенты инвалиды'],
    ['orphan_students', 'Студенты сироты'],
    ['incomplete_family_students', 'Студенты из неполной семьи'],
    ['large_family_students', 'Студенты из многодетной семьи'],
    ['low_income_students', 'Студенты из малообеспеченной семьи'],
    ['married_students', 'Семейные студенты'],
    ['foreign_students', 'Студенты иностранцы'],
    ['dormitory_students', 'Студенты, проживающие в общежитии'],
    ['relatives_living_students', 'Студенты, проживающие у родственников'],
    ['rental_housing_students', 'Студенты, арендующие жилье'],
    ['total_students', 'Общее количество студентов в группе'],
];

const departedStudentColumns = [
    ['full_name', 'ФИО'],
    ['faculty', 'Факультет'],
    ['education_program', 'Образовательная программа'],
    ['group_name', 'Группа'],
    ['course', 'Курс'],
    ['reason', 'Причина'],
    ['reason_other', 'Другое'],
];

const departureReasons = [
    ['transferred', 'Переведен в другой университет'],
    ['expelled', 'Отчислен'],
    ['deported', 'Депортирован'],
    ['death', 'Смерть'],
    ['other', 'Другое'],
];

function emptyStudentRow() {
    return Object.fromEntries(studentColumns.map(([key]) => [key, '']));
}

function emptyDepartedStudentRow() {
    return Object.fromEntries(
        departedStudentColumns.map(([key]) => [key, '']),
    );
}

function emptySummary() {
    return Object.fromEntries(summaryFields.map(([key]) => [key, '']));
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

function TextAreaCell({ value, onChange }) {
    return (
        <textarea
            value={value}
            onChange={onChange}
            rows={2}
            className="w-64 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        />
    );
}

export default function Edit({ passport }) {
    const [data, setData] = useState({
        group_name: valueOrEmpty(passport.group_name),
        leader_full_name: valueOrEmpty(passport.leader_full_name),
        leader_phone: valueOrEmpty(passport.leader_phone),
        leader_email: valueOrEmpty(passport.leader_email),
        curator_full_name: valueOrEmpty(passport.curator_full_name),
        curator_phone: valueOrEmpty(passport.curator_phone),
        curator_email: valueOrEmpty(passport.curator_email),
        students:
            passport.students?.length > 0
                ? passport.students
                : [emptyStudentRow()],
        summary: {
            ...emptySummary(),
            ...(passport.summary ?? {}),
        },
        departed_students:
            passport.departed_students?.length > 0
                ? passport.departed_students
                : [emptyDepartedStudentRow()],
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [recentlySuccessful, setRecentlySuccessful] = useState(false);

    const setField = (field, value) => {
        setData((current) => ({
            ...current,
            [field]: value,
        }));
    };

    const setStudentField = (index, field, value) => {
        setData((current) => ({
            ...current,
            students: current.students.map((student, studentIndex) =>
                studentIndex === index
                    ? { ...student, [field]: value }
                    : student,
            ),
        }));
    };

    const setSummaryField = (field, value) => {
        setData((current) => ({
            ...current,
            summary: {
                ...current.summary,
                [field]: value,
            },
        }));
    };

    const setDepartedStudentField = (index, field, value) => {
        setData((current) => ({
            ...current,
            departed_students: current.departed_students.map(
                (student, studentIndex) =>
                    studentIndex === index
                        ? { ...student, [field]: value }
                        : student,
            ),
        }));
    };

    const addStudent = () => {
        setData((current) => ({
            ...current,
            students: [...current.students, emptyStudentRow()],
        }));
    };

    const addDepartedStudent = () => {
        setData((current) => ({
            ...current,
            departed_students: [
                ...current.departed_students,
                emptyDepartedStudentRow(),
            ],
        }));
    };

    const removeStudent = (index) => {
        setData((current) => ({
            ...current,
            students:
                current.students.length === 1
                    ? [emptyStudentRow()]
                    : current.students.filter(
                          (_, studentIndex) => studentIndex !== index,
                      ),
        }));
    };

    const removeDepartedStudent = (index) => {
        setData((current) => ({
            ...current,
            departed_students:
                current.departed_students.length === 1
                    ? [emptyDepartedStudentRow()]
                    : current.departed_students.filter(
                          (_, studentIndex) => studentIndex !== index,
                      ),
        }));
    };

    const submit = (event) => {
        event.preventDefault();

        router.post(route('group-social-passport.update'), data, {
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
                    <form
                        onSubmit={submit}
                        className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                    >
                        <section className="border-b border-gray-200 p-6">
                            <div className="space-y-6">
                                <div>
                                    <h3 className="mb-5 text-base font-semibold text-gray-900">
                                        Группа
                                    </h3>
                                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                <Field label="Группа" error={errors.group_name}>
                                    <TextInput
                                        value={data.group_name}
                                        onChange={(event) =>
                                            setField('group_name', event.target.value)
                                        }
                                        className="w-full"
                                    />
                                </Field>
                                    </div>
                                </div>

                                <div className="border-t border-gray-100 pt-6">
                                    <h3 className="mb-5 text-base font-semibold text-gray-900">
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
                                            setField('leader_full_name', event.target.value)
                                        }
                                        className="w-full"
                                    />
                                </Field>
                                <Field label="Телефон" error={errors.leader_phone}>
                                    <TextInput
                                        value={data.leader_phone}
                                        onChange={(event) =>
                                            setField('leader_phone', event.target.value)
                                        }
                                        className="w-full"
                                    />
                                </Field>
                                <Field label="Эл.адрес" error={errors.leader_email}>
                                    <TextInput
                                        type="email"
                                        value={data.leader_email}
                                        onChange={(event) =>
                                            setField('leader_email', event.target.value)
                                        }
                                        className="w-full"
                                    />
                                </Field>
                                    </div>
                                </div>

                                <div className="border-t border-gray-100 pt-6">
                                    <h3 className="mb-5 text-base font-semibold text-gray-900">
                                        Куратор
                                    </h3>
                                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                <Field
                                    label="ФИО"
                                    error={errors.curator_full_name}
                                >
                                    <TextInput
                                        value={data.curator_full_name}
                                        onChange={(event) =>
                                            setField('curator_full_name', event.target.value)
                                        }
                                        className="w-full"
                                    />
                                </Field>
                                <Field label="Телефон" error={errors.curator_phone}>
                                    <TextInput
                                        value={data.curator_phone}
                                        onChange={(event) =>
                                            setField('curator_phone', event.target.value)
                                        }
                                        className="w-full"
                                    />
                                </Field>
                                <Field label="Эл.адрес" error={errors.curator_email}>
                                    <TextInput
                                        type="email"
                                        value={data.curator_email}
                                        onChange={(event) =>
                                            setField('curator_email', event.target.value)
                                        }
                                        className="w-full"
                                    />
                                </Field>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section className="border-b border-gray-200 p-6">
                            <div className="mb-5 flex items-center justify-between gap-4">
                                <h3 className="text-base font-semibold text-gray-900">
                                    Студенты группы
                                </h3>
                                <SecondaryButton type="button" onClick={addStudent}>
                                    Добавить студента
                                </SecondaryButton>
                            </div>

                            <div className="space-y-5">
                                {data.students.map((student, index) => (
                                    <div
                                        key={index}
                                        className="rounded-md border border-gray-200"
                                    >
                                        <div className="flex items-center justify-between gap-4 border-b border-gray-200 bg-gray-50 px-4 py-3">
                                            <h4 className="text-sm font-semibold text-gray-900">
                                                Студент №{index + 1}
                                            </h4>
                                            <DangerButton
                                                type="button"
                                                onClick={() =>
                                                    removeStudent(index)
                                                }
                                            >
                                                Удалить
                                            </DangerButton>
                                        </div>

                                        <div className="space-y-6 p-4">
                                            <div>
                                                <h5 className="mb-4 text-sm font-medium text-gray-700">
                                                    Основные данные
                                                </h5>
                                                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                                    <Field label="ФИО">
                                                        <TextInput
                                                            value={valueOrEmpty(
                                                                student.full_name,
                                                            )}
                                                            onChange={(event) =>
                                                                setStudentField(
                                                                    index,
                                                                    'full_name',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="w-full"
                                                        />
                                                    </Field>

                                                    <Field label="Дата рождения">
                                                        <TextInput
                                                            type="date"
                                                            value={valueOrEmpty(
                                                                student.birth_date,
                                                            )}
                                                            onChange={(event) =>
                                                                setStudentField(
                                                                    index,
                                                                    'birth_date',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="w-full"
                                                        />
                                                    </Field>

                                                    <Field label="Форма обучения">
                                                        <TextInput
                                                            value={valueOrEmpty(
                                                                student.study_form,
                                                            )}
                                                            onChange={(event) =>
                                                                setStudentField(
                                                                    index,
                                                                    'study_form',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="w-full"
                                                        />
                                                    </Field>

                                                    <Field label="Национальность">
                                                        <TextInput
                                                            value={valueOrEmpty(
                                                                student.nationality,
                                                            )}
                                                            onChange={(event) =>
                                                                setStudentField(
                                                                    index,
                                                                    'nationality',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="w-full"
                                                        />
                                                    </Field>

                                                    <Field label="ИИН">
                                                        <TextInput
                                                            value={valueOrEmpty(
                                                                student.iin,
                                                            )}
                                                            onChange={(event) =>
                                                                setStudentField(
                                                                    index,
                                                                    'iin',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            maxLength="12"
                                                            className="w-full"
                                                        />
                                                    </Field>

                                                    <Field label="№ уд.личн.">
                                                        <TextInput
                                                            value={valueOrEmpty(
                                                                student.identity_document_number,
                                                            )}
                                                            onChange={(event) =>
                                                                setStudentField(
                                                                    index,
                                                                    'identity_document_number',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="w-full"
                                                        />
                                                    </Field>
                                                </div>
                                            </div>

                                            <div className="border-t border-gray-100 pt-5">
                                                <h5 className="mb-4 text-sm font-medium text-gray-700">
                                                    Контакты и адреса
                                                </h5>
                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <Field label="Контактные данные">
                                                        <textarea
                                                            value={valueOrEmpty(
                                                                student.contact_details,
                                                            )}
                                                            onChange={(event) =>
                                                                setStudentField(
                                                                    index,
                                                                    'contact_details',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            rows={3}
                                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        />
                                                    </Field>

                                                    <Field label="Адрес пребывания">
                                                        <textarea
                                                            value={valueOrEmpty(
                                                                student.stay_address,
                                                            )}
                                                            onChange={(event) =>
                                                                setStudentField(
                                                                    index,
                                                                    'stay_address',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            rows={3}
                                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        />
                                                    </Field>

                                                    <Field label="Адрес проживания">
                                                        <textarea
                                                            value={valueOrEmpty(
                                                                student.residence_address,
                                                            )}
                                                            onChange={(event) =>
                                                                setStudentField(
                                                                    index,
                                                                    'residence_address',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            rows={3}
                                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        />
                                                    </Field>
                                                </div>
                                            </div>

                                            <div className="border-t border-gray-100 pt-5">
                                                <h5 className="mb-4 text-sm font-medium text-gray-700">
                                                    Семья и социальные сведения
                                                </h5>
                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <Field label="Сведения о родителях">
                                                        <textarea
                                                            value={valueOrEmpty(
                                                                student.parent_details,
                                                            )}
                                                            onChange={(event) =>
                                                                setStudentField(
                                                                    index,
                                                                    'parent_details',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            rows={3}
                                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        />
                                                    </Field>

                                                    <Field label="Социальный статус">
                                                        <textarea
                                                            value={valueOrEmpty(
                                                                student.social_status,
                                                            )}
                                                            onChange={(event) =>
                                                                setStudentField(
                                                                    index,
                                                                    'social_status',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            rows={3}
                                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        />
                                                    </Field>

                                                    <Field label="Вероисповедание">
                                                        <textarea
                                                            value={valueOrEmpty(
                                                                student.religion_details,
                                                            )}
                                                            onChange={(event) =>
                                                                setStudentField(
                                                                    index,
                                                                    'religion_details',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            rows={3}
                                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        />
                                                    </Field>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section className="border-b border-gray-200 p-6">
                            <h3 className="mb-5 text-base font-semibold text-gray-900">
                                Социальный статус
                            </h3>
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
                                            onChange={(event) =>
                                                setSummaryField(
                                                    key,
                                                    event.target.value,
                                                )
                                            }
                                            className="w-24 shrink-0 text-right"
                                        />
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section className="border-b border-gray-200 p-6">
                            <div className="mb-5 flex items-center justify-between gap-4">
                                <h3 className="text-base font-semibold text-gray-900">
                                    Выбывшие студенты
                                </h3>
                                <SecondaryButton
                                    type="button"
                                    onClick={addDepartedStudent}
                                >
                                    Добавить студента
                                </SecondaryButton>
                            </div>

                            <div className="space-y-5">
                                {data.departed_students.map(
                                    (student, index) => (
                                        <div
                                            key={index}
                                            className="rounded-md border border-gray-200"
                                        >
                                            <div className="flex items-center justify-between gap-4 border-b border-gray-200 bg-gray-50 px-4 py-3">
                                                <h4 className="text-sm font-semibold text-gray-900">
                                                    Выбывший студент №
                                                    {index + 1}
                                                </h4>
                                                <DangerButton
                                                    type="button"
                                                    onClick={() =>
                                                        removeDepartedStudent(
                                                            index,
                                                        )
                                                    }
                                                >
                                                    Удалить
                                                </DangerButton>
                                            </div>

                                            <div className="space-y-6 p-4">
                                                <div>
                                                    <h5 className="mb-4 text-sm font-medium text-gray-700">
                                                        Данные студента
                                                    </h5>
                                                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                                        <Field label="ФИО">
                                                            <TextInput
                                                                value={valueOrEmpty(
                                                                    student.full_name,
                                                                )}
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setDepartedStudentField(
                                                                        index,
                                                                        'full_name',
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                                className="w-full"
                                                            />
                                                        </Field>

                                                        <Field label="Факультет">
                                                            <TextInput
                                                                value={valueOrEmpty(
                                                                    student.faculty,
                                                                )}
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setDepartedStudentField(
                                                                        index,
                                                                        'faculty',
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                                className="w-full"
                                                            />
                                                        </Field>

                                                        <Field label="Образовательная программа">
                                                            <TextInput
                                                                value={valueOrEmpty(
                                                                    student.education_program,
                                                                )}
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setDepartedStudentField(
                                                                        index,
                                                                        'education_program',
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                                className="w-full"
                                                            />
                                                        </Field>

                                                        <Field label="Группа">
                                                            <TextInput
                                                                value={valueOrEmpty(
                                                                    student.group_name,
                                                                )}
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setDepartedStudentField(
                                                                        index,
                                                                        'group_name',
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                                className="w-full"
                                                            />
                                                        </Field>

                                                        <Field label="Курс">
                                                            <TextInput
                                                                type="number"
                                                                min="1"
                                                                max="8"
                                                                value={valueOrEmpty(
                                                                    student.course,
                                                                )}
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setDepartedStudentField(
                                                                        index,
                                                                        'course',
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                                className="w-full"
                                                            />
                                                        </Field>
                                                    </div>
                                                </div>

                                                <div className="border-t border-gray-100 pt-5">
                                                    <h5 className="mb-4 text-sm font-medium text-gray-700">
                                                        Причина выбытия
                                                    </h5>
                                                    <div className="grid gap-4 md:grid-cols-2">
                                                        <Field label="Причина">
                                                            <select
                                                                value={valueOrEmpty(
                                                                    student.reason,
                                                                )}
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setDepartedStudentField(
                                                                        index,
                                                                        'reason',
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                            >
                                                                <option value="">
                                                                    Не указано
                                                                </option>
                                                                {departureReasons.map(
                                                                    ([
                                                                        value,
                                                                        label,
                                                                    ]) => (
                                                                        <option
                                                                            key={
                                                                                value
                                                                            }
                                                                            value={
                                                                                value
                                                                            }
                                                                        >
                                                                            {
                                                                                label
                                                                            }
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                        </Field>

                                                        <Field label="Другое">
                                                            <textarea
                                                                value={valueOrEmpty(
                                                                    student.reason_other,
                                                                )}
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setDepartedStudentField(
                                                                        index,
                                                                        'reason_other',
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                                rows={3}
                                                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                            />
                                                        </Field>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        </section>

                        <div className="flex items-center justify-end gap-4 bg-gray-50 px-6 py-4">
                            {recentlySuccessful && (
                                <p className="text-sm text-gray-600">Сохранено</p>
                            )}
                            <PrimaryButton disabled={processing}>Сохранить</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
