import { Link } from '@inertiajs/react';
import { buttonVariants } from '@/components/ui/button';

export type PaginationState = {
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export default function Pagination({
    pagination,
    prevLabel = 'Previous',
    nextLabel = 'Next',
}: {
    pagination: PaginationState;
    prevLabel?: string;
    nextLabel?: string;
}) {
    if (pagination.last_page <= 1) {
        return null;
    }

    return (
        <nav
            aria-label="Pagination"
            className="mt-4 flex items-center justify-between text-xs text-ink-3"
        >
            {pagination.prev_page_url ? (
                <Link
                    href={pagination.prev_page_url}
                    className={buttonVariants({ size: 'sm' })}
                >
                    {prevLabel}
                </Link>
            ) : (
                <span />
            )}
            <span>
                Page {pagination.current_page} of {pagination.last_page}
            </span>
            {pagination.next_page_url ? (
                <Link
                    href={pagination.next_page_url}
                    className={buttonVariants({ size: 'sm' })}
                >
                    {nextLabel}
                </Link>
            ) : (
                <span />
            )}
        </nav>
    );
}
