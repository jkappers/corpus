import EmptyState from '@/components/empty-state';
import { Link } from '@inertiajs/react';
import { routesFor } from '@/lib/page-routes';
import type { PageType } from '@/types';
import type { Attachment } from '@/types/attachment';

export type FileRow = Attachment & {
    created_at: string;
    page: { id: number; title: string; type: PageType };
};

const fileDate = new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
});

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export default function FileList({
    files,
    empty,
}: {
    files: FileRow[];
    empty: { title: string; description: string };
}) {
    if (files.length === 0) {
        return (
            <EmptyState title={empty.title} description={empty.description} />
        );
    }

    return (
        <div role="list">
            <div className="th hidden gap-x-3 md:flex" aria-hidden>
                <span className="flex-1">Name</span>
                <span className="w-44">Attached to</span>
                <span className="w-16">Size</span>
                <span className="w-28">Added</span>
            </div>
            {files.map((file) => (
                <div
                    key={file.id}
                    role="listitem"
                    className="tr grid grid-cols-[1fr_auto] gap-x-3 py-2 md:flex md:py-1.5"
                >
                    <a
                        href={file.url}
                        target="_blank"
                        rel="noreferrer"
                        className="min-w-0 truncate font-medium hover:underline md:flex-1"
                    >
                        {file.name}
                    </a>
                    <Link
                        href={routesFor(file.page.type).show(file.page.id)}
                        className="col-span-2 row-start-2 truncate text-xs text-ink-3 hover:underline md:w-44"
                    >
                        {file.page.title}
                    </Link>
                    <span className="text-xs text-ink-3 md:w-16">
                        {formatSize(file.size)}
                    </span>
                    <span className="hidden text-xs text-ink-3 md:block md:w-28">
                        {fileDate.format(new Date(file.created_at))}
                    </span>
                </div>
            ))}
        </div>
    );
}
