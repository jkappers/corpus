import type { MarkdownToken } from "@tiptap/core";
import Blockquote from "@tiptap/extension-blockquote";

export const ALERT_TYPES = [
    "note",
    "tip",
    "important",
    "warning",
    "caution",
] as const;

export type AlertType = (typeof ALERT_TYPES)[number];

const MARKER = new RegExp(`^\\[!(${ALERT_TYPES.join("|")})\\][ \\t]*\\n?`, "i");

type Token = MarkdownToken;

function parseAlertType(value: unknown): AlertType | null {
    return ALERT_TYPES.find((type) => type === value) ?? null;
}

/**
 * Split a leading `[!NOTE]` marker off a blockquote's tokens.
 * Returns the alert type and the tokens without the marker.
 */
function extractAlert(tokens: Token[]): {
    alertType: AlertType | null;
    tokens: Token[];
} {
    const [first, ...rest] = tokens;
    const inline = first?.type === "paragraph" ? first.tokens : undefined;
    const lead = inline?.[0];
    const match = lead?.type === "text" ? MARKER.exec(lead.raw ?? "") : null;

    if (!first || !inline || !lead || !match) {
        return { alertType: null, tokens };
    }

    const remainder = (lead.raw ?? "").slice(match[0].length);
    const inlineRest = remainder
        ? [{ ...lead, raw: remainder, text: remainder }, ...inline.slice(1)]
        : inline.slice(1);

    return {
        alertType: parseAlertType(match[1].toLowerCase()),
        tokens: inlineRest.length
            ? [{ ...first, tokens: inlineRest }, ...rest]
            : rest,
    };
}

/** GitHub-style alerts (`> [!NOTE]`) layered on the standard blockquote. */
export const AlertBlockquote = Blockquote.extend({
    addAttributes() {
        return {
            alertType: {
                default: null,
                parseHTML: (element) =>
                    parseAlertType(element.getAttribute("data-alert")),
                renderHTML: (attributes) =>
                    attributes.alertType
                        ? { "data-alert": attributes.alertType }
                        : {},
            },
        };
    },

    parseMarkdown: (token, helpers) => {
        const { alertType, tokens } = extractAlert(token.tokens ?? []);
        const children = helpers.parseBlockChildren
            ? helpers.parseBlockChildren(tokens)
            : helpers.parseChildren(tokens);

        return helpers.createNode(
            "blockquote",
            { alertType },
            children.length
                ? children
                : [helpers.createNode("paragraph", undefined, [])],
        );
    },

    renderMarkdown(node, helpers, context) {
        const body = this.parent?.(node, helpers, context) ?? "";
        const alertType = parseAlertType(node.attrs?.alertType);

        return alertType ? `> [!${alertType.toUpperCase()}]\n${body}` : body;
    },
});
