import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head, Link } from '@inertiajs/react';

const primaryButton =
    'inline-flex items-center justify-center rounded-md bg-[#355da8] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2f5192] focus:outline-none focus:ring-2 focus:ring-[#355da8] focus:ring-offset-2';

const secondaryButton =
    'inline-flex items-center justify-center rounded-md border border-[#d7e1f3] bg-white px-5 py-3 text-sm font-semibold text-[#355da8] transition hover:bg-[#f5f8fd] focus:outline-none focus:ring-2 focus:ring-[#355da8] focus:ring-offset-2';

function StudentCard({ title, text }) {
    return (
        <article className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-[#dfe7f5]">
            <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4">
                <h3 className="text-base font-semibold text-[#274f93]">
                    {title}
                </h3>
            </div>
            <p className="p-5 text-sm leading-6 text-gray-600">{text}</p>
        </article>
    );
}

function PreviewItem({ title, text }) {
    return (
        <div className="rounded-lg bg-[#f6f9fe] p-4 ring-1 ring-[#e3ebf8]">
            <p className="text-sm font-semibold text-gray-950">{title}</p>
            <p className="mt-1 text-sm leading-6 text-gray-600">{text}</p>
        </div>
    );
}

function StudentPreview() {
    return (
        <div className="rounded-2xl bg-white p-5 shadow-xl shadow-[#1e3765]/15 ring-1 ring-[#dfe7f5]">
            <div className="flex items-center gap-3 border-b border-[#e7eef8] pb-4">
                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-[#eef4fc]">
                    <ApplicationLogo className="h-7 w-7" />
                </div>
                <div>
                    <p className="text-base font-semibold text-gray-950">
                        Личный кабинет студента
                    </p>
                    <p className="text-sm text-gray-500">
                        Все данные открываются после входа
                    </p>
                </div>
            </div>

            <div className="mt-5 grid gap-3">
                <PreviewItem
                    title="Моя анкета"
                    text="Основные данные, контакты, адрес проживания и социальный статус."
                />
                <PreviewItem
                    title="Мои достижения"
                    text="Олимпиады, конкурсы, спорт, волонтерство, проекты и публикации."
                />
                <PreviewItem
                    title="Мое портфолио"
                    text="Сертификаты, дипломы, грамоты, научные работы и видеоматериалы."
                />
            </div>

            <div className="mt-5 rounded-xl bg-[#355da8] p-4 text-white">
                <p className="text-sm font-semibold">Персональные данные</p>
                <p className="mt-1 text-sm leading-6 text-white/80">
                    Информация отображается только авторизованному пользователю
                    согласно его роли.
                </p>
            </div>
        </div>
    );
}

export default function Welcome({ auth, canLogin, canRegister }) {
    const isAuthenticated = Boolean(auth?.user);

    return (
        <>
            <Head title="ATU Student Profile" />

            <main className="min-h-screen bg-white text-gray-900">
                <header className="border-b border-[#e7eef8] bg-white">
                    <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                        <Link
                            href="/"
                            className="flex min-w-0 items-center gap-3"
                        >
                            <ApplicationLogo
                                variant="wordmark"
                                className="h-10 w-auto max-w-[160px] shrink-0 sm:h-11 sm:max-w-[210px]"
                                alt="Almaty Technological University"
                            />
                            <div className="min-w-0">
                                <p className="hidden text-xs text-gray-500 sm:block">
                                    Личный цифровой профиль студента
                                </p>
                            </div>
                        </Link>

                        <nav className="flex shrink-0 items-center gap-2">
                            {isAuthenticated ? (
                                <Link
                                    href={route('dashboard')}
                                    className={primaryButton}
                                >
                                    Открыть кабинет
                                </Link>
                            ) : (
                                <>
                                    {canLogin && (
                                        <Link
                                            href={route('login')}
                                            className={primaryButton}
                                        >
                                            Войти
                                        </Link>
                                    )}
                                    {canRegister && (
                                        <Link
                                            href={route('register')}
                                            className="hidden rounded-md px-4 py-3 text-sm font-semibold text-[#355da8] transition hover:bg-[#f5f8fd] sm:inline-flex"
                                        >
                                            Регистрация
                                        </Link>
                                    )}
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <section className="bg-[#f5f8fd]">
                    <div className="mx-auto grid max-w-7xl items-center gap-10 px-4 py-10 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8 lg:py-16">
                        <div>
                            <p className="text-sm font-semibold uppercase tracking-wide text-[#355da8]">
                                Кабинет студента АТУ
                            </p>
                            <h1 className="mt-4 max-w-2xl text-4xl font-semibold tracking-normal text-gray-950 sm:text-5xl">
                                Заполняйте анкету, собирайте портфолио и
                                следите за личным профилем
                            </h1>
                            <p className="mt-5 max-w-xl text-base leading-7 text-gray-600">
                                На этой платформе студент может обновлять свои
                                данные, добавлять достижения и хранить документы
                                портфолио в одном месте.
                            </p>

                            <div className="mt-7 flex flex-col gap-3 sm:flex-row">
                                <Link
                                    href={
                                        isAuthenticated
                                            ? route('dashboard')
                                            : route('login')
                                    }
                                    className={primaryButton}
                                >
                                    {isAuthenticated
                                        ? 'Перейти в кабинет'
                                        : 'Войти в личный кабинет'}
                                </Link>
                                {!isAuthenticated && canRegister && (
                                    <Link
                                        href={route('register')}
                                        className={secondaryButton}
                                    >
                                        Создать аккаунт
                                    </Link>
                                )}
                            </div>
                        </div>

                        <StudentPreview />
                    </div>
                </section>

                <section className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                    <div className="grid gap-4 md:grid-cols-3">
                        <StudentCard
                            title="Анкета студента"
                            text="ФИО, контакты, факультет, группа, адреса проживания и другие личные сведения."
                        />
                        <StudentCard
                            title="Достижения"
                            text="Участие в конкурсах, олимпиадах, соревнованиях, клубах, проектах и волонтерстве."
                        />
                        <StudentCard
                            title="Портфолио"
                            text="Загрузка сертификатов, дипломов, грамот, проектов, научных работ и видео."
                        />
                    </div>
                </section>

                <section className="border-t border-[#e7eef8] bg-white">
                    <div className="mx-auto flex max-w-7xl flex-col gap-5 px-4 py-10 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                        <div>
                            <h2 className="text-2xl font-semibold text-gray-950">
                                Начните с входа в личный кабинет
                            </h2>
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-gray-600">
                                После авторизации система покажет только те
                                разделы, которые доступны вашей роли.
                            </p>
                        </div>

                        <Link
                            href={
                                isAuthenticated
                                    ? route('dashboard')
                                    : route('login')
                            }
                            className={primaryButton}
                        >
                            {isAuthenticated
                                ? 'Открыть кабинет'
                                : 'Перейти ко входу'}
                        </Link>
                    </div>
                </section>
            </main>
        </>
    );
}
