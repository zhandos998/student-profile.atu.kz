import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';

export default function PlatonusLogin({
    status,
    csrfToken,
    errors = {},
    oldInput = {},
}) {
    return (
        <GuestLayout>
            <Head title="Вход через Платонус" />

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <form method="post" action={route('login')}>
                <input type="hidden" name="_token" value={csrfToken} />
                <input type="hidden" name="auth_type" value="platonus" />

                <div className="mb-4 rounded-md border border-[#355da8]/20 bg-[#355da8]/5 px-3 py-2 text-sm font-semibold text-[#355da8]">
                    Вход через Платонус
                </div>

                <div>
                    <InputLabel htmlFor="login" value="Логин Платонуса" />

                    <TextInput
                        id="login"
                        type="text"
                        name="login"
                        className="mt-1 block w-full"
                        autoComplete="username"
                        placeholder="Например: 1Daulet_Rauan"
                        defaultValue={oldInput.login ?? ''}
                        isFocused={true}
                        required
                    />

                    <InputError message={errors.login} className="mt-2" />
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

                <div className="mt-5 flex items-center justify-between gap-3">
                    <Link
                        href={route('login')}
                        className="rounded-md text-sm font-medium text-[#355da8] underline hover:text-[#2f5192] focus:outline-none focus:ring-2 focus:ring-[#355da8] focus:ring-offset-2"
                    >
                        Email или телефон
                    </Link>

                    <PrimaryButton type="submit">Войти</PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
