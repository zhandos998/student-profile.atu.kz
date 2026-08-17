import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import LanguageDomTranslator from '@/i18n/LanguageDomTranslator';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
    const page = usePage();
    const {
        user,
        canViewGroupSocialPassport,
        canViewAnalyticsDashboard,
        canManageStudentProfiles,
        canUseOwnStudentProfile,
        canManageUsers,
    } = page.props.auth;
    const impersonation = page.props.impersonation || { active: false };
    const roleSlug = user?.role?.slug;
    const roleName = user?.role?.name || user?.position || 'Пользователь';
    const useSidebar = Boolean(user && roleSlug !== 'student');
    const navItems = buildNavItems({
        canManageStudentProfiles,
        canManageUsers,
        canUseOwnStudentProfile,
        canViewAnalyticsDashboard,
        canViewGroupSocialPassport,
    });

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    return (
        <div
            className={
                useSidebar ? 'min-h-screen bg-[#f4f7fc]' : 'min-h-screen bg-white'
            }
        >
            <LanguageDomTranslator />

            {useSidebar && (
                <DesktopSidebar
                    navItems={navItems}
                    roleName={roleName}
                    user={user}
                />
            )}

            <div className={useSidebar ? 'lg:pl-72' : ''}>
                <TopNavigation
                    className={useSidebar ? 'lg:hidden' : ''}
                    navItems={navItems}
                    roleName={roleName}
                    showingNavigationDropdown={showingNavigationDropdown}
                    setShowingNavigationDropdown={setShowingNavigationDropdown}
                    user={user}
                />

                {impersonation.active && (
                    <ImpersonationBanner
                        impersonator={impersonation.impersonator}
                        user={user}
                    />
                )}

                {header && (
                    <header className="sticky top-0 z-30 border-b border-[#dbe5f6] bg-white shadow-sm">
                        <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            {header}
                        </div>
                    </header>
                )}

                <main>{children}</main>
            </div>
        </div>
    );
}

function buildNavItems({
    canManageStudentProfiles,
    canManageUsers,
    canUseOwnStudentProfile,
    canViewAnalyticsDashboard,
    canViewGroupSocialPassport,
}) {
    const items = [
        {
            key: 'dashboard',
            href: route('dashboard'),
            active: route().current('dashboard'),
            label: 'Панель',
        },
        {
            key: 'instructions',
            href: route('instructions.index'),
            active: route().current('instructions.*'),
            label: 'Инструкция',
        },
    ];

    if (canUseOwnStudentProfile) {
        items.push({
            key: 'student-profile',
            href: route('student-profile.edit'),
            active: route().current('student-profile.*'),
            label: 'Портрет студента',
        });
    }

    if (canManageStudentProfiles) {
        items.push({
            key: 'student-profiles',
            href: route('student-profiles.index'),
            active: route().current('student-profiles.*'),
            label: 'Портреты студентов',
        });
    }

    if (canViewGroupSocialPassport) {
        items.push({
            key: 'groups',
            href: route('groups.index'),
            active:
                route().current('groups.*') ||
                route().current('group-social-passport.*'),
            label: 'Социальный паспорт группы',
        });
    }

    if (canViewAnalyticsDashboard) {
        items.push({
            key: 'analytics',
            href: route('analytics-dashboard.index'),
            active: route().current('analytics-dashboard.*'),
            label: 'Аналитика',
        });
    }

    if (canManageUsers) {
        items.push({
            key: 'users',
            href: route('users.index'),
            active: route().current('users.*'),
            label: 'Пользователи',
        });
    }

    return items;
}

function ImpersonationBanner({ impersonator, user }) {
    return (
        <div className="border-b border-amber-200 bg-amber-50">
            <div className="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-3 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <div>
                    <span className="font-semibold">
                        Режим входа как пользователь.
                    </span>{' '}
                    Сейчас открыт аккаунт:{' '}
                    <span className="font-semibold">{user.name}</span>
                    {impersonator?.name && (
                        <span className="text-amber-800">
                            {' '}
                            Исходный администратор: {impersonator.name}
                        </span>
                    )}
                </div>
                <Link
                    href={route('impersonation.stop')}
                    method="post"
                    as="button"
                    className="inline-flex w-full justify-center rounded-md border border-amber-300 bg-white px-3 py-2 text-sm font-semibold text-amber-900 shadow-sm transition hover:bg-amber-100 sm:w-auto"
                >
                    Вернуться в ДИТ
                </Link>
            </div>
        </div>
    );
}

function DesktopSidebar({ navItems, roleName, user }) {
    return (
        <aside className="fixed inset-y-0 left-0 z-40 hidden w-72 border-r border-[#dbe5f6] bg-white shadow-sm lg:flex lg:flex-col">
            <div className="flex min-h-0 flex-1 flex-col">
                <div className="border-b border-[#dbe5f6] px-5 py-5">
                    <Link href={route('dashboard')} className="block">
                        <ApplicationLogo
                            variant="wordmark"
                            className="h-11 w-auto max-w-[210px]"
                            alt="Almaty Technological University"
                        />
                    </Link>
                    <div className="mt-4 rounded-md border border-[#dbe5f6] bg-[#f4f7fc] px-3 py-2">
                        <div className="truncate text-sm font-semibold text-[#274f93]">
                            {user.name}
                        </div>
                        <div className="mt-0.5 truncate text-xs text-gray-500">
                            {roleName}
                        </div>
                    </div>
                </div>

                <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                    {navItems.map((item) => (
                        <SidebarLink key={item.key} item={item} />
                    ))}
                </nav>

                <div className="border-t border-[#dbe5f6] p-4">
                    <LanguageSwitcher />

                    <div className="mt-4 space-y-1">
                        <SidebarLink
                            item={{
                                href: route('profile.edit'),
                                active: route().current('profile.edit'),
                                label: 'Профиль',
                            }}
                        />
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="block w-full rounded-md px-3 py-2 text-left text-sm font-medium text-gray-600 transition hover:bg-[#f4f7fc] hover:text-[#355da8]"
                        >
                            Выйти
                        </Link>
                    </div>
                </div>
            </div>
        </aside>
    );
}

function SidebarLink({ item }) {
    return (
        <Link
            href={item.href}
            className={
                'block rounded-md px-3 py-2 text-sm font-medium transition ' +
                (item.active
                    ? 'bg-[#edf3ff] text-[#274f93] shadow-sm'
                    : 'text-gray-600 hover:bg-[#f4f7fc] hover:text-[#355da8]')
            }
        >
            {item.label}
        </Link>
    );
}

function TopNavigation({
    className = '',
    navItems,
    roleName,
    showingNavigationDropdown,
    setShowingNavigationDropdown,
    user,
}) {
    return (
        <nav className={`border-b border-[#dbe5f6] bg-white ${className}`}>
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex h-16 justify-between">
                    <div className="flex">
                        <div className="flex shrink-0 items-center">
                            <Link href="/">
                                <ApplicationLogo
                                    variant="wordmark"
                                    className="block h-9 w-auto max-w-[150px]"
                                    alt="Almaty Technological University"
                                />
                            </Link>
                        </div>

                        <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                            {navItems.map((item) => (
                                <NavLink
                                    key={item.key}
                                    href={item.href}
                                    active={item.active}
                                >
                                    {item.label}
                                </NavLink>
                            ))}
                        </div>
                    </div>

                    <div className="hidden sm:ms-6 sm:flex sm:items-center">
                        <LanguageSwitcher compact />

                        <div className="relative ms-3">
                            <Dropdown>
                                <Dropdown.Trigger>
                                    <span className="inline-flex rounded-md">
                                        <button
                                            type="button"
                                            className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-[#355da8] transition duration-150 ease-in-out hover:text-[#2f5192] focus:outline-none"
                                        >
                                            {user.name}

                                            <svg
                                                className="-me-0.5 ms-2 h-4 w-4"
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                            >
                                                <path
                                                    fillRule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clipRule="evenodd"
                                                />
                                            </svg>
                                        </button>
                                    </span>
                                </Dropdown.Trigger>

                                <Dropdown.Content>
                                    <Dropdown.Link href={route('profile.edit')}>
                                        Профиль
                                    </Dropdown.Link>
                                    <Dropdown.Link
                                        href={route('logout')}
                                        method="post"
                                        as="button"
                                    >
                                        Выйти
                                    </Dropdown.Link>
                                </Dropdown.Content>
                            </Dropdown>
                        </div>
                    </div>

                    <div className="-me-2 flex items-center sm:hidden">
                        <button
                            onClick={() =>
                                setShowingNavigationDropdown(
                                    (previousState) => !previousState,
                                )
                            }
                            className="inline-flex items-center justify-center rounded-md p-2 text-[#355da8] transition duration-150 ease-in-out hover:bg-[#f4f7fc] hover:text-[#2f5192] focus:bg-[#f4f7fc] focus:text-[#2f5192] focus:outline-none"
                        >
                            <svg
                                className="h-6 w-6"
                                stroke="currentColor"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    className={
                                        !showingNavigationDropdown
                                            ? 'inline-flex'
                                            : 'hidden'
                                    }
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                                <path
                                    className={
                                        showingNavigationDropdown
                                            ? 'inline-flex'
                                            : 'hidden'
                                    }
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <MobileNavigation
                navItems={navItems}
                roleName={roleName}
                showingNavigationDropdown={showingNavigationDropdown}
                user={user}
            />
        </nav>
    );
}

function MobileNavigation({
    navItems,
    roleName,
    showingNavigationDropdown,
    user,
}) {
    return (
        <div
            className={
                (showingNavigationDropdown ? 'block' : 'hidden') +
                ' sm:hidden'
            }
        >
            <div className="space-y-1 pb-3 pt-2">
                {navItems.map((item) => (
                    <ResponsiveNavLink
                        key={item.key}
                        href={item.href}
                        active={item.active}
                    >
                        {item.label}
                    </ResponsiveNavLink>
                ))}
            </div>

            <div className="border-t border-[#dbe5f6] pb-1 pt-4">
                <div className="px-4">
                    <div className="mb-3">
                        <LanguageSwitcher />
                    </div>
                    <div className="text-base font-medium text-gray-800">
                        {user.name}
                    </div>
                    <div className="text-sm font-medium text-gray-500">
                        {user.email || roleName}
                    </div>
                </div>

                <div className="mt-3 space-y-1">
                    <ResponsiveNavLink href={route('profile.edit')}>
                        Профиль
                    </ResponsiveNavLink>
                    <ResponsiveNavLink
                        method="post"
                        href={route('logout')}
                        as="button"
                    >
                        Выйти
                    </ResponsiveNavLink>
                </div>
            </div>
        </div>
    );
}
