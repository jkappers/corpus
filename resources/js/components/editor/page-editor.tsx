import { Button } from "@/components/ui/button";
import { router, usePage } from "@inertiajs/react";
import { Markdown } from "@tiptap/markdown";
import type { Editor } from "@tiptap/react";
import { EditorContent, useEditor, useEditorState } from "@tiptap/react";
import { TaskItem, TaskList } from "@tiptap/extension-list";
import { TableKit } from "@tiptap/extension-table";
import StarterKit from "@tiptap/starter-kit";
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    Bold,
    Code,
    Heading1,
    Heading2,
    Heading3,
    Info,
    Italic,
    Link as LinkIcon,
    List,
    ListChecks,
    ListOrdered,
    MessageSquarePlus,
    Minus,
    Paperclip,
    Pilcrow,
    Quote,
    SquareCode,
    Table as TableIcon,
    X,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import type { Ref, RefObject } from "react";
import {
    useEffect,
    useImperativeHandle,
    useLayoutEffect,
    useRef,
    useState,
} from "react";
import { useAttachmentUpload } from "@/hooks/use-attachment-upload";
import { addNoteShortcut, modKey } from "@/lib/shortcuts";
import type { AttachmentConfig } from "@/types/attachment";
import type { Notation, Selection } from "@/types/notation";
import { ALERT_TYPES, AlertBlockquote } from "./alert-extension";
import { AttachmentUploads, insertFiles } from "./attachment-extension";
import { AttachmentCard } from "./attachment-node";
import { HighlightedCodeBlock } from "./code-block-extension";
import {
    DEFAULT_ALIGN,
    DEFAULT_SIZE,
    IMAGE_SIZES,
    LayoutImage,
} from "./image-extension";
import type { ImageAlign } from "./image-extension";
import { NotationHighlights, refreshNotations } from "./notation-extension";
import PageLinkMenu from "./page-link-menu";

export type PageEditorHandle = {
    /** Put the caret at the start of the body. */
    focusStart: () => void;
    /** Focus the body with a range selected, e.g. after closing the note form. */
    focusRange: (from: number, to: number) => void;
};

type Props = {
    ref?: Ref<PageEditorHandle>;
    pageId: number;
    initialMarkdown: string;
    readOnly: boolean;
    onChange: (markdown: string) => void;
    notations: Notation[];
    activeNotationId: number | null;
    onSelectNotation: (id: number) => void;
    onAddNote: (selection: Selection) => void;
    composing: boolean;
};

type Tool = {
    label: string;
    shortcut?: string;
    icon: LucideIcon;
    active: (e: Editor) => boolean;
    run: (e: Editor) => void;
};

const MARKS: Tool[] = [
    {
        label: "Bold",
        shortcut: `${modKey}B`,
        icon: Bold,
        active: (e) => e.isActive("bold"),
        run: (e) => e.chain().focus().toggleBold().run(),
    },
    {
        label: "Italic",
        shortcut: `${modKey}I`,
        icon: Italic,
        active: (e) => e.isActive("italic"),
        run: (e) => e.chain().focus().toggleItalic().run(),
    },
    {
        label: "Inline code",
        shortcut: `${modKey}E`,
        icon: Code,
        active: (e) => e.isActive("code"),
        run: (e) => e.chain().focus().toggleCode().run(),
    },
];

type SlashItem = {
    label: string;
    hint: string;
    icon: LucideIcon;
    keywords?: string;
    run: (e: Editor, attach: () => void) => void;
};

/** Blocks offered by typing "/" on an empty line. Hints show the markdown that does the same. */
const SLASH_ITEMS: SlashItem[] = [
    {
        label: "Text",
        hint: "",
        icon: Pilcrow,
        keywords: "paragraph",
        run: (e) => e.chain().focus().setParagraph().run(),
    },
    {
        label: "Heading 1",
        hint: "#",
        icon: Heading1,
        keywords: "h1 title",
        run: (e) => e.chain().focus().setHeading({ level: 1 }).run(),
    },
    {
        label: "Heading 2",
        hint: "##",
        icon: Heading2,
        keywords: "h2 subtitle",
        run: (e) => e.chain().focus().setHeading({ level: 2 }).run(),
    },
    {
        label: "Heading 3",
        hint: "###",
        icon: Heading3,
        keywords: "h3",
        run: (e) => e.chain().focus().setHeading({ level: 3 }).run(),
    },
    {
        label: "Bullet list",
        hint: "-",
        icon: List,
        keywords: "ul unordered",
        run: (e) => e.chain().focus().toggleBulletList().run(),
    },
    {
        label: "Numbered list",
        hint: "1.",
        icon: ListOrdered,
        keywords: "ol ordered",
        run: (e) => e.chain().focus().toggleOrderedList().run(),
    },
    {
        label: "Quote",
        hint: ">",
        icon: Quote,
        keywords: "blockquote",
        run: (e) => e.chain().focus().toggleBlockquote().run(),
    },
    {
        label: "Task list",
        hint: "- [ ]",
        icon: ListChecks,
        keywords: "todo checkbox checklist",
        run: (e) => e.chain().focus().toggleTaskList().run(),
    },
    {
        label: "Table",
        hint: "| a | b |",
        icon: TableIcon,
        keywords: "grid rows columns",
        run: (e) =>
            e
                .chain()
                .focus()
                .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
                .run(),
    },
    ...ALERT_TYPES.map((alertType): SlashItem => ({
        label: alertType.charAt(0).toUpperCase() + alertType.slice(1),
        hint: `> [!${alertType.toUpperCase()}]`,
        icon: Info,
        keywords: `callout alert admonition ${alertType}`,
        run: (e) =>
            e
                .chain()
                .focus()
                .toggleBlockquote()
                .updateAttributes("blockquote", { alertType })
                .run(),
    })),
    {
        label: "Code block",
        hint: "```",
        icon: SquareCode,
        keywords: "pre",
        run: (e) => e.chain().focus().toggleCodeBlock().run(),
    },
    {
        label: "Divider",
        hint: "---",
        icon: Minus,
        keywords: "hr rule line",
        run: (e) => e.chain().focus().setHorizontalRule().run(),
    },
    {
        label: "Attach file",
        hint: "",
        icon: Paperclip,
        keywords: "image upload video audio",
        run: (_e, attach) => attach(),
    },
];

function matchesSlash(item: SlashItem, query: string): boolean {
    const needle = query.trim().toLowerCase();

    return (
        needle === "" ||
        `${item.label} ${item.keywords ?? ""}`
            .toLowerCase()
            .split(" ")
            .some((word) => word.startsWith(needle))
    );
}

function captureSelection(editor: Editor): Selection | null {
    const { from, to, empty } = editor.state.selection;

    if (empty) {
        return null;
    }

    return { from, to, quote: editor.state.doc.textBetween(from, to, " ") };
}

function ToolButton({
    tool,
    pressed,
    editor,
}: {
    tool: Tool;
    pressed: boolean;
    editor: Editor;
}) {
    const Icon = tool.icon;

    return (
        <Button
            type="button"
            aria-label={tool.label}
            title={
                tool.shortcut ? `${tool.label} (${tool.shortcut})` : tool.label
            }
            aria-pressed={pressed}
            variant="quiet"
            size="icon-sm"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => tool.run(editor)}
        >
            <Icon className="size-4" aria-hidden />
        </Button>
    );
}

function Divider() {
    return <span aria-hidden className="mx-0.5 h-4 w-px shrink-0 bg-line" />;
}

const ALIGN_OPTIONS: { value: ImageAlign; label: string; icon: LucideIcon }[] =
    [
        { value: "left", label: "Align left", icon: AlignLeft },
        { value: "center", label: "Align center", icon: AlignCenter },
        { value: "right", label: "Align right", icon: AlignRight },
    ];

function ImageControls({
    editor,
    image,
}: {
    editor: Editor;
    image: { align: ImageAlign; size: number };
}) {
    const update = (attrs: { align?: ImageAlign; size?: number }) =>
        editor.chain().focus().updateAttributes("image", attrs).run();

    return (
        <>
            {ALIGN_OPTIONS.map(({ value, label, icon: Icon }) => (
                <Button
                    key={value}
                    type="button"
                    aria-label={label}
                    title={label}
                    aria-pressed={image.align === value}
                    variant="quiet"
                    size="icon-sm"
                    onMouseDown={(e) => e.preventDefault()}
                    onClick={() => update({ align: value })}
                >
                    <Icon className="size-4" aria-hidden />
                </Button>
            ))}
            <select
                aria-label="Image width"
                className="input touch-target h-6 w-auto shrink-0 py-0 text-xs"
                value={image.size}
                onChange={(e) => update({ size: Number(e.target.value) })}
            >
                {IMAGE_SIZES.map((size) => (
                    <option key={size} value={size}>
                        {size}%
                    </option>
                ))}
            </select>
        </>
    );
}

function LinkForm({
    editor,
    initialHref,
    onClose,
}: {
    editor: Editor;
    initialHref: string;
    onClose: () => void;
}) {
    const [href, setHref] = useState(initialHref);

    return (
        <form
            aria-label="Link"
            className="absolute top-full left-0 z-20 mt-1 flex w-80 max-w-[80vw] gap-1 rounded-md border border-line bg-raised p-1.5 shadow-md"
            onSubmit={(e) => {
                e.preventDefault();
                const url = href.trim();

                if (url) {
                    editor
                        .chain()
                        .focus()
                        .extendMarkRange("link")
                        .setLink({ href: url })
                        .run();
                }

                onClose();
            }}
            onKeyDown={(e) => {
                if (e.key === "Escape") {
                    e.preventDefault();
                    e.stopPropagation();
                    onClose();
                    editor.commands.focus();
                }
            }}
        >
            <input
                autoFocus
                type="url"
                inputMode="url"
                aria-label="Link URL"
                placeholder="https://"
                className="input"
                value={href}
                onChange={(e) => setHref(e.target.value)}
            />
            <Button type="submit" size="sm" disabled={!href.trim()}>
                {initialHref ? "Update" : "Apply"}
            </Button>
            {initialHref && (
                <Button
                    type="button"
                    size="sm"
                    variant="quiet"
                    onClick={() => {
                        editor
                            .chain()
                            .focus()
                            .extendMarkRange("link")
                            .unsetLink()
                            .run();
                        onClose();
                    }}
                >
                    Remove
                </Button>
            )}
        </form>
    );
}

/**
 * Formatting for the current selection, floating just above it. Readers only get Add note.
 * One Tab stop; arrow keys move between controls and Escape returns to the text.
 */
function SelectionMenu({
    editor,
    container,
    readOnly,
    hidden,
    linkOpen,
    setLinkOpen,
    onAddNote,
}: {
    editor: Editor;
    container: RefObject<HTMLDivElement | null>;
    readOnly: boolean;
    hidden: boolean;
    linkOpen: boolean;
    setLinkOpen: (open: boolean) => void;
    onAddNote: (selection: Selection) => void;
}) {
    const menuRef = useRef<HTMLDivElement>(null);
    // Focus inside the body (text or this menu); survives Tab from the text into the menu.
    const [focusInside, setFocusInside] = useState(false);

    useEffect(() => {
        const onFocusIn = (event: FocusEvent) =>
            setFocusInside(!!container.current?.contains(event.target as Node));

        setFocusInside(!!container.current?.contains(document.activeElement));
        document.addEventListener("focusin", onFocusIn);

        return () => document.removeEventListener("focusin", onFocusIn);
    }, [container]);

    // Re-render on selection/format changes (Tiptap v3 doesn't by default).
    const state = useEditorState({
        editor,
        selector: ({ editor: e }) => {
            const { empty, from } = e.state.selection;

            if (empty || !container.current) {
                return null;
            }

            const start = e.view.coordsAtPos(from);
            const box = container.current.getBoundingClientRect();
            const image = e.isActive("image")
                ? {
                      align: (e.getAttributes("image").align ??
                          DEFAULT_ALIGN) as ImageAlign,
                      size: Number(
                          e.getAttributes("image").size ?? DEFAULT_SIZE,
                      ),
                  }
                : null;

            return {
                top: Math.round(start.top - box.top),
                left: Math.round(Math.max(0, start.left - box.left)),
                marks: MARKS.map((tool) => tool.active(e)).join(),
                link: e.isActive("link")
                    ? String(e.getAttributes("link").href ?? "")
                    : null,
                imageAlign: image?.align ?? null,
                imageSize: image?.size ?? null,
            };
        },
        equalityFn: (a, b) => JSON.stringify(a) === JSON.stringify(b),
    });

    // Keep a single Tab stop: only the first control is tabbable.
    useLayoutEffect(() => {
        menuRef.current
            ?.querySelectorAll<HTMLElement>(":scope > button, :scope > select")
            .forEach((item, index) => {
                item.tabIndex = index === 0 ? 0 : -1;
            });
    });

    if (!state || hidden || !(focusInside || linkOpen || readOnly)) {
        return null;
    }

    const markState = state.marks.split(",");
    const addNote = () => {
        const selection = captureSelection(editor);

        if (selection) {
            onAddNote(selection);
        }
    };

    return (
        <div
            ref={menuRef}
            role="toolbar"
            aria-label="Selection"
            className="absolute z-20 flex -translate-y-full items-center gap-0.5 rounded-md border border-line bg-raised p-0.5 shadow-md"
            style={{ top: state.top - 6, left: state.left }}
            onKeyDown={(e) => {
                if (e.key === "Escape" && !linkOpen) {
                    e.preventDefault();
                    editor.commands.focus();

                    return;
                }

                if (
                    (e.key !== "ArrowRight" && e.key !== "ArrowLeft") ||
                    e.target instanceof HTMLSelectElement ||
                    e.target instanceof HTMLInputElement
                ) {
                    return;
                }

                const items = Array.from(
                    e.currentTarget.querySelectorAll<HTMLElement>(
                        ":scope > button:not(:disabled), :scope > select",
                    ),
                );
                const at = items.indexOf(e.target as HTMLElement);

                if (at === -1) {
                    return;
                }

                e.preventDefault();
                const step = e.key === "ArrowRight" ? 1 : -1;
                items[(at + step + items.length) % items.length].focus();
            }}
        >
            {!readOnly && state.imageAlign && state.imageSize !== null && (
                <>
                    <ImageControls
                        editor={editor}
                        image={{
                            align: state.imageAlign,
                            size: state.imageSize,
                        }}
                    />
                    <Divider />
                </>
            )}
            {!readOnly && !state.imageAlign && (
                <>
                    {MARKS.map((tool, i) => (
                        <ToolButton
                            key={tool.label}
                            tool={tool}
                            pressed={markState[i] === "true"}
                            editor={editor}
                        />
                    ))}
                    <Button
                        type="button"
                        aria-label={state.link ? "Edit link" : "Link"}
                        title={`${state.link ? "Edit link" : "Link"} (${modKey}K)`}
                        aria-pressed={state.link !== null}
                        aria-expanded={linkOpen}
                        variant="quiet"
                        size="icon-sm"
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={() => setLinkOpen(!linkOpen)}
                    >
                        <LinkIcon className="size-4" aria-hidden />
                    </Button>
                    <Divider />
                </>
            )}
            <Button
                type="button"
                variant="quiet"
                size="sm"
                title={`Add note (${addNoteShortcut})`}
                onMouseDown={(e) => e.preventDefault()}
                onClick={addNote}
            >
                <MessageSquarePlus className="size-3.5" aria-hidden />
                Add note
            </Button>
            {linkOpen && (
                <LinkForm
                    editor={editor}
                    initialHref={state.link ?? ""}
                    onClose={() => setLinkOpen(false)}
                />
            )}
        </div>
    );
}

type SlashState = {
    from: number;
    to: number;
    query: string;
    top: number;
    left: number;
};

/**
 * The "/" block menu. Opens when a paragraph holds only "/" plus a filter, and
 * takes over arrow keys, Enter and Escape through `keyHandler` while open.
 */
function SlashMenu({
    editor,
    container,
    keyHandler,
    onAttach,
}: {
    editor: Editor;
    container: RefObject<HTMLDivElement | null>;
    keyHandler: { current: ((event: KeyboardEvent) => boolean) | null };
    onAttach: () => void;
}) {
    const [index, setIndex] = useState(0);
    const [dismissedAt, setDismissedAt] = useState<number | null>(null);
    const slash = useEditorState({
        editor,
        selector: ({ editor: e }): SlashState | null => {
            const { empty, $from } = e.state.selection;

            if (
                !empty ||
                !e.isEditable ||
                !container.current ||
                $from.parent.type.name !== "paragraph"
            ) {
                return null;
            }

            const text = $from.parent.textContent;
            const match = /^\/([\w ]{0,24})$/.exec(text);

            if (!match || $from.parentOffset !== text.length) {
                return null;
            }

            const caret = e.view.coordsAtPos($from.pos);
            const box = container.current.getBoundingClientRect();

            return {
                from: $from.start(),
                to: $from.pos,
                query: match[1],
                top: Math.round(caret.bottom - box.top + 4),
                left: Math.round(Math.max(0, caret.left - box.left)),
            };
        },
        equalityFn: (a, b) => JSON.stringify(a) === JSON.stringify(b),
    });

    const open = slash !== null && slash.from !== dismissedAt;
    const items = open
        ? SLASH_ITEMS.filter((item) => matchesSlash(item, slash.query))
        : [];
    const active = Math.min(index, Math.max(0, items.length - 1));

    useEffect(() => {
        setIndex(0);
    }, [slash?.query]);

    useEffect(() => {
        if (slash === null) {
            setDismissedAt(null);
        }
    }, [slash]);

    const choose = (item: SlashItem) => {
        if (!slash) {
            return;
        }

        editor
            .chain()
            .focus()
            .deleteRange({ from: slash.from, to: slash.to })
            .run();
        item.run(editor, onAttach);
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
                      setDismissedAt(slash.from);

                      return true;
                  }

                  return false;
              }
            : null;

    if (!open || items.length === 0) {
        return null;
    }

    return (
        <ul
            role="listbox"
            aria-label="Insert block"
            className="absolute z-20 flex max-h-72 w-60 flex-col overflow-y-auto rounded-md border border-line bg-raised p-1 shadow-md"
            style={{ top: slash.top, left: slash.left }}
        >
            {items.map((item, i) => {
                const Icon = item.icon;

                return (
                    <li
                        key={item.label}
                        role="option"
                        aria-selected={i === active}
                        className="flex cursor-pointer items-center gap-2 rounded-ctl px-2 py-1 text-sm aria-selected:bg-sel"
                        onMouseDown={(e) => e.preventDefault()}
                        onMouseEnter={() => setIndex(i)}
                        onClick={() => choose(item)}
                    >
                        <Icon
                            className="size-4 shrink-0 text-ink-3"
                            aria-hidden
                        />
                        <span className="flex-1">{item.label}</span>
                        {item.hint && (
                            <kbd className="font-mono text-xs text-ink-3">
                                {item.hint}
                            </kbd>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

export default function PageEditor({
    ref,
    pageId,
    initialMarkdown,
    readOnly,
    onChange,
    notations,
    activeNotationId,
    onSelectNotation,
    onAddNote,
    composing,
}: Props) {
    const bodyRef = useRef<HTMLDivElement>(null);
    const fileInput = useRef<HTMLInputElement>(null);
    const slashKeys = useRef<((event: KeyboardEvent) => boolean) | null>(null);
    const pageLinkKeys = useRef<((event: KeyboardEvent) => boolean) | null>(
        null,
    );
    const lastMarkdown = useRef<string | null>(null);
    const addNoteRef = useRef(onAddNote);
    addNoteRef.current = onAddNote;
    const [linkOpen, setLinkOpen] = useState(false);
    const attachments = usePage<{ attachments: AttachmentConfig }>().props
        .attachments;
    const { upload, uploading } = useAttachmentUpload(pageId);
    const uploadRef = useRef(upload);
    uploadRef.current = upload;
    const [uploadError, setUploadError] = useState<string | null>(null);

    const editor = useEditor({
        // Underline has no markdown form, so it is left out.
        extensions: [
            StarterKit.configure({
                underline: false,
                blockquote: false,
                codeBlock: false,
                link: { openOnClick: false, autolink: true },
            }),
            AlertBlockquote,
            HighlightedCodeBlock,
            TaskList,
            TaskItem.configure({ nested: true }),
            TableKit.configure({ table: { resizable: false } }),
            Markdown,
            LayoutImage,
            AttachmentUploads.configure({
                upload: (file) => {
                    setUploadError(null);

                    return uploadRef.current(file);
                },
                onError: setUploadError,
            }),
            AttachmentCard.configure({
                videoExtensions: attachments.video_extensions,
                audioExtensions: attachments.audio_extensions,
            }),
            NotationHighlights.configure({ onSelect: onSelectNotation }),
        ],
        content: initialMarkdown,
        contentType: "markdown",
        immediatelyRender: false,
        editorProps: {
            attributes: {
                "aria-label": "Page body",
                "aria-multiline": "true",
                role: "textbox",
            },
            handleKeyDown: (view, event) => {
                if (
                    slashKeys.current?.(event) ||
                    pageLinkKeys.current?.(event)
                ) {
                    event.preventDefault();

                    return true;
                }

                const mod = event.metaKey || event.ctrlKey;

                if (mod && event.altKey && event.code === "KeyM") {
                    const { from, to, empty } = view.state.selection;

                    if (!empty) {
                        event.preventDefault();
                        addNoteRef.current({
                            from,
                            to,
                            quote: view.state.doc.textBetween(from, to, " "),
                        });
                    }

                    return true;
                }

                if (
                    mod &&
                    !event.altKey &&
                    event.key.toLowerCase() === "k" &&
                    view.editable &&
                    !view.state.selection.empty
                ) {
                    event.preventDefault();
                    setLinkOpen(true);

                    return true;
                }

                return false;
            },
            handleClick: (view, _pos, event) => {
                const link = (event.target as HTMLElement).closest<HTMLAnchorElement>(
                    'a[href^="/pages/"]',
                );

                // Editing keeps the plain click for the caret; Cmd/Ctrl-click follows the link.
                if (!link || (view.editable && !(event.metaKey || event.ctrlKey))) {
                    return false;
                }

                const href = link.getAttribute("href") as string;

                if (event.metaKey || event.ctrlKey) {
                    window.open(href, "_blank", "noopener");
                } else {
                    router.visit(href);
                }

                return true;
            },
        },
        // Report only real content changes, not the normalized body on load or decoration-only updates.
        // Trailing whitespace is ignored: the editor appends an empty paragraph after load.
        onCreate: ({ editor: e }) => {
            lastMarkdown.current = e.getMarkdown().trimEnd();
        },
        onUpdate: ({ editor: e }) => {
            const markdown = e.getMarkdown();

            if (markdown.trimEnd() !== lastMarkdown.current) {
                lastMarkdown.current = markdown.trimEnd();
                onChange(markdown);
            }
        },
        onSelectionUpdate: () => setLinkOpen(false),
    });

    useImperativeHandle(
        ref,
        () => ({
            focusStart: () => editor?.commands.focus("start"),
            focusRange: (from, to) => {
                if (!editor) {
                    return;
                }

                const size = editor.state.doc.content.size;
                editor
                    .chain()
                    .focus()
                    .setTextSelection({
                        from: Math.min(from, size),
                        to: Math.min(to, size),
                    })
                    .run();
            },
        }),
        [editor],
    );

    const isEmpty = useEditorState({
        editor,
        selector: ({ editor: e }) => e?.isEmpty ?? false,
    });

    useEffect(() => {
        editor?.setEditable(!readOnly, false);
    }, [editor, readOnly]);

    useEffect(() => {
        if (editor) {
            refreshNotations(editor, notations, activeNotationId);
        }
    }, [editor, notations, activeNotationId]);

    if (!editor) {
        return null;
    }

    return (
        <div ref={bodyRef} className="relative">
            {(uploading || uploadError) && (
                <p
                    role={uploadError ? "alert" : "status"}
                    className={`flex items-center gap-1 pb-1 text-xs ${uploadError ? "text-bad-ink" : "text-ink-3"}`}
                >
                    {uploadError ?? "Uploading…"}
                    {uploadError && (
                        <Button
                            type="button"
                            aria-label="Dismiss"
                            variant="quiet"
                            size="icon-sm"
                            onClick={() => setUploadError(null)}
                        >
                            <X className="size-3.5" aria-hidden />
                        </Button>
                    )}
                </p>
            )}
            {isEmpty && !readOnly && (
                <p
                    aria-hidden
                    className="pointer-events-none absolute top-2 left-0 text-[1rem] text-ink-3 select-none"
                >
                    Start writing, or type / for headings, lists and files
                </p>
            )}
            <EditorContent editor={editor} className="page-prose py-2" />
            <SelectionMenu
                editor={editor}
                container={bodyRef}
                readOnly={readOnly}
                hidden={composing}
                linkOpen={linkOpen}
                setLinkOpen={setLinkOpen}
                onAddNote={onAddNote}
            />
            {!readOnly && (
                <>
                    <SlashMenu
                        editor={editor}
                        container={bodyRef}
                        keyHandler={slashKeys}
                        onAttach={() => fileInput.current?.click()}
                    />
                    <PageLinkMenu
                        editor={editor}
                        container={bodyRef}
                        keyHandler={pageLinkKeys}
                        pageId={pageId}
                    />
                </>
            )}
            <input
                ref={fileInput}
                type="file"
                multiple
                hidden
                accept={attachments.accept}
                onChange={(e) => {
                    insertFiles(editor, Array.from(e.target.files ?? []));
                    e.target.value = "";
                }}
            />
        </div>
    );
}
