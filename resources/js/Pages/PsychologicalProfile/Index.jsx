import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

function TextAreaField({ label, value, error, onChange }) {
    return (
        <div>
            <InputLabel value={label} />
            <textarea
                value={value}
                onChange={onChange}
                rows={8}
                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <InputError message={error} className="mt-2" />
        </div>
    );
}

export default function Index({ profile }) {
    const form = useForm({
        testing_results: profile.testing_results ?? '',
        individual_features: profile.individual_features ?? '',
    });

    const submit = (event) => {
        event.preventDefault();

        form.post(route('psychological-profile.update'), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Психолого-педагогический профиль
                </h2>
            }
        >
            <Head title="Психолого-педагогический профиль" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                    >
                        <div className="space-y-6 p-6">
                            <TextAreaField
                                label="Результаты тестирований"
                                value={form.data.testing_results}
                                error={form.errors.testing_results}
                                onChange={(event) =>
                                    form.setData(
                                        'testing_results',
                                        event.target.value,
                                    )
                                }
                            />

                            <TextAreaField
                                label="Индивидуальные особенности"
                                value={form.data.individual_features}
                                error={form.errors.individual_features}
                                onChange={(event) =>
                                    form.setData(
                                        'individual_features',
                                        event.target.value,
                                    )
                                }
                            />
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
