import { router, usePage } from '@inertiajs/react';

export default function LanguageSwitcher({ compact = false }) {
    const { locale = 'ru', availableLocales = [] } = usePage().props;

    const changeLocale = (nextLocale) => {
        if (nextLocale === locale) {
            return;
        }

        router.post(
            route('locale.update'),
            { locale: nextLocale },
            {
                preserveScroll: true,
                preserveState: false,
            },
        );
    };

    return (
        <div
            className={
                compact
                    ? 'inline-flex rounded-md border border-[#dbe5f6] bg-white p-0.5'
                    : 'inline-flex rounded-lg border border-[#dbe5f6] bg-[#f5f8fd] p-1'
            }
            aria-label="Выбор языка"
        >
            {availableLocales.map((item) => {
                const active = item.value === locale;

                return (
                    <button
                        key={item.value}
                        type="button"
                        onClick={() => changeLocale(item.value)}
                        className={
                            (compact
                                ? 'rounded px-2.5 py-1 text-xs'
                                : 'rounded-md px-3 py-1.5 text-sm') +
                            ' font-semibold transition ' +
                            (active
                                ? 'bg-[#355da8] text-white shadow-sm'
                                : 'text-[#355da8] hover:bg-white')
                        }
                    >
                        {item.value.toUpperCase()}
                    </button>
                );
            })}
        </div>
    );
}
