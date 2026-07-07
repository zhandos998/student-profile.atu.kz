import ApplicationLogo from '@/Components/ApplicationLogo';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import LanguageDomTranslator from '@/i18n/LanguageDomTranslator';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0">
            <LanguageDomTranslator />
            <div className="mb-4 flex w-full justify-end px-4 sm:absolute sm:right-6 sm:top-6 sm:mb-0 sm:w-auto sm:px-0">
                <LanguageSwitcher />
            </div>

            <div>
                <Link href="/">
                    <ApplicationLogo
                        variant="wordmark"
                        className="h-16 w-auto max-w-[260px]"
                        alt="Almaty Technological University"
                    />
                </Link>
            </div>

            <div className="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg">
                {children}
            </div>
        </div>
    );
}
