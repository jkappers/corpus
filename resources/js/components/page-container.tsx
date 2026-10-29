import type { ReactNode, Ref } from 'react';
import { cn } from '@/lib/utils';

const widths = {
    narrow: 'max-w-[32rem]',
    default: 'max-w-[56rem]',
    wide: 'max-w-[64rem]',
} as const;

export function PageContainer({
    size = 'default',
    className,
    children,
}: {
    size?: keyof typeof widths;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'mx-auto px-4 py-3 md:px-6 md:py-4',
                widths[size],
                className,
            )}
        >
            {children}
        </div>
    );
}

export function PageHeader({
    title,
    description,
    actions,
    headingRef,
}: {
    title: ReactNode;
    headingRef?: Ref<HTMLHeadingElement>;
    description?: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <header className="mb-3">
            <div className="flex items-center justify-between gap-3">
                <h1
                    ref={headingRef}
                    tabIndex={headingRef ? -1 : undefined}
                    className="min-w-0 truncate text-lg font-semibold tracking-tight outline-none"
                >
                    {title}
                </h1>
                {actions && <div className="flex gap-2">{actions}</div>}
            </div>
            {description && <p className="mt-1 text-ink-2">{description}</p>}
        </header>
    );
}

export function SectionHeading({
    children,
    description,
}: {
    children: ReactNode;
    description?: ReactNode;
}) {
    return (
        <div className="mb-3">
            <h2 className="text-sm font-semibold text-ink">{children}</h2>
            {description && (
                <div className="mt-0.5 text-xs text-ink-3">{description}</div>
            )}
        </div>
    );
}
