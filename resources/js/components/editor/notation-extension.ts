import type { Node as ProseNode } from '@tiptap/pm/model';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import { Decoration, DecorationSet } from '@tiptap/pm/view';
import { Extension } from '@tiptap/react';
import type { Editor } from '@tiptap/react';

type Anchor = { id: number; quote: string; from: number; to: number };

type Options = { onSelect: (id: number) => void };

type NotationStorage = { notations: Anchor[]; activeId: number | null };

const key = new PluginKey('notations');

/**
 * Notations are stored outside the markdown body, so their ranges are
 * re-anchored on every render: trust the saved range while its text still
 * matches the quote, otherwise look for the quote in a single text block.
 */
export function resolveRange(
    doc: ProseNode,
    { quote, from, to }: Anchor,
): { from: number; to: number } | null {
    if (to <= doc.content.size && doc.textBetween(from, to, ' ') === quote) {
        return { from, to };
    }

    let found: { from: number; to: number } | null = null;

    doc.descendants((node, pos) => {
        if (found || !node.isTextblock) {
            return !found;
        }

        const index = node.textContent.indexOf(quote);

        if (index !== -1) {
            found = {
                from: pos + 1 + index,
                to: pos + 1 + index + quote.length,
            };
        }

        return false;
    });

    return found;
}

export const NotationHighlights = Extension.create<Options, NotationStorage>({
    name: 'notations',

    addOptions() {
        return { onSelect: () => {} };
    },

    addStorage() {
        return { notations: [], activeId: null };
    },

    addProseMirrorPlugins() {
        const { storage, options } = this;

        return [
            new Plugin({
                key,
                props: {
                    decorations: ({ doc }) => {
                        const decorations: Decoration[] = [];

                        for (const notation of storage.notations) {
                            const range = resolveRange(doc, notation);

                            if (range && range.from < range.to) {
                                decorations.push(
                                    Decoration.inline(range.from, range.to, {
                                        class:
                                            notation.id === storage.activeId
                                                ? 'notation-highlight notation-highlight-active'
                                                : 'notation-highlight',
                                        'data-notation-id': String(notation.id),
                                    }),
                                );
                            }
                        }

                        return DecorationSet.create(doc, decorations);
                    },
                    handleClick: (_view, _pos, event) => {
                        const id = (event.target as HTMLElement)
                            .closest?.('[data-notation-id]')
                            ?.getAttribute('data-notation-id');

                        if (id) {
                            options.onSelect(Number(id));
                        }

                        return false;
                    },
                },
            }),
        ];
    },
});

export function refreshNotations(
    editor: Editor,
    notations: Anchor[],
    activeId: number | null,
) {
    const storage = (
        editor.storage as unknown as { notations: NotationStorage }
    ).notations;

    storage.notations = notations;
    storage.activeId = activeId;
    editor.view.dispatch(editor.state.tr.setMeta(key, true));
}
