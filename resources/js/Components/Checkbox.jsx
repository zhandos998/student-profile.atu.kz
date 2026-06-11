export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-gray-300 text-[#355da8] shadow-sm focus:ring-[#355da8] ' +
                className
            }
        />
    );
}
