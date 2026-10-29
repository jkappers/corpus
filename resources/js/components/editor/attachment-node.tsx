import { Node, ReactNodeViewRenderer, NodeViewWrapper } from '@tiptap/react';
import type { NodeViewProps } from '@tiptap/react';
import { ExternalLink, Paperclip, X } from 'lucide-react';
import { toast } from 'sonner';

type PlayerOptions = {
    videoExtensions: string[];
    audioExtensions: string[];
};

const ATTACHMENT_PREFIX = '/attachments/';

export function playerTag(
    name: string,
    options: PlayerOptions,
): 'video' | 'audio' | null {
    const extension = name.split('.').pop()?.toLowerCase() ?? '';

    if (options.videoExtensions.includes(extension)) {
        return 'video';
    }

    return options.audioExtensions.includes(extension) ? 'audio' : null;
}

function AttachmentView({
    node,
    editor,
    getPos,
    selected,
    extension,
}: NodeViewProps) {
    const { url, name } = node.attrs as { url: string; name: string };
    const tag = playerTag(name, extension.options as PlayerOptions);

    const remove = () => {
        const pos = getPos();

        if (pos === undefined) {
            return;
        }

        editor
            .chain()
            .focus()
            .deleteRange({ from: pos, to: pos + node.nodeSize })
            .run();
        toast('Attachment removed from page', {
            action: { label: 'Undo', onClick: () => editor.commands.undo() },
        });
    };

    return (
        <NodeViewWrapper
            className="attachment-card"
            data-selected={selected || undefined}
            data-drag-handle
        >
            <div className="attachment-card__header">
                <Paperclip aria-hidden className="size-3.5 flex-none" />
                <span className="attachment-card__name" title={name}>
                    {name}
                </span>
                <a
                    className="attachment-card__action"
                    href={url}
                    target="_blank"
                    rel="noreferrer"
                    aria-label={`Open ${name}`}
                    contentEditable={false}
                >
                    <ExternalLink aria-hidden className="size-3.5" />
                </a>
                {editor.isEditable && (
                    <button
                        type="button"
                        className="attachment-card__action"
                        aria-label={`Remove ${name} from page`}
                        onClick={remove}
                    >
                        <X aria-hidden className="size-3.5" />
                    </button>
                )}
            </div>
            {tag === 'video' && (
                <video
                    className="attachment-card__media"
                    controls
                    preload="metadata"
                    src={url}
                />
            )}
            {tag === 'audio' && (
                <audio
                    className="attachment-card__media"
                    controls
                    preload="metadata"
                    src={url}
                />
            )}
        </NodeViewWrapper>
    );
}

/**
 * A file attached to a page, rendered as one card with its player.
 * The markdown stays a plain link, so existing pages hydrate as cards.
 */
export const AttachmentCard = Node.create<PlayerOptions>({
    name: 'attachment',
    group: 'block',
    atom: true,
    draggable: true,
    selectable: true,
    // Must outrank Paragraph (1000), which claims every paragraph token.
    priority: 1100,
    markdownTokenName: 'paragraph',

    addOptions() {
        return { videoExtensions: [], audioExtensions: [] };
    },

    addAttributes() {
        // Required attrs keep ProseMirror from using this node to fill
        // empty block slots (e.g. an empty document).
        return {
            url: {
                isRequired: true,
                parseHTML: (el) => el.getAttribute('data-url') ?? '',
            },
            name: {
                isRequired: true,
                parseHTML: (el) => el.textContent ?? '',
            },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-attachment]' }];
    },

    renderHTML({ node }) {
        return [
            'div',
            { 'data-attachment': '', 'data-url': node.attrs.url },
            node.attrs.name,
        ];
    },

    parseMarkdown: (token, helpers) => {
        const [only, ...rest] = token.tokens ?? [];

        if (
            rest.length > 0 ||
            only?.type !== 'link' ||
            !only.href?.startsWith(ATTACHMENT_PREFIX)
        ) {
            return [];
        }

        return helpers.createNode('attachment', {
            url: only.href,
            name: only.text ?? '',
        });
    },

    renderMarkdown: (node) =>
        `[${node.attrs?.name ?? ''}](${node.attrs?.url ?? ''})`,

    addNodeView() {
        return ReactNodeViewRenderer(AttachmentView);
    },
});
