import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const inputClass =
    'w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]';

function valueOrDash(value) {
    return value === null || value === undefined || value === '' ? '—' : value;
}

function roleBadgeClass(roleSlug) {
    if (roleSlug === 'administrator_dit') {
        return 'bg-[#355da8] text-white';
    }

    if (roleSlug === 'student') {
        return 'bg-gray-100 text-gray-700';
    }

    return 'bg-[#edf3ff] text-[#274f93]';
}

export default function Index({
    users,
    filters = { search: '', role: '' },
    roleOptions = [],
}) {
    const [filterData, setFilterData] = useState(filters);

    const submitFilters = (event) => {
        event.preventDefault();

        router.get(route('users.index'), filterData, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const resetFilters = () => {
        const emptyFilters = {
            search: '',
            role: '',
        };

        setFilterData(emptyFilters);
        router.get(route('users.index'), emptyFilters, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const impersonate = (user) => {
        if (!user.canImpersonate) {
            return;
        }

        router.post(route('users.impersonate', user.id), {}, {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Пользователи
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Просмотр всех аккаунтов и вход от имени пользователя для
                        проверки доступа.
                    </p>
                </div>
            }
        >
            <Head title="Пользователи" />

            <div className="bg-[#f4f7fc] py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200/80">
                        <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4">
                            <h3 className="text-base font-semibold text-[#274f93]">
                                Фильтр пользователей
                            </h3>
                        </div>
                        <form
                            onSubmit={submitFilters}
                            className="grid gap-4 p-5 md:grid-cols-[1fr_260px_auto]"
                        >
                            <div>
                                <label className="text-sm font-medium text-gray-700">
                                    Поиск
                                </label>
                                <input
                                    value={filterData.search}
                                    onChange={(event) =>
                                        setFilterData((current) => ({
                                            ...current,
                                            search: event.target.value,
                                        }))
                                    }
                                    placeholder="ФИО, email, телефон, логин Платонуса"
                                    className={`${inputClass} mt-1`}
                                />
                            </div>

                            <div>
                                <label className="text-sm font-medium text-gray-700">
                                    Роль
                                </label>
                                <select
                                    value={filterData.role}
                                    onChange={(event) =>
                                        setFilterData((current) => ({
                                            ...current,
                                            role: event.target.value,
                                        }))
                                    }
                                    className={`${inputClass} mt-1`}
                                >
                                    <option value="">Все роли</option>
                                    {roleOptions.map((role) => (
                                        <option
                                            key={role.value}
                                            value={role.value}
                                        >
                                            {role.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex items-end gap-3">
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
                    </section>

                    <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200/80">
                        <div className="flex flex-col gap-2 border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <h3 className="text-base font-semibold text-[#274f93]">
                                Список пользователей
                            </h3>
                            <span className="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-[#355da8] ring-1 ring-[#c9d8f0]">
                                {users.total}
                            </span>
                        </div>

                        {users.data.length === 0 ? (
                            <p className="px-5 py-6 text-sm text-gray-500">
                                Пользователи не найдены.
                            </p>
                        ) : (
                            <div className="divide-y divide-gray-100">
                                {users.data.map((user) => (
                                    <div
                                        key={user.id}
                                        className="grid gap-4 px-5 py-4 xl:grid-cols-[1.15fr_1fr_0.8fr_0.8fr_auto] xl:items-center"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold text-gray-900">
                                                {user.name}
                                            </p>
                                            <p className="mt-1 truncate text-sm text-gray-500">
                                                {valueOrDash(user.email)}
                                            </p>
                                        </div>

                                        <div className="flex flex-wrap gap-2">
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-semibold ${roleBadgeClass(
                                                    user.roleSlug,
                                                )}`}
                                            >
                                                {user.roleName}
                                            </span>
                                            {user.position && (
                                                <span className="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                                                    {user.position}
                                                </span>
                                            )}
                                        </div>

                                        <div className="text-sm text-gray-700">
                                            <p>
                                                Телефон:{' '}
                                                {valueOrDash(user.phone)}
                                            </p>
                                            <p className="mt-1 text-gray-500">
                                                ID: {user.id}
                                            </p>
                                        </div>

                                        <div className="text-sm text-gray-700">
                                            <p>
                                                Платонус:{' '}
                                                {valueOrDash(
                                                    user.platonusLogin,
                                                )}
                                            </p>
                                            <p className="mt-1 text-gray-500">
                                                Создан: {valueOrDash(user.createdAt)}
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            onClick={() => impersonate(user)}
                                            disabled={!user.canImpersonate}
                                            className="inline-flex items-center justify-center rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2f5192] disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-500 disabled:shadow-none"
                                        >
                                            Войти как
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}

                        {users.links.length > 3 && (
                            <div className="flex flex-wrap gap-2 border-t border-gray-200 px-5 py-4">
                                {users.links.map((link, index) => (
                                    <Link
                                        key={`${link.label}-${index}`}
                                        href={link.url ?? '#'}
                                        preserveScroll
                                        className={`rounded-md px-3 py-2 text-sm ${
                                            link.active
                                                ? 'bg-[#355da8] text-white'
                                                : 'bg-white text-gray-700 ring-1 ring-gray-200'
                                        } ${
                                            link.url
                                                ? 'hover:bg-[#f4f7fc]'
                                                : 'pointer-events-none opacity-50'
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
