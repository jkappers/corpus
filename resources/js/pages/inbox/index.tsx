import EmptyState from "@/components/empty-state";
import Pagination from "@/components/pagination";
import type { PaginationState } from "@/components/pagination";
import { PageContainer, PageHeader } from "@/components/page-container";
import { Button } from "@/components/ui/button";
import { Head, Link } from "@inertiajs/react";
import { Check, RotateCcw } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";
import { api, refreshPage } from "@/lib/api";
import { absoluteFormatter, relativeTime } from "@/lib/relative-time";
import { complete, reopen } from "@/routes/api/v1/notations";
import { index } from "@/routes/inbox";
import { show } from "@/routes/pages";

type Row = {
    id: number;
    quote: string;
    body: string;
    created_at: string;
    author: { id: number; name: string };
    page: { id: number; title: string };
};

type Counts = { open: number; completed: number };

const STALE_AFTER_MS = 3 * 86_400_000;

function NotationRow({
    notation,
    completed,
    pending,
    tabbable,
    onToggle,
    onActivate,
}: {
    notation: Row;
    completed: boolean;
    pending: boolean;
    tabbable: boolean;
    onToggle: (notation: Row) => void;
    onActivate: (id: number) => void;
}) {
    const tabIndex = tabbable ? 0 : -1;
    const stale =
        !completed &&
        Date.now() - new Date(notation.created_at).getTime() > STALE_AFTER_MS;

    return (
        <li
            data-inbox-row
            data-notation-id={notation.id}
            onFocus={() => onActivate(notation.id)}
            className="tr group relative items-start gap-3 py-3 has-[:focus-visible]:bg-hover max-sm:flex-col"
        >
            <div className="min-w-0 flex-1">
                <p className="line-clamp-3 text-sm font-medium whitespace-pre-wrap text-ink">
                    {notation.body}
                </p>

                <blockquote className="notation-quote mt-1.5 line-clamp-1 w-fit max-w-full px-1 text-xs text-ink-2">
                    {notation.quote}
                </blockquote>

                <p className="mt-1.5 flex min-w-0 items-baseline gap-1.5 text-xs text-ink-2">
                    <span className="shrink-0">{notation.author.name}</span>
                    <span aria-hidden>·</span>
                    <Link
                        href={show(
                            { page: notation.page.id },
                            { query: { notation: notation.id } },
                        )}
                        data-inbox-link
                        tabIndex={tabIndex}
                        className="min-w-0 truncate hover:text-ink hover:underline after:absolute after:inset-0 after:content-['']"
                    >
                        {notation.page.title}
                    </Link>
                    <span aria-hidden>·</span>
                    <time
                        dateTime={notation.created_at}
                        title={absoluteFormatter.format(
                            new Date(notation.created_at),
                        )}
                        className={`shrink-0 tabular-nums ${stale ? "font-medium text-warn" : ""}`}
                    >
                        {relativeTime(notation.created_at)}
                    </time>
                </p>
            </div>
            <Button
                type="button"
                variant="quiet"
                size="sm"
                tabIndex={tabIndex}
                className="relative z-10 shrink-0 text-ink-2 max-sm:-ml-2group-has-[:focus-visible]:border-edge group-has-[:focus-visible]:text-ink hover:border-edge hover:text-ink sm:group-hover:border-line sm:group-hover:text-ink"
                disabled={pending}
                aria-label={`${completed ? "Reopen" : "Mark done"}: note from ${notation.author.name} on ${notation.page.title}`}
                onClick={() => onToggle(notation)}
            >
                {completed ? <RotateCcw aria-hidden /> : <Check aria-hidden />}
                {completed ? "Reopen" : "Done"}
            </Button>
        </li>
    );
}

export default function Inbox({
    notations,
    completed,
    counts,
    pagination,
}: {
    notations: Row[];
    completed: boolean;
    counts: Counts;
    pagination: PaginationState;
}) {
    const [hiddenIds, setHiddenIds] = useState<Set<number>>(new Set());
    const [pendingIds, setPendingIds] = useState<Set<number>>(new Set());
    const [activeId, setActiveId] = useState<number | null>(null);
    const headingRef = useRef<HTMLHeadingElement>(null);
    const listRef = useRef<HTMLUListElement>(null);

    const setMember = (
        update: typeof setHiddenIds,
        id: number,
        present: boolean,
    ) =>
        update((current) => {
            const next = new Set(current);

            if (present) {
                next.add(id);
            } else {
                next.delete(id);
            }

            return next;
        });

    const focusNeighbour = (id: number) => {
        const rows = Array.from(
            listRef.current?.querySelectorAll<HTMLElement>(
                "[data-inbox-row]",
            ) ?? [],
        );
        const position = rows.findIndex(
            (row) => row.dataset.notationId === String(id),
        );
        const target = rows[position + 1] ?? rows[position - 1];
        const link =
            target?.querySelector<HTMLElement>("[data-inbox-link]") ?? null;

        (link ?? headingRef.current)?.focus();
    };

    const setCompletion = async (notation: Row, done: boolean) => {
        await api(
            done ? "put" : "delete",
            (done ? complete : reopen)(notation.id).url,
        );
        await refreshPage();
    };

    const toggle = async (notation: Row) => {
        const markDone = !completed;

        setMember(setPendingIds, notation.id, true);
        setMember(setHiddenIds, notation.id, true);
        focusNeighbour(notation.id);

        try {
            await setCompletion(notation, markDone);
            toast(
                `${completed ? "Reopened" : "Marked done"}: ${notation.page.title}`,
                {
                    action: {
                        label: "Undo",
                        onClick: () => setCompletion(notation, !markDone),
                    },
                },
            );
        } catch {
            toast.error(
                `Couldn't ${completed ? "reopen" : "complete"} this note.`,
                { action: { label: "Retry", onClick: () => toggle(notation) } },
            );
        } finally {
            setMember(setPendingIds, notation.id, false);
            setMember(setHiddenIds, notation.id, false);
        }
    };

    const visible = notations.filter((notation) => !hiddenIds.has(notation.id));
    const tabbableId = visible.some((notation) => notation.id === activeId)
        ? activeId
        : (visible[0]?.id ?? null);

    const toggleRef = useRef(toggle);
    toggleRef.current = toggle;
    const notationsRef = useRef(notations);
    notationsRef.current = notations;

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                event.defaultPrevented ||
                event.metaKey ||
                event.ctrlKey ||
                event.altKey ||
                !["j", "k", "e"].includes(event.key)
            ) {
                return;
            }

            const target = event.target as HTMLElement | null;

            if (
                target?.closest(
                    'input, textarea, select, [contenteditable="true"], [role="dialog"], [role="menu"]',
                )
            ) {
                return;
            }

            const rows = Array.from(
                listRef.current?.querySelectorAll<HTMLElement>(
                    "[data-inbox-row]",
                ) ?? [],
            );
            const current = rows.findIndex((row) =>
                row.contains(document.activeElement),
            );

            if (event.key === "e") {
                const id = rows[current]?.dataset.notationId;
                const notation = notationsRef.current.find(
                    (row) => String(row.id) === id,
                );

                if (notation) {
                    event.preventDefault();
                    toggleRef.current(notation);
                }

                return;
            }

            const next = Math.min(
                Math.max(current + (event.key === "j" ? 1 : -1), 0),
                rows.length - 1,
            );

            event.preventDefault();
            rows[next]
                ?.querySelector<HTMLElement>("[data-inbox-link]")
                ?.focus();
        };

        window.addEventListener("keydown", onKeyDown);

        return () => window.removeEventListener("keydown", onKeyDown);
    }, []);

    const title = completed
        ? "Completed"
        : counts.open > 0
          ? `${counts.open} waiting on you`
          : "Inbox";

    return (
        <>
            <Head title="Inbox" />
            <PageContainer size="wide">
                <PageHeader
                    headingRef={headingRef}
                    title={title}
                />

                {visible.length === 0 ? (
                    <EmptyState
                        title={
                            completed
                                ? "No completed notes yet"
                                : "Nothing waiting on you"
                        }
                        description={
                            completed
                                ? "Notes you mark done show up here, so you can reopen them."
                                : "Notes assigned to you show up here until you mark them done."
                        }
                    />
                ) : (
                    <ul
                        ref={listRef}
                    >
                        {visible.map((notation) => (
                            <NotationRow
                                key={notation.id}
                                notation={notation}
                                completed={completed}
                                pending={pendingIds.has(notation.id)}
                                tabbable={notation.id === tabbableId}
                                onToggle={toggle}
                                onActivate={setActiveId}
                            />
                        ))}
                    </ul>
                )}

                <Pagination
                    pagination={pagination}
                    prevLabel="Previous"
                    nextLabel="Next"
                />

                <footer className="mt-6 flex items-center justify-between gap-3 text-xs text-ink-3">
                    <Link
                        href={index({ query: completed ? {} : { completed: 1 } })}
                        preserveScroll
                        className="rounded-ctl px-1 py-1 text-ink-2 hover:text-ink hover:underline [@media(pointer:coarse)]:min-h-11 [@media(pointer:coarse)]:content-center"
                    >
                        {completed
                            ? "Back to inbox"
                            : `Completed (${counts.completed})`}
                    </Link>
                    {visible.length > 0 && (
                        <span className="[@media(pointer:coarse)]:hidden">
                            j/k move · e {completed ? "reopen" : "done"}
                        </span>
                    )}
                </footer>
            </PageContainer>
        </>
    );
}
