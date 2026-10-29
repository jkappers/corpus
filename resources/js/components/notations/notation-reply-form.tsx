import { Button } from "@/components/ui/button";
import { usePage } from "@inertiajs/react";
import { useState } from "react";
import { api, refreshPage } from "@/lib/api";
import { store } from "@/routes/api/v1/notations/replies";

export default function NotationReplyForm({
    notationId,
}: {
    notationId: number;
}) {
    const { notationBodyMax } = usePage<{ notationBodyMax: number }>().props;
    const [body, setBody] = useState("");
    const [focused, setFocused] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState(false);

    const expanded = focused || body !== "";

    const submit = async () => {
        if (!body.trim() || processing) {
            return;
        }

        setProcessing(true);
        setError(false);

        try {
            await api("post", store(notationId).url, { body });
            await refreshPage();
            setBody("");
            setFocused(false);
        } catch {
            setError(true);
        } finally {
            setProcessing(false);
        }
    };

    return (
        <form
            aria-label="Reply"
            className="mt-2 flex flex-col gap-1.5"
            onSubmit={(e) => {
                e.preventDefault();
                submit();
            }}
        >
            <textarea
                rows={expanded ? 3 : 1}
                maxLength={notationBodyMax}
                className="input h-auto min-h-7 resize-none py-1"
                aria-label="Reply"
                placeholder="Reply"
                value={body}
                onFocus={() => setFocused(true)}
                onChange={(e) => setBody(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === "Escape") {
                        e.stopPropagation();
                        setBody("");
                        setFocused(false);
                        e.currentTarget.blur();
                    } else if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) {
                        e.preventDefault();
                        submit();
                    }
                }}
            />
            {error && (
                <p role="alert" className="text-xs text-bad-ink">
                    Couldn't send your reply. Try again.
                </p>
            )}
            {expanded && (
                <div className="flex items-center justify-end gap-1">
                    <span className="mr-auto text-xs text-ink-3 [@media(pointer:coarse)]:hidden">
                        Ctrl/⌘ Enter to send
                    </span>
                    <Button
                        type="button"
                        variant="quiet"
                        size="sm"
                        onClick={() => {
                            setBody("");
                            setFocused(false);
                        }}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        size="sm"
                        disabled={processing || !body.trim()}
                    >
                        Reply
                    </Button>
                </div>
            )}
        </form>
    );
}
