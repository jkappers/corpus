export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    return (
        <header className={variant === 'small' ? '' : 'mb-6 space-y-0.5'}>
            <h2
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-base font-medium'
                        : 'text-lg font-semibold tracking-tight'
                }
            >
                {title}
            </h2>
            {description && <p className="text-ink-2">{description}</p>}
        </header>
    );
}
