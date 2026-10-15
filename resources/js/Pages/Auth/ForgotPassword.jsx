import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

const copy = {
    ru: {
        title: 'Восстановление пароля',
        description: 'Укажите электронную почту. Мы отправим ссылку для восстановления пароля.',
        email: 'Электронная почта',
        submit: 'Отправить ссылку',
    },
    kk: {
        title: 'Құпия сөзді қалпына келтіру',
        description: 'Электрондық поштаңызды көрсетіңіз. Құпия сөзді қалпына келтіру сілтемесін жібереміз.',
        email: 'Электрондық пошта',
        submit: 'Сілтемені жіберу',
    },
    en: {
        title: 'Reset password',
        description: 'Enter your email address and we will send you a password reset link.',
        email: 'Email address',
        submit: 'Send reset link',
    },
};

export default function ForgotPassword({ status }) {
    const { locale = 'ru' } = usePage().props;
    const labels = copy[locale] ?? copy.ru;
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title={labels.title} />

            <div className="mb-4 text-sm text-gray-600">
                {labels.description}
            </div>

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <form onSubmit={submit}>
                <InputLabel htmlFor="email" value={labels.email} />
                <TextInput
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    className="mt-1 block w-full"
                    isFocused={true}
                    autoComplete="email"
                    onChange={(e) => setData('email', e.target.value)}
                />

                <InputError message={errors.email} className="mt-2" />

                <div className="mt-4 flex items-center justify-end">
                    <PrimaryButton className="ms-4" disabled={processing}>
                        {labels.submit}
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
