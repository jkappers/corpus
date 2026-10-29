import { api } from "@/lib/api";
import { search } from "@/routes/api/v1";
import { show } from "@/routes/pages";
import type { Editor } from "@tiptap/react";
import { useEditorState } from "@tiptap/react";
import { FileText } from "lucide-react";
import type { RefObject } from "react";
import { useEffect, useRef, useState } from "react";

type Result = {
    id: number;
    title: string;
    space?: { id: number; name: string };
};

type Status = "loading" | "done" | "error";

type MentionState = {
    from: number;
    to: number;
    query: string;
    top: number;
    left: number;
};

/** "@" at the start of a block or after whitespace, followed by the filter typed so far. */
const MENTION = /(?:^|\s)@([^\s@]{0,32})$/;

/**
 * The "@" page link menu. Opens while the caret sits right after "@" plus a filter, and takes over
 * arrow keys, Enter and Escape through `keyHandler` while open. Choosing a page inserts its title as a
 * plain link to the page, which is stored in the body as a normal markdown link.
 */
export default function PageLinkMenu({
    editor,
    container,
    keyHandler,
    pageId,
}: {
    editor: Editor;
    container: RefObject<HTMLDivElement | null>;
    keyHandler: { current: ((event: KeyboardEvent) => boolean) | null };
    pageId: number;
}) {
    const [index, setIndex] = useState(0);
    const [dismissedAt, setDismissedAt] = useState<number | null>(null);
    const [results, setResults] = useState<Result[]>([]);
    const [status, setStatus] = useState<Status>("loading");
    const latest = useRef(0);

    const mention = useEditorState({
        editor,
        selector: ({ editor: e }): MentionState | null => {
            const { empty, $from } = e.state.selection;

            if (
                !empty ||
                !e.isEditable ||
                !container.current ||
                $from.parent.type.name !== "paragraph" ||
                $from.marks().some((mark) => mark.type.name === "code")
            ) {
                return null;
            }

            const before = $from.parent.textBetween(
                0,
                $from.parentOffset,
                undefined,
                "￼",
            );
            const match = MENTION.exec(before);

            if (!match) {
                return null;
            }

            const caret = e.view.coordsAtPos($from.pos);
            const box = container.current.getBoundingClientRect();

            return {
                from: $from.pos - match[1].length - 1,
                to: $from.pos,
                query: match[1],
                top: Math.round(caret.bottom - box.top + 4),
                left: Math.round(Math.max(0, caret.left - box.left)),
            };
        },
        equalityFn: (a, b) => JSON.stringify(a) === JSON.stringify(b),
    });

    const open = mention !== null && mention.from !== dismissedAt;
    const query = mention?.query ?? "";
    const items = results.filter((result) => result.id !== pageId);
    const active = Math.min(index, Math.max(0, items.length - 1));

    useEffect(() => {
        if (!open) {
            return;
        }

        const request = ++latest.current;
        setStatus("loading");

        const timer = setTimeout(async () => {
            try {
                const { data } = await api<{ data: Result[] }>(
                    "get",
                    search({ query: { q: query, type: "page" } }).url,
                );

                if (request === latest.current) {
                    setResults(data);
                    setIndex(0);
                    setStatus("done");
                }
            } catch {
                if (request === latest.current) {
                    setStatus("error");
                }
            }
        }, 200);

        return () => clearTimeout(timer);
    }, [open, query]);

    useEffect(() => {
        if (mention === null) {
            setDismissedAt(null);
        }
    }, [mention]);

    const choose = (result: Result) => {
        if (!mention) {
            return;
        }

        editor
            .chain()
            .focus()
            .insertContentAt({ from: mention.from, to: mention.to }, [
                {
                    type: "text",
                    text: result.title || "Untitled",
                    marks: [
                        {
                            type: "link",
                            attrs: { href: show(result.id).url },
                        },
                    ],
                },
                { type: "text", text: " " },
            ])
            .run();
    };

    keyHandler.current =
        open && items.length > 0
            ? (event) => {
                  if (event.key === "ArrowDown" || event.key === "ArrowUp") {
                      const step = event.key === "ArrowDown" ? 1 : -1;
                      setIndex((active + step + items.length) % items.length);

                      return true;
                  }

                  if (event.key === "Enter" || event.key === "Tab") {
                      choose(items[active]);

                      return true;
                  }

                  if (event.key === "Escape") {
                      setDismissedAt(mention.from);

                      return true;
                  }

                  return false;
              }
            : null;

    if (!open || (status === "done" && items.length === 0 && query === "")) {
        return null;
    }

    return (
        <ul
            role="listbox"
            aria-label="Link to page"
            className="absolute z-20 flex max-h-72 w-72 flex-col overflow-y-auto rounded-md border border-line bg-raised p-1 shadow-md"
            style={{ top: mention.top, left: mention.left }}
        >
            {items.length === 0 && (
                <li className="px-2 py-1 text-sm text-ink-3">
                    {status === "error"
                        ? "Couldn't load pages."
                        : status === "loading"
                          ? "Searching…"
                          : "No matching pages."}
                </li>
            )}
            {items.map((result, i) => (
                <li
                    key={result.id}
                    role="option"
                    aria-selected={i === active}
                    className="flex cursor-pointer items-center gap-2 rounded-ctl px-2 py-1 text-sm aria-selected:bg-sel"
                    onMouseDown={(e) => e.preventDefault()}
                    onMouseEnter={() => setIndex(i)}
                    onClick={() => choose(result)}
                >
                    <FileText
                        className="size-4 shrink-0 text-ink-3"
                        aria-hidden
                    />
                    <span className="flex-1 truncate">
                        {result.title || "Untitled"}
                    </span>
                    {result.space && (
                        <span className="truncate text-xs text-ink-3">
                            {result.space.name}
                        </span>
                    )}
                </li>
            ))}
        </ul>
    );
}
