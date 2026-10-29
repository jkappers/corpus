import { useConfirm } from "@/components/confirm-dialog";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { usePage } from "@inertiajs/react";
import {
    Check,
    ChevronDown,
    ChevronRight,
    MoreHorizontal,
    Trash2,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";
import { api, refreshPage } from "@/lib/api";
import { absoluteFormatter, relativeTime } from "@/lib/relative-time";
import { destroy } from "@/routes/api/v1/notations";
import { complete, reopen } from "@/routes/api/v1/notations";
import { destroy as destroyReply } from "@/routes/api/v1/notation-replies";
import type { Notation } from "@/types/notation";
import NotationReplyForm from "./notation-reply-form";

type Person = { id: number; name: string };

function Byline({
    author,
    createdAt,
    assignee,
    viewerId,
    onDelete,
}: {
    author: Person;
    createdAt: string;
    assignee?: Person;
    viewerId: number;
    onDelete?: () => void;
}) {
    const mine = assignee?.id === viewerId;

    return (
        <p className="flex min-w-0 flex-wrap items-baseline gap-x-1.5 gap-y-0.5 text-xs text-ink-3">
            <span className="font-medium text-ink">{author.name}</span>
            <time
                dateTime={createdAt}
                title={absoluteFormatter.format(new Date(createdAt))}
            >
                {relativeTime(createdAt)}
            </time>
            {assignee && (
                <span
                    title="Assigned to"
                    className={`rounded-sm px-1.5 py-px ${mine ? "bg-sel font-medium text-primary" : "bg-hover text-ink-2"}`}
                >
                    <span aria-hidden>→ </span>
                    <span className="sr-only">Assigned to </span>
                    {mine ? "You" : assignee.name}
                </span>
            )}
            {onDelete && (
                <button
                    type="button"
                    className="ml-auto text-ink-3 underline-offset-2 hover:text-bad-ink hover:underline [@media(pointer:coarse)]:min-h-11"
                    onClick={onDelete}
                >
                    Delete
                </button>
            )}
        </p>
    );
}

export default function NotationThread({
    notation,
    active,
    onSelect,
}: {
    notation: Notation;
    active: boolean;
    onSelect: (id: number) => void;
}) {
    const ref = useRef<HTMLLIElement>(null);
    const { confirm, dialog } = useConfirm();
    const { auth } = usePage<{ auth: { user: Person } }>().props;
    const [override, setOverride] = useState<boolean | null>(null);
    const [expanded, setExpanded] = useState(false);
    const done = override ?? notation.is_completed;
    const showThread = done ? expanded : active;
    const resolver = notation.resolved_by ?? (override ? auth.user : null);
    const replyCount = notation.replies.length;

    useEffect(() => {
        if (active) {
            ref.current?.scrollIntoView({ block: "nearest" });
        }
    }, [active]);

    const setResolved = async (resolved: boolean, silent = false) => {
        setOverride(resolved);

        try {
            await api(
                resolved ? "put" : "delete",
                (resolved ? complete : reopen)(notation.id).url,
            );
            await refreshPage();

            if (!silent) {
                toast(resolved ? "Note resolved" : "Note reopened", {
                    action: {
                        label: "Undo",
                        onClick: () => setResolved(!resolved, true),
                    },
                });
            }
        } catch {
            toast.error(
                `Couldn't ${resolved ? "resolve" : "reopen"} this note.`,
                {
                    action: {
                        label: "Retry",
                        onClick: () => setResolved(resolved),
                    },
                },
            );
        } finally {
            setOverride(null);
        }
    };

    const deleteThread = async () => {
        if (
            await confirm({
                title: "Delete this note?",
                description: "The note and all of its replies are removed.",
                confirmLabel: "Delete note",
                destructive: true,
            })
        ) {
            await api("delete", destroy(notation.id).url);
            await refreshPage();
        }
    };

    const deleteReply = async (id: number) => {
        if (
            await confirm({
                title: "Delete this reply?",
                confirmLabel: "Delete reply",
                destructive: true,
            })
        ) {
            await api("delete", destroyReply(id).url);
            await refreshPage();
        }
    };

    const quote = (
        <button
            type="button"
            className={`block w-full truncate border-l-2 py-0.5 pl-2 text-left text-xs [@media(pointer:coarse)]:py-3 ${
                done
                    ? "border-rule text-ink-3 line-through"
                    : active
                      ? "border-primary text-ink-2"
                      : "border-edge text-ink-3 hover:text-ink-2"
            }`}
            onClick={() => onSelect(notation.id)}
        >
            {notation.quote}
        </button>
    );

    const menu = showThread && notation.can_delete && (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="quiet"
                    size="icon-sm"
                    aria-label="More actions"
                >
                    <MoreHorizontal aria-hidden />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem variant="destructive" onSelect={deleteThread}>
                    <Trash2 aria-hidden />
                    Delete note
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );

    const comments = (
        <>
            {notation.replies.length > 0 && (
                <ul className="mt-2 flex flex-col gap-2.5 border-t border-rule pt-2.5">
                    {notation.replies.map((reply) => (
                        <li key={reply.id}>
                            <Byline
                                author={reply.author}
                                createdAt={reply.created_at}
                                viewerId={auth.user.id}
                                onDelete={
                                    reply.can_delete
                                        ? () => deleteReply(reply.id)
                                        : undefined
                                }
                            />
                            <p className="mt-0.5 text-sm whitespace-pre-wrap text-ink">
                                {reply.body}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
            {!done && notation.can_reply && (
                <NotationReplyForm notationId={notation.id} />
            )}
        </>
    );

    return (
        <li
            ref={ref}
            data-notation-thread={notation.id}
            aria-current={active ? "true" : undefined}
            className={`rounded-md px-2 py-2.5 ${active ? "bg-sel ring-1 ring-primary/30 ring-inset" : ""}`}
            onClick={(event) => {
                if (
                    !(event.target as HTMLElement).closest(
                        'button, a, input, textarea, select, [role="menu"]',
                    )
                ) {
                    onSelect(notation.id);
                }
            }}
        >
            {dialog}

            {done ? (
                <div className="flex items-start gap-2">
                    <Check
                        className="mt-0.5 size-4 shrink-0 text-ok"
                        aria-hidden
                    />
                    <div className="min-w-0 flex-1">
                        {quote}
                        <p className="mt-1 text-xs text-ink-3">
                            Resolved
                            {resolver && ` by ${resolver.name}`}
                            {notation.completed_at && (
                                <>
                                    {" · "}
                                    <time
                                        dateTime={notation.completed_at}
                                        title={absoluteFormatter.format(
                                            new Date(notation.completed_at),
                                        )}
                                    >
                                        {relativeTime(notation.completed_at)}
                                    </time>
                                </>
                            )}
                        </p>
                    </div>
                    {notation.can_complete && (
                        <Button
                            type="button"
                            variant="quiet"
                            size="sm"
                            onClick={() => setResolved(false)}
                        >
                            Reopen
                        </Button>
                    )}
                    <Button
                        type="button"
                        variant="quiet"
                        size="icon-sm"
                        aria-expanded={expanded}
                        aria-label={expanded ? "Hide note" : "Show note"}
                        onClick={() => setExpanded((value) => !value)}
                    >
                        {expanded ? (
                            <ChevronDown className="size-4" aria-hidden />
                        ) : (
                            <ChevronRight className="size-4" aria-hidden />
                        )}
                    </Button>
                </div>
            ) : (
                <div className="flex items-start gap-1">
                    <div className="min-w-0 flex-1">
                        <Byline
                            author={notation.author}
                            createdAt={notation.created_at}
                            assignee={notation.assignee}
                            viewerId={auth.user.id}
                        />
                    </div>
                    {notation.can_complete && (
                        <Button
                            type="button"
                            variant="quiet"
                            size="icon-sm"
                            className="-mt-1"
                            aria-label="Resolve note"
                            title="Resolve"
                            onClick={() => setResolved(true)}
                        >
                            <Check aria-hidden />
                        </Button>
                    )}
                    {menu}
                </div>
            )}

            {(!done || showThread) && (
                <div className={done ? "mt-2" : "mt-0.5"}>
                    {done && (
                        <Byline
                            author={notation.author}
                            createdAt={notation.created_at}
                            assignee={notation.assignee}
                            viewerId={auth.user.id}
                        />
                    )}
                    <p
                        className={`text-sm whitespace-pre-wrap text-ink ${showThread ? "mt-0.5" : "line-clamp-3"}`}
                    >
                        {notation.body}
                    </p>
                    {!done && <div className="mt-2">{quote}</div>}
                    {!showThread && replyCount > 0 && (
                        <p className="mt-1.5 text-xs text-ink-3">
                            {replyCount}{" "}
                            {replyCount === 1 ? "reply" : "replies"}
                        </p>
                    )}
                </div>
            )}

            {showThread && comments}
            {done && showThread && (
                <div className="mt-1 flex justify-end">{menu}</div>
            )}
        </li>
    );
}
