import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

function Field({ label, error, children, className = '' }) {
    return (
        <div className={className}>
            <InputLabel value={label} />
            <div className="mt-1">{children}</div>
            <InputError message={error} className="mt-2" />
        </div>
    );
}

function SelectInput({ value, onChange, options, placeholder = 'Не указано' }) {
    return (
        <select
            value={value}
            onChange={onChange}
            className="w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
        >
            <option value="">{placeholder}</option>
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}

function TextAreaInput({ value, onChange, rows = 4 }) {
    return (
        <textarea
            value={value}
            onChange={onChange}
            rows={rows}
            className="w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]"
        />
    );
}

function FileLink({ href, label }) {
    return href ? (
        <a
            href={href}
            target="_blank"
            rel="noreferrer"
            className="mt-2 inline-flex text-sm font-medium text-[#355da8] hover:text-[#2f5192]"
        >
            {label}
        </a>
    ) : null;
}

export default function Index({ passport, options }) {
    const form = useForm({
        fluorography_date: passport.fluorography_date ?? '',
        fluorography_image: null,
        dispensary_accounting: passport.dispensary_accounting ?? '',
        diagnosis: passport.diagnosis ?? '',
        disability_group: passport.disability_group ?? '',
        psychological_diagnosis: passport.psychological_diagnosis ?? '',
        pregnancy: passport.pregnancy ?? '',
    });

    const submit = (event) => {
        event.preventDefault();

        form.post(route('health-passport.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.setData('fluorography_image', null),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Паспорт здоровья обучающегося
                </h2>
            }
        >
            <Head title="Паспорт здоровья обучающегося" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                    >
                        <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-6 py-4">
                            <h3 className="text-base font-semibold text-[#274f93]">
                                Паспорт здоровья обучающегося
                            </h3>
                        </div>

                        <div className="grid gap-5 p-6 md:grid-cols-2">
                            <Field
                                label="Флюорография: дата прохождения"
                                error={form.errors.fluorography_date}
                            >
                                <TextInput
                                    type="date"
                                    value={form.data.fluorography_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'fluorography_date',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full"
                                />
                            </Field>

                            <Field
                                label="Флюорография: фото снимка"
                                error={form.errors.fluorography_image}
                            >
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png"
                                    onChange={(event) =>
                                        form.setData(
                                            'fluorography_image',
                                            event.target.files[0],
                                        )
                                    }
                                    className="block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-200"
                                />
                                <FileLink
                                    href={passport.fluorography_image_url}
                                    label="Открыть текущий снимок"
                                />
                            </Field>

                            <Field
                                label="Диспансерный учет"
                                error={form.errors.dispensary_accounting}
                            >
                                <SelectInput
                                    value={form.data.dispensary_accounting}
                                    options={options.dispensaryAccounting}
                                    placeholder="Выберите значение"
                                    onChange={(event) =>
                                        form.setData(
                                            'dispensary_accounting',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>

                            <Field
                                label="Группа инвалидности"
                                error={form.errors.disability_group}
                            >
                                <SelectInput
                                    value={form.data.disability_group}
                                    options={options.disabilityGroups}
                                    placeholder="Не указано"
                                    onChange={(event) =>
                                        form.setData(
                                            'disability_group',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>

                            <Field
                                label="Диагноз"
                                error={form.errors.diagnosis}
                                className="md:col-span-2"
                            >
                                <TextAreaInput
                                    value={form.data.diagnosis}
                                    onChange={(event) =>
                                        form.setData(
                                            'diagnosis',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>

                            <Field
                                label="Психологический диагноз"
                                error={form.errors.psychological_diagnosis}
                                className="md:col-span-2"
                            >
                                <TextAreaInput
                                    value={form.data.psychological_diagnosis}
                                    onChange={(event) =>
                                        form.setData(
                                            'psychological_diagnosis',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>

                            <Field
                                label="Беременность"
                                error={form.errors.pregnancy}
                                className="md:col-span-2"
                            >
                                <TextAreaInput
                                    value={form.data.pregnancy}
                                    rows={3}
                                    onChange={(event) =>
                                        form.setData(
                                            'pregnancy',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </div>

                        <div className="flex items-center justify-end gap-4 border-t border-gray-200 bg-gray-50 px-6 py-4">
                            {form.recentlySuccessful && (
                                <p className="text-sm text-gray-600">
                                    Сохранено
                                </p>
                            )}
                            <PrimaryButton disabled={form.processing}>
                                Сохранить
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
