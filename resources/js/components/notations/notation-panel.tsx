import { Button } from "@/components/ui/button";
import { usePage } from "@inertiajs/react";
import { ChevronDown, ChevronRight } from "lucide-react";
import { useId, useState } from "react";
import { addNoteShortcut } from "@/lib/shortcuts";
import { api, refreshPage } from "@/lib/api";
import { store } from "@/routes/api/v1/pages/notations";
import type { Assignee, Notation, Selection } from "@/types/notation";
import NotationThread from "./notation-thread";

type Props = {
    pageId: number;
    notations: Notation[];
    assignableUsers: Assignee[];
    pending: Selection | null;
    activeId: number | null;
    onSelect: (id: number) => void;
    onDone: () => void;
};

function NotationForm({
    pageId,
    selection,
    assignableUsers,
    onDone,
}: {
    pageId: number;
    selection: Selection;
    assignableUsers: Assignee[];
    onDone: () => void;
}) {
    const { default_assignee_id, notationBodyMax } = usePage<{
        default_assignee_id: number;
        notationBodyMax: number;
    }>().props;
    const [body, setBody] = useState("");
    const [assigneeId, setAssigneeId] = useState(default_assignee_id);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState(false);
    const quoteId = useId();

    return (
        <form
            aria-label="New note"
            className="fixed inset-x-0 bottom-0 z-30 flex flex-col gap-3 border-t border-line bg-raised p-3 shadow-md lg:static lg:z-auto lg:mb-3 lg:rounded-md lg:border lg:shadow-none"
            onKeyDown={(e) => {
                if (e.key === "Escape") {
                    e.stopPropagation();
                    onDone();
                }
            }}
            onSubmit={async (e) => {
                e.preventDefault();
                setProcessing(true);
                setError(false);

                try {
                    await api("post", store(pageId).url, {
                        ...selection,
                        body,
                        assignee_id: assigneeId,
                    });
                    await refreshPage();
                    onDone();
                } catch {
                    setError(true);
                } finally {
                    setProcessing(false);
                }
            }}
        >
            <div className="flex flex-col gap-2">
                <blockquote
                    id={quoteId}
                    className="line-clamp-3 border-l-2 border-line pl-2 text-xs text-ink-2"
                >
                    {selection.quote}
                </blockquote>
                <textarea
                    autoFocus
                    required
                    maxLength={notationBodyMax}
                    rows={3}
                    className="input h-auto min-h-18 resize-none py-1.5 leading-5"
                    aria-label="Note"
                    aria-describedby={quoteId}
                    placeholder="Write your note"
                    value={body}
                    onChange={(e) => setBody(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) {
                            e.preventDefault();
                            e.currentTarget.form?.requestSubmit();
                        }
                    }}
                />
            </div>
            <label>
                <span className="label">Assign to</span>
                <select
                    className="input"
                    value={assigneeId}
                    onChange={(e) => setAssigneeId(Number(e.target.value))}
                >
                    {assignableUsers.map((user) => (
                        <option key={user.id} value={user.id}>
                            {user.id === default_assignee_id ? "Me" : user.name}
                        </option>
                    ))}
                </select>
            </label>
            {error && (
                <p role="alert" className="text-xs text-bad-ink">
                    Couldn't save your note. Your text is kept, so try again.
                </p>
            )}
            <div className="flex items-center justify-end gap-2">
                <span className="mr-auto text-xs text-ink-3 [@media(pointer:coarse)]:hidden">
                    Ctrl/⌘ Enter to add
                </span>
                <Button
                    type="button"
                    variant="quiet"
                    size="sm"
                    onClick={onDone}
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    variant="primary"
                    size="sm"
                    disabled={processing || !body.trim()}
                >
                    {processing ? "Adding…" : "Add note"}
                </Button>
            </div>
        </form>
    );
}

export default function NotationPanel({
    pageId,
    notations,
    assignableUsers,
    pending,
    activeId,
    onSelect,
    onDone,
}: Props) {
    const { auth } = usePage<{ auth: { user: { id: number } } }>().props;
    const [showResolved, setShowResolved] = useState(false);
    const [mineOnly, setMineOnly] = useState(false);
    const visible = notations
        .filter(
            (notation) =>
                !mineOnly ||
                notation.id === activeId ||
                notation.assignee.id === auth.user.id,
        )
        .sort((a, b) => a.from - b.from || a.id - b.id);
    const open = visible.filter((notation) => !notation.is_completed);
    const resolved = visible.filter((notation) => notation.is_completed);
    const openTotal = notations.filter(
        (notation) => !notation.is_completed,
    ).length;
    const mineTotal = notations.filter(
        (notation) =>
            !notation.is_completed && notation.assignee.id === auth.user.id,
    ).length;
    const resolvedVisible =
        showResolved || resolved.some((notation) => notation.id === activeId);

    const renderThread = (notation: Notation) => (
        <NotationThread
            key={notation.id}
            notation={notation}
            active={notation.id === activeId}
            onSelect={onSelect}
        />
    );

    return (
        <aside
            id="notes"
            aria-label="Notes"
            className="mt-6 scroll-mt-24 lg:sticky lg:top-14 lg:mt-0 lg:max-h-[calc(100dvh-4rem)] lg:overflow-y-auto"
        >
            <div className="flex items-center justify-between gap-2">
                <h2 className="text-base font-semibold">
                    Notes
                    {openTotal > 0 && (
                        <span className="ml-2 text-xs font-normal text-ink-2 tabular-nums">
                            {openTotal} open
                        </span>
                    )}
                </h2>
                {notations.length > 0 && (
                    <div
                        role="group"
                        aria-label="Show"
                        className="flex gap-0.5 rounded-ctl border border-line bg-raised p-0.5"
                    >
                        <Button
                            type="button"
                            variant="quiet"
                            size="sm"
                            aria-pressed={!mineOnly}
                            onClick={() => setMineOnly(false)}
                        >
                            All
                        </Button>
                        <Button
                            type="button"
                            variant="quiet"
                            size="sm"
                            aria-pressed={mineOnly}
                            onClick={() => setMineOnly(true)}
                        >
                            Mine
                            <span className="font-normal text-ink-3 tabular-nums">
                                {mineTotal}
                            </span>
                        </Button>
                    </div>
                )}
            </div>

            {pending && (
                <NotationForm
                    pageId={pageId}
                    selection={pending}
                    assignableUsers={assignableUsers}
                    onDone={onDone}
                />
            )}

            {notations.length === 0 && !pending && (
                <p className="mt-1 text-sm text-ink-2">
                    Select text in the page, then choose Add note
                    <span className="[@media(pointer:coarse)]:hidden">
                        {" "}
                        or press{" "}
                        <kbd className="font-sans">{addNoteShortcut}</kbd>
                    </span>
                    .
                </p>
            )}

            {mineOnly && notations.length > 0 && visible.length === 0 && (
                <p className="mt-2 text-sm text-ink-2">
                    Nothing is assigned to you on this page.
                </p>
            )}

            <ul className="mt-2 flex flex-col gap-0.5 divide-y divide-rule">
                {open.map(renderThread)}
            </ul>

            {resolved.length > 0 && (
                <section className="mt-4">
                    <h3>
                        <button
                            type="button"
                            className="flex items-center gap-1 text-xs font-medium text-ink-3 hover:text-ink [@media(pointer:coarse)]:min-h-11"
                            aria-expanded={resolvedVisible}
                            onClick={() => setShowResolved((value) => !value)}
                        >
                            {resolvedVisible ? (
                                <ChevronDown className="size-3.5" aria-hidden />
                            ) : (
                                <ChevronRight
                                    className="size-3.5"
                                    aria-hidden
                                />
                            )}
                            Resolved ({resolved.length})
                        </button>
                    </h3>
                    {resolvedVisible && (
                        <ul className="mt-1 flex flex-col gap-0.5 divide-y divide-rule">
                            {resolved.map(renderThread)}
                        </ul>
                    )}
                </section>
            )}
        </aside>
    );
}
