import EmptyState from "@/components/empty-state";
import { useConfirm } from "@/components/confirm-dialog";
import { Button } from "@/components/ui/button";
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from "@/components/ui/sheet";
import { History } from "lucide-react";
import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { routesFor } from "@/lib/page-routes";
import type { PageType } from "@/types/page";

type Version = {
    id: number;
    title: string;
    reason: string;
    user: { id: number; name: string } | null;
    created_at: string;
};

type Preview = { title: string; summary: string };

const REASON_LABELS: Record<string, string> = {
    autosave: "Edited",
    "pre-restore": "Saved before a restore",
};

function dayLabel(date: Date): string {
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);

    if (date.toDateString() === today.toDateString()) {
        return "Today";
    }

    if (date.toDateString() === yesterday.toDateString()) {
        return "Yesterday";
    }

    return date.toLocaleDateString([], {
        weekday: "short",
        month: "short",
        day: "numeric",
        year:
            date.getFullYear() === today.getFullYear() ? undefined : "numeric",
    });
}

/** Groups versions (newest first) under a heading per calendar day. */
function byDay(versions: Version[]): { day: string; items: Version[] }[] {
    const groups: { day: string; items: Version[] }[] = [];

    for (const version of versions) {
        const day = dayLabel(new Date(version.created_at));
        const last = groups[groups.length - 1];

        if (last?.day === day) {
            last.items.push(version);
        } else {
            groups.push({ day, items: [version] });
        }
    }

    return groups;
}

/**
 * Past versions of a page or board, with preview and restore. Renders no
 * heading or chrome so it can sit in an inline panel or a sheet. Loads on mount.
 */
export function VersionHistoryPanel({
    type,
    id,
    disabled,
    canRestore = true,
    onRestored = () => window.location.reload(),
    onClose = () => {},
}: {
    type: PageType;
    id: number;
    disabled: boolean;
    canRestore?: boolean;
    onRestored?: () => void;
    onClose?: () => void;
}) {
    const [versions, setVersions] = useState<Version[] | null>(null);
    const [selected, setSelected] = useState<number | null>(null);
    const [preview, setPreview] = useState<Preview | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [restoring, setRestoring] = useState(false);
    const { versions: routes } = routesFor(type);

    const load = async () => {
        setError(null);
        setVersions(null);
        setSelected(null);
        setPreview(null);

        try {
            const { data } = await api<{ data: Version[] }>(
                "get",
                routes.index(id).url,
            );

            setVersions(data);
        } catch {
            setVersions([]);
            setError("Couldn't load versions.");
        }
    };

    useEffect(() => {
        void load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    const select = async (versionId: number) => {
        setSelected(versionId);
        setPreview(null);
        setError(null);

        try {
            const { data } = await api<{ data: Preview }>(
                "get",
                routes.show([id, versionId]).url,
            );

            setPreview(data);
        } catch {
            setError("Couldn't load that version. Pick it again to retry.");
        }
    };

    const { confirm, dialog } = useConfirm();

    const doRestore = async () => {
        if (
            selected === null ||
            !(await confirm({
                title: "Restore this version?",
                description: "The current state is saved first.",
                confirmLabel: "Restore version",
            }))
        ) {
            return;
        }

        setRestoring(true);

        try {
            await api("post", routes.restore([id, selected]).url);
            onClose();
            onRestored();
        } catch {
            setError("Couldn't restore that version.");
        } finally {
            setRestoring(false);
        }
    };

    return (
        <>
            {dialog}
            <div className="min-h-0 flex-1 overflow-y-auto text-sm">
                {versions === null && (
                    <div className="flex flex-col gap-1">
                        <div className="h-6 animate-pulse rounded bg-hover" />
                        <div className="h-6 animate-pulse rounded bg-hover" />
                        <div className="h-6 animate-pulse rounded bg-hover" />
                    </div>
                )}
                {versions?.length === 0 && !error && (
                    <EmptyState title="No versions yet" />
                )}
                {byDay(versions ?? []).map((group) => (
                    <section key={group.day} className="mb-2">
                        <h3 className="py-1 text-xs font-medium text-ink-3">
                            {group.day}
                        </h3>
                        <ul className="flex flex-col gap-0.5">
                            {group.items.map((v) => (
                                <li key={v.id}>
                                    <button
                                        type="button"
                                        className="flex w-full items-baseline gap-2 rounded-ctl px-2 py-1.5 text-left hover:bg-hover aria-pressed:bg-sel [@media(pointer:coarse)]:min-h-11"
                                        aria-pressed={selected === v.id}
                                        onClick={() => select(v.id)}
                                    >
                                        <span className="tabular-nums">
                                            {new Date(
                                                v.created_at,
                                            ).toLocaleTimeString([], {
                                                hour: "numeric",
                                                minute: "2-digit",
                                            })}
                                        </span>
                                        <span className="min-w-0 truncate text-ink-3">
                                            {v.user?.name ?? "Unknown"} ·{" "}
                                            {REASON_LABELS[v.reason] ??
                                                v.reason}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </div>
            {(preview || error) && (
                <div className="mt-2 max-h-[45dvh] shrink-0 overflow-y-auto border-t border-rule pt-3 text-sm">
                    {error && (
                        <p
                            role="alert"
                            className="flex items-center gap-2 text-bad-ink"
                        >
                            {error}
                            {versions?.length === 0 && (
                                <Button type="button" size="sm" onClick={load}>
                                    Retry
                                </Button>
                            )}
                        </p>
                    )}
                    {preview && (
                        <>
                            <div className="font-medium">{preview.title}</div>
                            <p className="mt-1 text-xs whitespace-pre-wrap text-ink-2">
                                {preview.summary}
                            </p>
                            {canRestore && (
                                <Button
                                    type="button"
                                    size="sm"
                                    className="mt-3"
                                    disabled={disabled || restoring}
                                    title={
                                        disabled
                                            ? "Wait for your changes to save"
                                            : undefined
                                    }
                                    onClick={doRestore}
                                >
                                    {restoring
                                        ? "Restoring…"
                                        : "Restore this version"}
                                </Button>
                            )}
                        </>
                    )}
                </div>
            )}
        </>
    );
}

/**
 * Inline right-hand panel for a page, sharing its slot and heading style with
 * Notes.
 */
export function VersionHistoryAside(
    props: React.ComponentProps<typeof VersionHistoryPanel>,
) {
    return (
        <aside
            id="history"
            aria-label="History"
            className="mt-6 flex scroll-mt-24 flex-col lg:sticky lg:top-14 lg:mt-0 lg:max-h-[calc(100dvh-4rem)]"
        >
            <h2 className="mb-2 text-base font-semibold">History</h2>
            <VersionHistoryPanel {...props} />
        </aside>
    );
}

/**
 * A History button that opens the panel in a side sheet, for surfaces with no
 * room for an inline column (boards).
 */
export default function VersionHistory(
    props: Omit<React.ComponentProps<typeof VersionHistoryPanel>, "onClose">,
) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                type="button"
                variant="quiet"
                size="icon"
                aria-haspopup="dialog"
                aria-expanded={open}
                aria-label="Version history"
                title="Version history"
                onClick={() => setOpen(true)}
            >
                <History className="size-4" aria-hidden />
            </Button>
            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent
                    side="right"
                    className="w-[min(24rem,92vw)] gap-0 border-rule bg-raised p-4 sm:max-w-none"
                >
                    <SheetTitle className="mb-2 text-base font-semibold">
                        History
                    </SheetTitle>
                    <SheetDescription className="sr-only">
                        Pick a version to preview it.
                    </SheetDescription>
                    <VersionHistoryPanel
                        {...props}
                        onClose={() => setOpen(false)}
                    />
                </SheetContent>
            </Sheet>
        </>
    );
}
