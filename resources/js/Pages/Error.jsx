import ApplicationLogo from '@/Components/ApplicationLogo';
import LanguageDomTranslator from '@/i18n/LanguageDomTranslator';
import { Head, Link } from '@inertiajs/react';

const errorContent = {
    403: {
        title: 'Доступ запрещен',
        description: 'У вашей учетной записи нет доступа к этой странице.',
    },
    404: {
        title: 'Страница не найдена',
        description: 'Возможно, адрес указан неверно или страница была перемещена.',
    },
    419: {
        title: 'Сеанс завершен',
        description: 'Обновите страницу и войдите в систему повторно.',
    },
    500: {
        title: 'Ошибка сервера',
        description: 'Не удалось выполнить запрос. Попробуйте еще раз немного позже.',
    },
    503: {
        title: 'Сервис временно недоступен',
        description: 'На сайте ведутся технические работы. Попробуйте зайти позже.',
    },
};

export default function Error({ status, auth }) {
    const content = errorContent[status] ?? {
        title: 'Произошла ошибка',
        description: 'Не удалось открыть запрошенную страницу.',
    };
    const homeUrl = auth?.user ? route('dashboard') : '/';

    return (
        <>
            <LanguageDomTranslator />
            <Head title={`${status} - ${content.title}`} />

            <main className="flex min-h-screen items-center justify-center bg-[#f4f7fc] px-4 py-10 sm:px-6">
                <section className="w-full max-w-xl overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-[#dbe5f6]">
                    <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-6 py-5 sm:px-8">
                        <ApplicationLogo
                            variant="wordmark"
                            className="h-11 w-auto max-w-[220px]"
                            alt="Almaty Technological University"
                        />
                    </div>

                    <div className="px-6 py-9 sm:px-8 sm:py-11">
                        <p className="text-sm font-semibold text-[#355da8]">
                            Ошибка {status}
                        </p>
                        <h1 className="mt-2 text-2xl font-semibold text-gray-950 sm:text-3xl">
                            {content.title}
                        </h1>
                        <p className="mt-3 text-base leading-7 text-gray-600">
                            {content.description}
                        </p>

                        <div className="mt-8 flex flex-wrap gap-3">
                            <Link
                                href={homeUrl}
                                className="inline-flex min-h-11 items-center justify-center rounded-md bg-[#355da8] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#2f5192] focus:outline-none focus:ring-2 focus:ring-[#355da8] focus:ring-offset-2"
                            >
                                Домой
                            </Link>
                            <button
                                type="button"
                                onClick={() => window.history.back()}
                                className="inline-flex min-h-11 items-center justify-center rounded-md border border-[#cad7eb] bg-white px-5 py-2.5 text-sm font-semibold text-[#274f93] transition hover:bg-[#f5f8fd] focus:outline-none focus:ring-2 focus:ring-[#355da8] focus:ring-offset-2"
                            >
                                Назад
                            </button>
                        </div>
                    </div>
                </section>
            </main>
        </>
    );
}
