const logoSources = {
    mark: '/favicon.png',
    compact: '/logo.png',
    wordmark: '/logo%20long.png',
};

export default function ApplicationLogo({
    variant = 'mark',
    className = '',
    alt = 'ATU',
    ...props
}) {
    return (
        <img
            {...props}
            src={logoSources[variant] ?? logoSources.mark}
            alt={alt}
            className={['object-contain', className].filter(Boolean).join(' ')}
        />
    );
}
