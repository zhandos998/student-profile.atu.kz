import { Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none ' +
                (active
                    ? 'border-[#355da8] text-[#355da8] focus:border-[#2f5192]'
                    : 'border-transparent text-gray-600 hover:border-[#355da8] hover:text-[#355da8] focus:border-[#355da8] focus:text-[#355da8]') +
                className
            }
        >
            {children}
        </Link>
    );
}
