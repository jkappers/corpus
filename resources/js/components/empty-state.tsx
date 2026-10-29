import type { ReactNode } from 'react';

export default function EmptyState({
    title,
    description,
    action,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="py-10">
            <p className="font-semibold">{title}</p>
            {description && <p className="mt-0.5 text-ink-3">{description}</p>}
            {action && <div className="mt-3">{action}</div>}
        </div>
    );
}
