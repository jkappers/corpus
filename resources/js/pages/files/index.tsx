import FileList from '@/components/file-list';
import type { FileRow } from '@/components/file-list';
import { PageContainer, PageHeader } from '@/components/page-container';
import Pagination from '@/components/pagination';
import type { PaginationState } from '@/components/pagination';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { index } from '@/routes/files';
import type { AttachmentKind } from '@/types/attachment';

type Filters = { q: string; kind: AttachmentKind | null };

const allKinds = 'all';

const kinds: { value: string; label: string }[] = [
    { value: allKinds, label: 'All files' },
    { value: 'image', label: 'Images' },
    { value: 'file', label: 'Documents' },
];

const SEARCH_DEBOUNCE_MS = 250;

export default function Files({
    files,
    filters,
    pagination,
}: {
    files: FileRow[];
    filters: Filters;
    pagination: PaginationState;
}) {
    const [query, setQuery] = useState(filters.q);
    const latest = useRef(filters);
    latest.current = filters;

    const visit = (next: Partial<Filters>) => {
        const merged = { ...latest.current, ...next };

        router.get(
            index({
                query: {
                    ...(merged.q ? { q: merged.q } : {}),
                    ...(merged.kind ? { kind: merged.kind } : {}),
                },
            }).url,
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    useEffect(() => {
        if (query === latest.current.q) {
            return;
        }

        const timer = setTimeout(() => visit({ q: query }), SEARCH_DEBOUNCE_MS);

        return () => clearTimeout(timer);
    }, [query]);

    const filtered = filters.q !== '' || filters.kind !== null;

    return (
        <>
            <Head title="Files" />
            <PageContainer size="wide">
                <PageHeader
                    title="Files"
                    description="Everything attached to pages and boards you can see."
                />

                <div className="mb-3 flex gap-2">
                    <Input
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search files"
                        aria-label="Search files"
                        className="max-w-xs"
                    />
                    <Select
                        value={filters.kind ?? allKinds}
                        onValueChange={(value) =>
                            visit({
                                kind:
                                    value === allKinds
                                        ? null
                                        : (value as AttachmentKind),
                            })
                        }
                    >
                        <SelectTrigger size="sm" aria-label="Type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {kinds.map((kind) => (
                                <SelectItem key={kind.value} value={kind.value}>
                                    {kind.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <FileList
                    files={files}
                    empty={
                        filtered
                            ? {
                                  title: 'No matching files',
                                  description:
                                      'Try a different search or type.',
                              }
                            : {
                                  title: 'No files yet',
                                  description:
                                      'Files attached to pages and boards show up here.',
                              }
                    }
                />

                <Pagination pagination={pagination} />
            </PageContainer>
        </>
    );
}
