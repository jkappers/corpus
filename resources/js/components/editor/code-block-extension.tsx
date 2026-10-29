import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import CodeBlockLowlight from "@tiptap/extension-code-block-lowlight";
import type { NodeViewProps } from "@tiptap/react";
import {
    NodeViewContent,
    NodeViewWrapper,
    ReactNodeViewRenderer,
} from "@tiptap/react";
import bash from "highlight.js/lib/languages/bash";
import css from "highlight.js/lib/languages/css";
import diff from "highlight.js/lib/languages/diff";
import go from "highlight.js/lib/languages/go";
import javascript from "highlight.js/lib/languages/javascript";
import json from "highlight.js/lib/languages/json";
import markdown from "highlight.js/lib/languages/markdown";
import php from "highlight.js/lib/languages/php";
import python from "highlight.js/lib/languages/python";
import rust from "highlight.js/lib/languages/rust";
import sql from "highlight.js/lib/languages/sql";
import typescript from "highlight.js/lib/languages/typescript";
import xml from "highlight.js/lib/languages/xml";
import yaml from "highlight.js/lib/languages/yaml";
import { Check, ChevronDown, Copy } from "lucide-react";
import { createLowlight } from "lowlight";
import { useEffect, useState } from "react";

const PLAIN = "plain";

const LANGUAGES = [
    { value: "bash", label: "Bash", aliases: ["sh", "shell", "zsh"], definition: bash },
    { value: "css", label: "CSS", aliases: [], definition: css },
    { value: "diff", label: "Diff", aliases: ["patch"], definition: diff },
    { value: "go", label: "Go", aliases: ["golang"], definition: go },
    { value: "xml", label: "HTML / XML", aliases: ["html", "svg", "xhtml"], definition: xml },
    { value: "javascript", label: "JavaScript", aliases: ["js", "jsx", "mjs"], definition: javascript },
    { value: "json", label: "JSON", aliases: [], definition: json },
    { value: "markdown", label: "Markdown", aliases: ["md"], definition: markdown },
    { value: "php", label: "PHP", aliases: [], definition: php },
    { value: "python", label: "Python", aliases: ["py"], definition: python },
    { value: "rust", label: "Rust", aliases: ["rs"], definition: rust },
    { value: "sql", label: "SQL", aliases: [], definition: sql },
    { value: "typescript", label: "TypeScript", aliases: ["ts", "tsx"], definition: typescript },
    { value: "yaml", label: "YAML", aliases: ["yml"], definition: yaml },
];

const lowlight = createLowlight();

for (const { value, aliases, definition } of LANGUAGES) {
    lowlight.register({ [value]: definition });
    lowlight.registerAlias({ [value]: aliases });
}

/** Maps a fence info string (`ts`, `sh`, `html`) to a picker value. */
function resolveLanguage(language: string | null): string {
    if (!language) {
        return PLAIN;
    }

    return (
        LANGUAGES.find(
            ({ value, aliases }) =>
                value === language ||
                aliases.includes(language),
        )?.value ?? PLAIN
    );
}

const OPTIONS = [
    { value: PLAIN, label: "Plain text" },
    ...LANGUAGES.map(({ value, label }) => ({ value, label })),
];

function LanguagePicker({
    value,
    onChange,
}: {
    value: string;
    onChange: (value: string) => void;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState("");

    const needle = query.trim().toLowerCase();
    const matches = OPTIONS.filter(({ label, value: optionValue }) => {
        const aliases =
            LANGUAGES.find((language) => language.value === optionValue)
                ?.aliases ?? [];

        return (
            !needle ||
            label.toLowerCase().includes(needle) ||
            optionValue.includes(needle) ||
            aliases.some((alias) => alias.startsWith(needle))
        );
    });

    const choose = (next: string) => {
        onChange(next);
        setOpen(false);
    };

    return (
        <DropdownMenu
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                setQuery("");
            }}
        >
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="quiet"
                    className="h-6 gap-1 px-2 text-xs text-ink-2"
                    aria-label="Code language"
                >
                    {OPTIONS.find((option) => option.value === value)?.label}
                    <ChevronDown className="size-3" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                <Input
                    autoFocus
                    value={query}
                    placeholder="Search languages"
                    aria-label="Search languages"
                    className="mb-1 h-7 text-xs"
                    onChange={(event) => setQuery(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === "Enter" && matches[0]) {
                            event.preventDefault();
                            choose(matches[0].value);

                            return;
                        }

                        if (
                            event.key !== "ArrowDown" &&
                            event.key !== "ArrowUp" &&
                            event.key !== "Escape"
                        ) {
                            event.stopPropagation();
                        }
                    }}
                />
                {matches.map((option) => (
                    <DropdownMenuItem
                        key={option.value}
                        onSelect={() => choose(option.value)}
                    >
                        {option.label}
                    </DropdownMenuItem>
                ))}
                {matches.length === 0 && (
                    <div className="px-2 py-1.5 text-xs text-ink-3">
                        No match
                    </div>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function CodeBlockView({ node, editor, updateAttributes }: NodeViewProps) {
    const current = resolveLanguage(node.attrs.language);
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) {
            return;
        }

        const timeout = setTimeout(() => setCopied(false), 1500);

        return () => clearTimeout(timeout);
    }, [copied]);

    const copy = async () => {
        await navigator.clipboard.writeText(node.textContent);
        setCopied(true);
    };

    return (
        <NodeViewWrapper className="code-block">
            <div className="code-block-actions" contentEditable={false}>
                <Button
                    type="button"
                    variant="quiet"
                    size="icon-sm"
                    className="size-6 text-ink-2"
                    aria-label={copied ? "Copied" : "Copy code"}
                    title={copied ? "Copied" : "Copy"}
                    onClick={copy}
                >
                    {copied ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
                </Button>
                {editor.isEditable && (
                    <LanguagePicker
                        value={current}
                        onChange={(value) =>
                            updateAttributes({
                                language: value === PLAIN ? null : value,
                            })
                        }
                    />
                )}
            </div>
            <pre>
                <NodeViewContent<"code"> as="code" />
            </pre>
        </NodeViewWrapper>
    );
}

export const HighlightedCodeBlock = CodeBlockLowlight.extend({
    addNodeView() {
        return ReactNodeViewRenderer(CodeBlockView);
    },
}).configure({
    lowlight,
    defaultLanguage: null,
});
