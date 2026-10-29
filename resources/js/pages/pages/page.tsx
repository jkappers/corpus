import { Button } from "@/components/ui/button";
import { Head } from "@inertiajs/react";
import { History, MessageSquare } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";
import ItemLocation from "@/components/item-location";
import { useDiscardUntouched } from "@/hooks/use-discard-untouched";
import NotationPanel from "@/components/notations/notation-panel";
import type { Assignee, Notation, Selection } from "@/types/notation";
import { VersionHistoryAside } from "@/components/version-history";
import PageEditor from "@/components/editor/page-editor";
import type { PageEditorHandle } from "@/components/editor/page-editor";
import { useAutosave } from "@/hooks/use-autosave";
import { useUntitledTitle } from "@/hooks/use-untitled-title";
import type { SaveStatus } from "@/hooks/use-autosave";
import { refreshPage } from "@/lib/api";
import { update } from "@/routes/api/v1/pages";

type PageRecord = {
    id: number;
    title: string;
    body: string | null;
    space_id: number;
    updated_at: string;
};

type PageProps = {
    page: PageRecord;
    notations: Notation[];
    assignableUsers: Assignee[];
    can_edit: boolean;
};

/**
 * Quiet save state for the page bar. Shows only while work is in flight or has failed;
 * a settled save says nothing. Only failures are announced to screen readers;
 * routine saves would interrupt them on every pause in typing.
 */
function SaveIndicator({
    status,
    onRetry,
}: {
    status: SaveStatus;
    onRetry: () => void;
}) {
    const failed = status === "error" || status === "conflict";
    const text = {
        idle: "",
        pending: "Unsaved",
        saving: "Saving…",
        saved: "",
        error: "Not saved. Your changes are only in this tab.",
        conflict: "Changed elsewhere. Reload to get the latest.",
    }[status];

    return (
        <div
            className={`flex min-w-0 items-center gap-2 text-xs ${failed ? "text-bad-ink" : "text-ink-3"}`}
        >
            <span className="truncate" aria-hidden={!failed}>
                {text}
            </span>
            <span role="alert" className="sr-only">
                {failed ? text : ""}
            </span>
            {status === "error" && (
                <Button type="button" size="sm" onClick={onRetry}>
                    Retry
                </Button>
            )}
            {status === "conflict" && (
                <Button
                    type="button"
                    size="sm"
                    onClick={() => window.location.reload()}
                >
                    Reload
                </Button>
            )}
        </div>
    );
}

export default function DocPage(props: PageProps) {
    // Bumped after a restore so the editor remounts on the restored body.
    const [revision, setRevision] = useState(0);

    return (
        <PageBody
            key={`${props.page.id}:${revision}`}
            {...props}
            onRestored={async () => {
                await refreshPage();
                setRevision((value) => value + 1);
                toast("Version restored");
            }}
        />
    );
}

function PageBody({
    page,
    notations,
    assignableUsers,
    can_edit,
    onRestored,
}: PageProps & { onRestored: () => void }) {
    const untitledTitle = useUntitledTitle();
    const editorRef = useRef<PageEditorHandle>(null);
    const [pending, setPending] = useState<Selection | null>(null);
    const [activeId, setActiveId] = useState<number | null>(() => {
        const id = new URLSearchParams(window.location.search).get("notation");

        return id ? Number(id) : null;
    });
    const [notesOpen, setNotesOpen] = useState(
        () => notations.length > 0 || activeId !== null,
    );
    const [historyOpen, setHistoryOpen] = useState(false);
    const [title, setTitle] = useState(page.title);
    const readOnly = !can_edit;
    const { save, flush, rebase, status } = useAutosave(
        update(page.id).url,
        page.updated_at,
    );

    const unsaved =
        status === "pending" ||
        status === "saving" ||
        status === "error" ||
        status === "conflict";
    const touch = useDiscardUntouched("page", page.id);
    const edit = (changes: { title?: string; body?: string }) => {
        touch();
        save(changes);
    };

    const showHistory = historyOpen;
    const showNotes = !showHistory && (notesOpen || pending !== null);
    const noteCount = useRef(notations.length);

    // A newly added note keeps the panel open once its form closes.
    useEffect(() => {
        if (notations.length > noteCount.current) {
            setHistoryOpen(false);
            setNotesOpen(true);
        }

        noteCount.current = notations.length;
    }, [notations.length]);

    const openNotes = () => {
        setHistoryOpen(false);
        setNotesOpen(true);
        // Below the body on small screens, so bring it into view.
        requestAnimationFrame(() => {
            if (!window.matchMedia("(min-width: 64rem)").matches) {
                document
                    .getElementById("notes")
                    ?.scrollIntoView({ behavior: "smooth" });
            }
        });
    };

    const closeNoteForm = () => {
        const selection = pending;
        setPending(null);

        if (selection) {
            editorRef.current?.focusRange(selection.from, selection.to);
        }
    };

    useEffect(() => {
        if (!unsaved) {
            return;
        }

        const guard = (event: BeforeUnloadEvent) => event.preventDefault();

        window.addEventListener("beforeunload", guard);

        return () => window.removeEventListener("beforeunload", guard);
    }, [unsaved]);

    useEffect(() => {
        if (readOnly) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                !event.altKey &&
                event.key.toLowerCase() === "s"
            ) {
                event.preventDefault();

                if (status === "idle") {
                    toast("No changes to save");
                }

                flush();
            }
        };

        window.addEventListener("keydown", onKeyDown);

        return () => window.removeEventListener("keydown", onKeyDown);
    }, [readOnly, flush, status]);

    return (
        <>
            <Head title={title || untitledTitle} />
            <div className="sticky top-12 z-20 border-b border-rule bg-ground md:top-0">
                <div className="mx-auto flex h-11 max-w-[62rem] items-center justify-between gap-3 px-4 md:px-6">
                    <ItemLocation
                        type="page"
                        id={page.id}
                        spaceId={page.space_id}
                        canMove={can_edit && !unsaved}
                        onMoved={rebase}
                        trailingSlash={false}
                    />
                    <div className="flex min-w-0 items-center gap-1">
                        {readOnly ? (
                            <span className="text-xs text-ink-3">
                                View only
                            </span>
                        ) : (
                            <SaveIndicator status={status} onRetry={flush} />
                        )}
                        <Button
                            type="button"
                            variant="quiet"
                            size="icon"
                            className="relative"
                            aria-pressed={showNotes}
                            aria-controls="notes"
                            aria-label={
                                notations.length > 0
                                    ? `${showNotes ? "Hide" : "Show"} notes (${notations.length})`
                                    : showNotes
                                      ? "Hide notes"
                                      : "Show notes"
                            }
                            title={showNotes ? "Hide notes" : "Show notes"}
                            onClick={() => {
                                if (showNotes) {
                                    setNotesOpen(false);
                                    setPending(null);
                                } else {
                                    openNotes();
                                }
                            }}
                        >
                            <MessageSquare className="size-4" aria-hidden />
                            {notations.length > 0 && (
                                <span
                                    aria-hidden
                                    className="absolute -top-0.5 -right-0.5 min-w-3.5 rounded-full bg-ink px-1 text-center text-[0.625rem] leading-3.5 text-ground tabular-nums"
                                >
                                    {notations.length}
                                </span>
                            )}
                        </Button>
                        <Button
                            type="button"
                            variant="quiet"
                            size="icon"
                            aria-pressed={showHistory}
                            aria-controls="history"
                            aria-label={
                                showHistory ? "Hide history" : "Show history"
                            }
                            title={
                                showHistory ? "Hide history" : "Show history"
                            }
                            onClick={() => {
                                if (showHistory) {
                                    setHistoryOpen(false);
                                } else {
                                    setPending(null);
                                    setHistoryOpen(true);
                                }
                            }}
                        >
                            <History className="size-4" aria-hidden />
                        </Button>
                    </div>
                </div>
            </div>

            <div
                className={`mx-auto max-w-[62rem] gap-12 px-4 pt-8 pb-24 md:px-6 md:pt-12 ${showNotes || showHistory ? "lg:grid lg:grid-cols-[minmax(0,40rem)_18rem]" : ""}`}
            >
                <div
                    className={
                        showNotes || showHistory ? "" : "mx-auto max-w-[40rem]"
                    }
                >
                    <input
                        value={title}
                        readOnly={readOnly}
                        placeholder={untitledTitle}
                        aria-label="Title"
                        autoFocus={!readOnly && page.title === untitledTitle}
                        onFocus={(e) => {
                            if (title === untitledTitle) {
                                e.currentTarget.select();
                            }
                        }}
                        onKeyDown={(e) => {
                            if (
                                e.key === "Enter" ||
                                (e.key === "ArrowDown" && !readOnly)
                            ) {
                                e.preventDefault();
                                editorRef.current?.focusStart();
                            }
                        }}
                        className="mb-4 w-full bg-transparent text-[2rem] leading-10 font-semibold tracking-tight outline-none placeholder:text-ink-3"
                        onChange={(e) => {
                            setTitle(e.target.value);
                            edit({ title: e.target.value });
                        }}
                        onBlur={flush}
                    />

                    <PageEditor
                        ref={editorRef}
                        pageId={page.id}
                        initialMarkdown={page.body ?? ""}
                        readOnly={readOnly}
                        onChange={(body) => edit({ body })}
                        notations={notations}
                        activeNotationId={activeId}
                        onSelectNotation={(id) => {
                            setActiveId(id);
                            openNotes();
                        }}
                        onAddNote={(selection) => {
                            setHistoryOpen(false);
                            setPending(selection);
                        }}
                        composing={pending !== null}
                    />
                </div>

                {showHistory && (
                    <VersionHistoryAside
                        type="page"
                        id={page.id}
                        canRestore={can_edit}
                        disabled={unsaved}
                        onRestored={onRestored}
                        onClose={() => setHistoryOpen(false)}
                    />
                )}
                {showNotes && (
                    <NotationPanel
                        pageId={page.id}
                        notations={notations}
                        assignableUsers={assignableUsers}
                        pending={pending}
                        activeId={activeId}
                        onSelect={setActiveId}
                        onDone={closeNoteForm}
                    />
                )}
            </div>
        </>
    );
}
