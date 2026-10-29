import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { typeIcons } from '@/components/new-button';
import { useCreatePage } from '@/hooks/use-create-page';
import { api } from '@/lib/api';
import { routesFor } from '@/lib/page-routes';
import { spaceLabel, useCreateTarget, useSpaceLabel } from '@/lib/spaces';
import { search } from '@/routes/api/v1';
import { show as spaceShow } from '@/routes/spaces';
import type { PageType, Space } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Layers, Lock, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const commandTypes: PageType[] = ['page', 'board'];

type Result = {
    type: PageType;
    id: number;
    title: string;
    space?: { id: number; name: string };
};

type Status = 'idle' | 'loading' | 'done' | 'error';

type Item =
    | { kind: 'space'; id: string; space: Space }
    | { kind: 'result'; id: string; result: Result };

const MAX_SPACES = 4;

export default function GlobalSearch({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [q, setQ] = useState('');
    const [results, setResults] = useState<Result[]>([]);
    const [status, setStatus] = useState<Status>('idle');
    const [active, setActive] = useState(0);
    const latest = useRef(0);
    const { create } = useCreatePage();
    const { target } = useCreateTarget();
    const labelFor = useSpaceLabel();

    const { spaces } = usePage<{ spaces: Space[] }>().props;
    const term = q.trim().toLowerCase();
    const items: Item[] = [
        ...(term
            ? spaces
                  .filter((item) =>
                      spaceLabel(item).toLowerCase().includes(term),
                  )
                  .slice(0, MAX_SPACES)
                  .map((item) => ({
                      kind: 'space' as const,
                      id: `global-search-space-${item.id}`,
                      space: item,
                  }))
            : []),
        ...results.map((result) => ({
            kind: 'result' as const,
            id: `global-search-${result.type}-${result.id}`,
            result,
        })),
    ];
    const noMatches = status === 'done' && items.length === 0;
    const showCommands = status === 'idle' || noMatches;
    const count = showCommands ? commandTypes.length : items.length;

    useEffect(() => {
        const term = q.trim();
        const request = ++latest.current;

        if (!term) {
            setResults([]);
            setStatus('idle');
            setActive(0);

            return;
        }

        setStatus('loading');

        const timer = setTimeout(async () => {
            try {
                const { data } = await api<{ data: Result[] }>(
                    'get',
                    search({ query: { q: term } }).url,
                );

                if (request === latest.current) {
                    setResults(
                        data.filter(
                            (r) => r.type === 'page' || r.type === 'board',
                        ),
                    );
                    setActive(0);
                    setStatus('done');
                }
            } catch {
                if (request === latest.current) {
                    setStatus('error');
                }
            }
        }, 250);

        return () => clearTimeout(timer);
    }, [q]);

    const handleOpenChange = (next: boolean) => {
        if (!next) {
            setQ('');
        }

        onOpenChange(next);
    };

    const go = (item: Item) => {
        handleOpenChange(false);
        router.visit(
            item.kind === 'space'
                ? spaceShow(item.space.id).url
                : routesFor(item.result.type).show(item.result.id).url,
        );
    };

    const run = (type: PageType) => {
        handleOpenChange(false);
        void create(type, target?.id);
    };

    const onKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'ArrowDown' && count) {
            event.preventDefault();
            setActive((index) => (index + 1) % count);
        } else if (event.key === 'ArrowUp' && count) {
            event.preventDefault();
            setActive((index) => (index - 1 + count) % count);
        } else if (event.key === 'Enter' && showCommands) {
            event.preventDefault();
            run(commandTypes[active]);
        } else if (event.key === 'Enter' && items[active]) {
            event.preventDefault();
            go(items[active]);
        }
    };

    const activeId = showCommands
        ? `global-search-new-${commandTypes[active]}`
        : items[active]?.id;

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent
                className="top-[15%] translate-y-0 gap-0 p-0 sm:max-w-xl"
                onKeyDown={onKeyDown}
            >
                <DialogTitle className="sr-only">Search</DialogTitle>
                <DialogDescription className="sr-only">
                    Search spaces, pages and boards by title
                </DialogDescription>
                <div className="flex items-center gap-2 border-b border-rule px-4">
                    <Search
                        className="size-4 shrink-0 text-ink-3"
                        aria-hidden
                    />
                    <input
                        type="search"
                        autoFocus
                        value={q}
                        onChange={(event) => setQ(event.target.value)}
                        placeholder="Search spaces, pages and boards"
                        aria-label="Search spaces, pages and boards"
                        role="combobox"
                        aria-expanded={count > 0}
                        aria-controls="global-search-results"
                        aria-activedescendant={activeId}
                        className="h-12 min-w-0 flex-1 bg-transparent pr-8 text-sm outline-none placeholder:text-ink-3"
                    />
                </div>

                <div className="max-h-[50vh] overflow-y-auto p-2">
                    {noMatches && (
                        <p
                            className="truncate px-2 pt-3 pb-4 text-sm text-ink-3"
                            role="status"
                        >
                            No results for{' '}
                            <span className="text-ink-2">“{q.trim()}”</span>
                        </p>
                    )}
                    {showCommands && (
                        <ul
                            id="global-search-results"
                            role="listbox"
                            aria-label="Create"
                        >
                            {commandTypes.map((type, index) => {
                                const Icon = typeIcons[type];

                                return (
                                    <li
                                        key={type}
                                        id={`global-search-new-${type}`}
                                        role="option"
                                        aria-selected={index === active}
                                        onMouseMove={() => setActive(index)}
                                        onClick={() => run(type)}
                                        className={`flex cursor-pointer items-center gap-3 rounded-ctl px-2 py-2 text-sm ${index === active ? 'bg-sel' : ''}`}
                                    >
                                        <Icon
                                            className="size-4 shrink-0 text-ink-3"
                                            aria-hidden
                                        />
                                        <span className="min-w-0 flex-1 truncate font-medium">
                                            New {type}
                                        </span>
                                        {target && (
                                            <span className="max-w-[10rem] shrink-0 truncate text-xs text-ink-3">
                                                in {spaceLabel(target)}
                                            </span>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                    {status === 'loading' && items.length === 0 && (
                        <div className="space-y-2 p-2" aria-hidden>
                            {[0, 1, 2].map((row) => (
                                <div
                                    key={row}
                                    className="h-7 animate-pulse rounded-ctl bg-hover"
                                />
                            ))}
                        </div>
                    )}
                    {status === 'error' && (
                        <p
                            className="px-2 py-6 text-center text-sm text-bad-ink"
                            role="alert"
                        >
                            Search failed. Try again.
                        </p>
                    )}
                    {!showCommands && items.length > 0 && (
                        <ul
                            id="global-search-results"
                            role="listbox"
                            aria-label="Results"
                        >
                            {items.map((item, index) => (
                                <li
                                    key={item.id}
                                    id={item.id}
                                    role="option"
                                    aria-selected={index === active}
                                    onMouseMove={() => setActive(index)}
                                    onClick={() => go(item)}
                                    className={`flex cursor-pointer items-center gap-3 rounded-ctl px-2 py-2 text-sm ${index === active ? 'bg-sel' : ''}`}
                                >
                                    <span className="tag w-14 shrink-0 justify-center">
                                        {item.kind === 'space'
                                            ? 'space'
                                            : item.result.type}
                                    </span>
                                    {item.kind === 'space' ? (
                                        <>
                                            <span className="min-w-0 flex-1 truncate font-medium">
                                                {spaceLabel(item.space)}
                                            </span>
                                            {item.space.visibility ===
                                            'private' ? (
                                                <Lock
                                                    className="size-3.5 shrink-0 text-ink-3"
                                                    aria-hidden
                                                />
                                            ) : (
                                                <Layers
                                                    className="size-3.5 shrink-0 text-ink-3"
                                                    aria-hidden
                                                />
                                            )}
                                        </>
                                    ) : (
                                        <>
                                            <span className="min-w-0 flex-1 truncate font-medium">
                                                {item.result.title}
                                            </span>
                                            {item.result.space && (
                                                <span className="max-w-[10rem] shrink-0 truncate text-xs text-ink-3">
                                                    {labelFor(
                                                        item.result.space,
                                                    )}
                                                </span>
                                            )}
                                        </>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
