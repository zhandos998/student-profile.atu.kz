import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PrimaryButton from "@/Components/PrimaryButton";
import TextInput from "@/Components/TextInput";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, useForm } from "@inertiajs/react";

const inputClass =
    "w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]";

function Field({ label, error, children, className = "" }) {
    return (
        <div className={className}>
            <InputLabel value={label} />
            <div className="mt-1">{children}</div>
            <InputError message={error} className="mt-2" />
        </div>
    );
}

export default function Create({ options, availableGroups = [] }) {
    const form = useForm({
        name: "",
        email: "",
        password: "",
        full_name: "",
        faculty: "",
        student_group_id: "",
        group_name: "",
        specialty: "",
        course: "",
    });
    const visibleGroupOptions = availableGroups.filter(
        (group) =>
            !form.data.faculty ||
            !group.faculty ||
            group.faculty === form.data.faculty,
    );

    const setFaculty = (faculty) => {
        const selectedGroup = availableGroups.find(
            (group) =>
                String(group.value) === String(form.data.student_group_id),
        );

        form.setData({
            ...form.data,
            faculty,
            student_group_id:
                selectedGroup?.faculty && selectedGroup.faculty !== faculty
                    ? ""
                    : form.data.student_group_id,
            group_name:
                selectedGroup?.faculty && selectedGroup.faculty !== faculty
                    ? ""
                    : form.data.group_name,
        });
    };

    const setStudentGroupId = (studentGroupId) => {
        const selectedGroup = availableGroups.find(
            (group) => String(group.value) === String(studentGroupId),
        );

        form.setData({
            ...form.data,
            student_group_id: studentGroupId,
            group_name: selectedGroup?.name || selectedGroup?.label || "",
            faculty: selectedGroup?.faculty || form.data.faculty,
        });
    };

    const submit = (event) => {
        event.preventDefault();

        form.post(route("student-profiles.store"));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Создать портрет студента
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Создание учетной записи студента и первичной анкеты.
                        </p>
                    </div>
                    <Link
                        href={route("student-profiles.index")}
                        className="text-sm font-medium text-[#355da8] hover:text-[#2f5192]"
                    >
                        К списку портретов
                    </Link>
                </div>
            }
        >
            <Head title="Создать портрет студента" />

            <div className="bg-[#f4f7fc] py-8">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200/80"
                    >
                        <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-6 py-4">
                            <h3 className="text-base font-semibold text-[#274f93]">
                                Данные нового студента
                            </h3>
                        </div>

                        <div className="grid gap-5 p-6 md:grid-cols-2">
                            <Field
                                label="Имя пользователя"
                                error={form.errors.name}
                            >
                                <TextInput
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData("name", event.target.value)
                                    }
                                    className="w-full"
                                />
                            </Field>

                            <Field label="Email" error={form.errors.email}>
                                <TextInput
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setData(
                                            "email",
                                            event.target.value,
                                        )
                                    }
                                    className="w-full"
                                />
                            </Field>

                            <Field label="Пароль" error={form.errors.password}>
                                <TextInput
                                    type="password"
                                    value={form.data.password}
                                    onChange={(event) =>
                                        form.setData(
                                            "password",
                                            event.target.value,
                                        )
                                    }
                                    className="w-full"
                                />
                            </Field>

                            <Field
                                label="ФИО студента"
                                error={form.errors.full_name}
                            >
                                <TextInput
                                    value={form.data.full_name}
                                    onChange={(event) =>
                                        form.setData(
                                            "full_name",
                                            event.target.value,
                                        )
                                    }
                                    className="w-full"
                                />
                            </Field>

                            <Field
                                label="Факультет"
                                error={form.errors.faculty}
                                className="md:col-span-2"
                            >
                                <select
                                    value={form.data.faculty}
                                    onChange={(event) =>
                                        setFaculty(event.target.value)
                                    }
                                    className={inputClass}
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
                            </Field>

                            <Field
                                label="Группа"
                                error={
                                    form.errors.student_group_id ||
                                    form.errors.group_name
                                }
                            >
                                <select
                                    value={form.data.student_group_id}
                                    onChange={(event) =>
                                        setStudentGroupId(event.target.value)
                                    }
                                    disabled={availableGroups.length === 0}
                                    className={`${inputClass} disabled:cursor-not-allowed disabled:bg-gray-50`}
                                >
                                    <option
                                        value=""
                                        label={
                                            availableGroups.length === 0
                                                ? "Сначала создайте группу"
                                                : "Выберите группу"
                                        }
                                    >
                                        {availableGroups.length === 0
                                            ? "Сначала создайте группу"
                                            : "Выберите группу"}
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
                            </Field>

                            <Field label="Курс" error={form.errors.course}>
                                <TextInput
                                    type="number"
                                    min="1"
                                    max="8"
                                    value={form.data.course}
                                    onChange={(event) =>
                                        form.setData(
                                            "course",
                                            event.target.value,
                                        )
                                    }
                                    className="w-full"
                                />
                            </Field>

                            <Field
                                label="Специальность"
                                error={form.errors.specialty}
                                className="md:col-span-2"
                            >
                                <TextInput
                                    value={form.data.specialty}
                                    onChange={(event) =>
                                        form.setData(
                                            "specialty",
                                            event.target.value,
                                        )
                                    }
                                    className="w-full"
                                />
                            </Field>
                        </div>

                        <div className="flex items-center justify-end gap-4 border-t border-gray-100 bg-gray-50 px-6 py-4">
                            <Link
                                href={route("student-profiles.index")}
                                className="text-sm font-medium text-gray-600 hover:text-gray-900"
                            >
                                Отмена
                            </Link>
                            <PrimaryButton
                                type="submit"
                                disabled={form.processing}
                            >
                                Создать
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
