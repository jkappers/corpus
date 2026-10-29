import EmptyState from '@/components/empty-state';
import FileList from '@/components/file-list';
import NewButton from '@/components/new-button';
import { PageContainer, PageHeader } from '@/components/page-container';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Settings } from 'lucide-react';
import { toast } from 'sonner';
import { api, refreshPage } from '@/lib/api';
import { routesFor } from '@/lib/page-routes';
import { spaceLabel, useSpaceLabel } from '@/lib/spaces';
import { edit, show as spaceShow } from '@/routes/spaces';
import type { Space, PageType } from '@/types';
import type { FileRow } from '@/components/file-list';

type Row = {
    id: number;
    type: PageType;
    title: string;
    excerpt: string | null;
    updated_at: string;
    space: { id: number; name: string; visibility: string };
};

type SortKey = 'title' | 'space' | 'updated';

type Filter = PageType | 'file';

const filters: { value: Filter | null; label: string }[] = [
    { value: null, label: 'Everything' },
    { value: 'page', label: 'Pages' },
    { value: 'board', label: 'Boards' },
    { value: 'file', label: 'Files' },
];

const allFilter = 'all';

const formatter = new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
});

const plainExcerpt = (markdown: string | null): string =>
    (markdown ?? '')
        .replace(/!\[[^\]]*\]\([^)]*\)/g, '')
        .replace(/\[([^\]]*)\]\([^)]*\)/g, '$1')
        .replace(/&nbsp;|&amp;/g, ' ')
        .replace(/\[!(note|tip|important|warning|caution)\]/gi, '')
        .replace(/^\s*[-*] \[[ xX]\]/gm, '')
        .replace(/\|[\s:|-]+\|/g, ' ')
        .replace(/[#>*_`~|[\]]/g, '')
        .replace(/\s+/g, ' ')
        .trim();

const spaceHue = (id: number): number => (id * 67) % 360;

function SortHeader({
    label,
    column,
    sort,
    onSort,
    className,
}: {
    label: string;
    column: SortKey;
    sort: { key: SortKey; desc: boolean } | null;
    onSort: (key: SortKey) => void;
    className: string;
}) {
    if (!sort) {
        return <span className={className}>{label}</span>;
    }

    const active = sort.key === column;
    const Arrow = sort.desc ? ArrowDown : ArrowUp;

    return (
        <button
            type="button"
            className={`flex items-center gap-1 text-left hover:text-ink ${className}`}
            aria-label={`Sort by ${label.toLowerCase()}`}
            onClick={() => onSort(column)}
        >
            {label}
            {active && <Arrow className="size-3" aria-hidden />}
        </button>
    );
}

function emptyCopy(
    type: Filter | null,
    space: string | null,
    canCreate: boolean,
): { title: string; description: string } {
    if (!space) {
        return {
            title: 'Nothing here yet',
            description:
                'Pages and boards you or your teammates change show up here. Add one with the + next to a space.',
        };
    }

    if (type === 'file') {
        return {
            title: `No files in ${space}`,
            description: 'Files you attach to pages in this space appear here.',
        };
    }

    if (!canCreate) {
        return {
            title: type ? `No ${type}s in ${space}` : `Nothing in ${space} yet`,
            description: 'You can view this space but not add to it.',
        };
    }

    return {
        title: type ? `No ${type}s in ${space}` : `Nothing in ${space} yet`,
        description: 'Pages are for writing. Boards are for sketching.',
    };
}

export default function Pages({
    pages,
    files,
    heading,
    space,
    type,
    filters: sortFilters,
}: {
    pages: Row[];
    files: FileRow[] | null;
    heading: string;
    space: {
        id: number;
        name: string;
        can_edit: boolean;
        can_update: boolean;
    } | null;
    type: Filter | null;
    filters: { sort: SortKey; direction: 'asc' | 'desc' } | null;
}) {
    const sort = sortFilters
        ? { key: sortFilters.sort, desc: sortFilters.direction === 'desc' }
        : null;

    const { spaces } = usePage<{ spaces: Space[] }>().props;
    const labelFor = useSpaceLabel();
    const current = spaces.find((item) => item.id === space?.id);
    const isPersonal = current?.is_personal === true;
    const isMine = current?.is_mine === true;
    const ownerName = current?.owner_name ?? null;
    const title = current ? spaceLabel(current) : heading;

    const canCreate = space?.can_edit === true;
    const empty = emptyCopy(type, space ? title : null, canCreate);
    const filterHref = (value: Filter | null) =>
        space
            ? spaceShow(space.id, { query: value ? { type: value } : {} })
            : null;

    const trash = async (row: Row) => {
        const routes = routesFor(row.type);

        try {
            await api('delete', routes.api.destroy(row.id).url);
            await refreshPage();
            toast(`Moved “${row.title}” to Trash`, {
                action: {
                    label: 'Undo',
                    onClick: async () => {
                        await api('post', routes.api.restore(row.id).url);
                        router.visit(routes.show(row.id).url);
                    },
                },
            });
        } catch {
            toast.error(`Couldn't delete “${row.title}”.`);
        }
    };

    const toggleSort = (key: SortKey) => {
        if (!space || !sort) {
            return;
        }

        router.get(
            spaceShow(space.id).url,
            {
                ...(type ? { type } : {}),
                sort: key,
                direction:
                    sort.key === key
                        ? sort.desc
                            ? 'asc'
                            : 'desc'
                        : key === 'updated'
                          ? 'desc'
                          : 'asc',
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const createAction =
        space && canCreate && type !== 'file' ? (
            <NewButton spaceId={space.id} />
        ) : null;

    return (
        <>
            <Head title={title} />
            <PageContainer size="wide">
                <PageHeader
                    title={title}
                    actions={
                        space && (
                            <>
                                {space.can_update && (
                                    <Link
                                        href={edit(space.id)}
                                        className={buttonVariants({
                                            variant: 'quiet',
                                            size: 'icon',
                                            className:
                                                'text-ink-3 hover:text-ink',
                                        })}
                                        aria-label="Space settings"
                                        title="Space settings"
                                    >
                                        <Settings aria-hidden />
                                    </Link>
                                )}
                                {!canCreate && (
                                    <span className="tag self-center">
                                        View only
                                    </span>
                                )}
                            </>
                        )
                    }
                />

                {isPersonal && (
                    <p className="mb-3 text-sm text-ink-3">
                        {isMine
                            ? 'Only you can see these.'
                            : `Private to ${ownerName ?? 'its owner'}.`}
                    </p>
                )}

                {space && (
                    <Select
                        value={type ?? allFilter}
                        onValueChange={(value) =>
                            router.visit(
                                filterHref(
                                    value === allFilter
                                        ? null
                                        : (value as Filter),
                                )!,
                                { preserveScroll: true },
                            )
                        }
                    >
                        <SelectTrigger
                            size="sm"
                            className="mb-3"
                            aria-label="Show"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {filters.map((filter) => (
                                <SelectItem
                                    key={filter.label}
                                    value={filter.value ?? allFilter}
                                >
                                    {filter.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}

                {type === 'file' ? (
                    <FileList files={files ?? []} empty={empty} />
                ) : pages.length === 0 ? (
                    <EmptyState
                        title={empty.title}
                        description={empty.description}
                        action={createAction}
                    />
                ) : (
                    <div>
                        <div className="th hidden gap-x-3 md:flex">
                            {!type && <span className="w-16">Type</span>}
                            <SortHeader
                                label="Title"
                                column="title"
                                sort={sort}
                                onSort={toggleSort}
                                className="flex-1"
                            />
                            {!space && (
                                <SortHeader
                                    label="Space"
                                    column="space"
                                    sort={sort}
                                    onSort={toggleSort}
                                    className="w-44"
                                />
                            )}
                            <SortHeader
                                label="Updated"
                                column="updated"
                                sort={sort}
                                onSort={toggleSort}
                                className="w-36"
                            />
                            <span className="w-20" />
                        </div>
                        <div role="list">
                            {pages.map((page) => {
                                const excerpt = plainExcerpt(page.excerpt);

                                return (
                                    <div
                                        key={page.id}
                                        role="listitem"
                                        className="tr group grid grid-cols-[1fr_auto] gap-x-3 py-2 md:flex md:py-1.5"
                                    >
                                        {!type && (
                                            <span className="hidden w-16 md:block">
                                                <span className="tag">
                                                    {page.type}
                                                </span>
                                            </span>
                                        )}
                                        <div className="min-w-0 md:flex-1">
                                            <Link
                                                href={routesFor(page.type).show(
                                                    page.id,
                                                )}
                                                className="block truncate font-medium hover:underline"
                                            >
                                                {page.title}
                                            </Link>
                                            {excerpt && (
                                                <p className="truncate text-xs text-ink-3">
                                                    {excerpt}
                                                </p>
                                            )}
                                        </div>
                                        <span className="col-span-2 row-start-2 mt-0.5 flex items-center gap-2 text-xs text-ink-3 md:contents">
                                            {!space && (
                                                <span className="flex min-w-0 items-center gap-1.5 md:w-44">
                                                    <span
                                                        className="size-2 shrink-0 rounded-[2px]"
                                                        style={{
                                                            background: `oklch(0.72 0.12 ${spaceHue(page.space.id)})`,
                                                        }}
                                                        aria-hidden
                                                    />
                                                    <span className="truncate">
                                                        {labelFor(page.space)}
                                                    </span>
                                                </span>
                                            )}
                                            <span className="shrink-0 md:w-36">
                                                {formatter.format(
                                                    new Date(page.updated_at),
                                                )}
                                            </span>
                                        </span>
                                        <span className="col-start-2 row-start-1 flex justify-end md:w-20">
                                            <Button
                                                type="button"
                                                variant="quiet"
                                                size="sm"
                                                className="text-ink-3 group-focus-within:opacity-100 hover:text-bad-ink focus-visible:opacity-100 [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:opacity-100"
                                                aria-label={`Delete ${page.title}`}
                                                onClick={() => trash(page)}
                                            >
                                                Delete
                                            </Button>
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}
            </PageContainer>
        </>
    );
}
