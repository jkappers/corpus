import { useConfirm } from "@/components/confirm-dialog";
import EmptyState from "@/components/empty-state";
import { PageContainer, PageHeader } from "@/components/page-container";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Head, router, usePage } from "@inertiajs/react";
import { api, refreshPage } from "@/lib/api";
import { routesFor } from "@/lib/page-routes";
import { cn } from "@/lib/utils";
import type { PageType } from "@/types";
import { useState } from "react";
import { toast } from "sonner";

type Row = {
    id: number;
    type: PageType;
    title: string;
    deleted_at: string;
    days_left: number;
    space: { id: number; name: string; visibility: string };
};

const URGENT_DAYS = 3;

const fullDate = new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
});

const shortDate = new Intl.DateTimeFormat(undefined, {
    month: "short",
    day: "numeric",
});

const rowKey = (row: Row) => `${row.type}:${row.id}`;

const titleOf = (row: Row) => row.title || "Untitled";

const plural = (count: number) => `${count} ${count === 1 ? "item" : "items"}`;

function deletedLabel(deletedAt: string): string {
    const date = new Date(deletedAt);
    const days = Math.floor((Date.now() - date.getTime()) / 86_400_000);

    if (days < 1) {
        return "Deleted today";
    }

    if (days === 1) {
        return "Deleted yesterday";
    }

    return days < 7
        ? `Deleted ${days}d ago`
        : `Deleted ${shortDate.format(date)}`;
}

const restoreRequest = (row: Row) =>
    api("post", routesFor(row.type).api.restore(row.id).url);

const forceDeleteRequest = (row: Row) =>
    api("delete", routesFor(row.type).api.forceDestroy(row.id).url);

/** Runs one request per row and reports how many succeeded and failed. */
async function runAll(rows: Row[], request: (row: Row) => Promise<unknown>) {
    const results = await Promise.allSettled(rows.map(request));
    const failed = results.filter((r) => r.status === "rejected").length;

    return { done: rows.length - failed, failed };
}

export default function Trash({ pages }: { pages: Row[] }) {
    const { trashRetentionDays: retentionDays } = usePage<{
        trashRetentionDays: number;
    }>().props;
    const { confirm, dialog } = useConfirm();
    const [selectedKeys, setSelectedKeys] = useState<Set<string>>(new Set());
    const [busy, setBusy] = useState(false);

    const selected = pages.filter((row) => selectedKeys.has(rowKey(row)));
    const allSelected = pages.length > 0 && selected.length === pages.length;

    const toggle = (row: Row, checked: boolean) => {
        setSelectedKeys((current) => {
            const next = new Set(current);

            if (checked) {
                next.add(rowKey(row));
            } else {
                next.delete(rowKey(row));
            }

            return next;
        });
    };

    const restore = async (rows: Row[]) => {
        setBusy(true);
        const { done, failed } = await runAll(rows, restoreRequest);
        await refreshPage();
        setBusy(false);

        if (failed > 0) {
            toast.error(`Couldn't restore ${plural(failed)}. Try again.`);
        }

        if (done === 0) {
            return;
        }

        if (rows.length === 1 && failed === 0) {
            const [row] = rows;

            toast(`Restored "${titleOf(row)}"`, {
                action: {
                    label: "Open",
                    onClick: () =>
                        router.visit(routesFor(row.type).show(row.id).url),
                },
            });
        } else {
            toast(`Restored ${plural(done)}`);
        }
    };

    const deleteForever = async (
        rows: Row[],
        title: string,
        confirmLabel: string,
    ) => {
        const confirmed = await confirm({
            title,
            description: "This cannot be undone.",
            confirmLabel,
            destructive: true,
        });

        if (!confirmed) {
            return;
        }

        setBusy(true);
        const { done, failed } = await runAll(rows, forceDeleteRequest);
        await refreshPage();
        setBusy(false);

        if (failed > 0) {
            toast.error(`Couldn't delete ${plural(failed)}. Try again.`);
        }

        if (done > 0) {
            toast(`Deleted ${plural(done)} permanently`);
        }
    };

    return (
        <>
            {dialog}
            <Head title="Trash" />
            <PageContainer size="wide">
                <PageHeader
                    title="Trash"
                    description={`Items are deleted permanently after ${retentionDays} days.`}
                    actions={
                        pages.length > 0 && (
                            <Button
                                type="button"
                                variant="danger"
                                disabled={busy}
                                onClick={() =>
                                    deleteForever(
                                        pages,
                                        `Delete ${plural(pages.length)} forever?`,
                                        "Empty trash",
                                    )
                                }
                            >
                                Empty trash
                            </Button>
                        )
                    }
                />

                {pages.length === 0 ? (
                    <EmptyState
                        title="Trash is empty"
                        description="Deleted pages and boards wait here until you restore them or the retention period ends."
                    />
                ) : (
                    <>
                        <div className="flex h-9 items-center gap-3 border-b border-rule px-2">
                            <Checkbox
                                aria-label="Select all"
                                checked={
                                    allSelected
                                        ? true
                                        : selected.length > 0
                                          ? "indeterminate"
                                          : false
                                }
                                onCheckedChange={(checked) =>
                                    setSelectedKeys(
                                        checked === true
                                            ? new Set(pages.map(rowKey))
                                            : new Set(),
                                    )
                                }
                            />
                            {selected.length === 0 ? (
                                <span className="text-xs text-ink-3">
                                    {plural(pages.length)}
                                </span>
                            ) : (
                                <div className="flex min-w-0 flex-1 items-center gap-1">
                                    <span className="mr-1 text-xs text-ink-2">
                                        {selected.length} selected
                                    </span>
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={busy}
                                        onClick={() => restore(selected)}
                                    >
                                        Restore
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="danger"
                                        disabled={busy}
                                        onClick={() =>
                                            deleteForever(
                                                selected,
                                                `Delete ${plural(selected.length)} forever?`,
                                                "Delete forever",
                                            )
                                        }
                                    >
                                        Delete forever
                                    </Button>
                                </div>
                            )}
                        </div>

                        <ul>
                            {pages.map((row) => {
                                const urgent = row.days_left <= URGENT_DAYS;

                                return (
                                    <li
                                        key={rowKey(row)}
                                        className="tr group gap-3 py-2"
                                    >
                                        <Checkbox
                                            aria-label={`Select ${titleOf(row)}`}
                                            checked={selectedKeys.has(
                                                rowKey(row),
                                            )}
                                            onCheckedChange={(checked) =>
                                                toggle(row, checked === true)
                                            }
                                        />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium">
                                                {titleOf(row)}
                                            </p>
                                            <p className="truncate text-xs text-ink-3">
                                                {row.type === "board"
                                                    ? "Board · "
                                                    : null}
                                                {row.space.name}
                                                {" · "}
                                                <time
                                                    dateTime={row.deleted_at}
                                                    title={fullDate.format(
                                                        new Date(
                                                            row.deleted_at,
                                                        ),
                                                    )}
                                                >
                                                    {deletedLabel(
                                                        row.deleted_at,
                                                    )}
                                                </time>
                                                {" · "}
                                                <span
                                                    className={cn(
                                                        "tabular-nums",
                                                        urgent && "text-warn",
                                                    )}
                                                >
                                                    {row.days_left}d left
                                                </span>
                                            </p>
                                        </div>
                                        <span className="flex shrink-0 items-center gap-1">
                                            <Button
                                                type="button"
                                                variant="quiet"
                                                size="sm"
                                                disabled={busy}
                                                className="text-ink-3 hover:text-bad-ink group-focus-within:opacity-100 focus-visible:opacity-100 [@media(pointer:coarse)]:hidden [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:opacity-100"
                                                aria-label={`Delete ${titleOf(row)} forever`}
                                                onClick={() =>
                                                    deleteForever(
                                                        [row],
                                                        `Delete "${titleOf(row)}" forever?`,
                                                        "Delete forever",
                                                    )
                                                }
                                            >
                                                Delete forever
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                disabled={busy}
                                                aria-label={`Restore ${titleOf(row)}`}
                                                onClick={() => restore([row])}
                                            >
                                                Restore
                                            </Button>
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    </>
                )}
            </PageContainer>
        </>
    );
}
