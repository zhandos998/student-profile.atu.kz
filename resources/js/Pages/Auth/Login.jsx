import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';

export default function Login({
    status,
    canResetPassword,
    csrfToken,
    errors = {},
    oldInput = {},
}) {
    return (
        <GuestLayout>
            <Head title="Вход" />

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <form method="post" action={route('login')}>
                <input type="hidden" name="_token" value={csrfToken} />
                <input type="hidden" name="auth_type" value="auto" />

                <div className="mb-4 rounded-md border border-[#355da8]/20 bg-[#355da8]/5 px-3 py-2 text-sm font-semibold text-[#355da8]">
                    Вход в систему
                </div>
                <p className="mb-4 text-sm text-gray-600">
                    Используйте email, номер телефона или логин Платонуса.
                </p>

                <div>
                    <InputLabel
                        htmlFor="email"
                        value="Email / телефон / логин Платонуса"
                    />

                    <TextInput
                        id="email"
                        type="text"
                        name="email"
                        className="mt-1 block w-full"
                        autoComplete="username"
                        placeholder="email@atu.kz, +7 700 000 00 00 или 1Daulet_Rauan"
                        defaultValue={oldInput.email ?? ''}
                        isFocused={true}
                        required
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Пароль" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        required
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4 block">
                    <label className="flex items-center">
                        <Checkbox name="remember" />
                        <span className="ms-2 text-sm text-gray-600">
                            Запомнить меня
                        </span>
                    </label>
                </div>

                <div className="mt-5 flex flex-wrap items-center justify-between gap-3">
                    {canResetPassword ? (
                        <Link
                            href={route('password.request')}
                            className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#355da8] focus:ring-offset-2"
                        >
                            Забыли пароль?
                        </Link>
                    ) : (
                        <span />
                    )}

                    <PrimaryButton type="submit">Войти</PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
