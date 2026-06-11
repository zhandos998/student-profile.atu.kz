import { Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-4 py-2 pe-4 ps-3 ${
                active
                    ? 'border-[#355da8] bg-[#f4f7fc] text-[#355da8] focus:border-[#2f5192] focus:bg-[#eaf0fb] focus:text-[#2f5192]'
                    : 'border-transparent text-gray-600 hover:border-[#355da8] hover:bg-[#f4f7fc] hover:text-[#355da8] focus:border-[#355da8] focus:bg-[#f4f7fc] focus:text-[#355da8]'
            } text-base font-medium transition duration-150 ease-in-out focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
