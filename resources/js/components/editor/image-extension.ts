import Image from '@tiptap/extension-image';

export const IMAGE_ALIGNS = ['left', 'center', 'right'] as const;
export const IMAGE_SIZES = [25, 50, 75, 100] as const;

export type ImageAlign = (typeof IMAGE_ALIGNS)[number];
export type ImageSize = (typeof IMAGE_SIZES)[number];

export const DEFAULT_ALIGN: ImageAlign = 'center';
export const DEFAULT_SIZE: ImageSize = 100;

function parseAlign(value: unknown): ImageAlign {
    return IMAGE_ALIGNS.find((align) => align === value) ?? DEFAULT_ALIGN;
}

function parseSize(value: unknown): ImageSize {
    return IMAGE_SIZES.find((size) => size === Number(value)) ?? DEFAULT_SIZE;
}

/** Split `/attachments/x#a=right&w=50` into a clean src and its layout attrs. */
function splitSrc(href: string): {
    src: string;
    align: ImageAlign;
    size: ImageSize;
} {
    const hash = href.indexOf('#');

    if (hash === -1) {
        return { src: href, align: DEFAULT_ALIGN, size: DEFAULT_SIZE };
    }

    const params = new URLSearchParams(href.slice(hash + 1));

    return {
        src: href.slice(0, hash),
        align: parseAlign(params.get('a')),
        size: parseSize(params.get('w')),
    };
}

/**
 * Image with alignment and width. Layout is persisted in the markdown as a
 * src fragment (`#a=right&w=50`), which is never sent to the server.
 */
export const LayoutImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            align: {
                default: DEFAULT_ALIGN,
                parseHTML: (el) => parseAlign(el.getAttribute('data-align')),
                renderHTML: (attrs) => ({ 'data-align': attrs.align }),
            },
            size: {
                default: DEFAULT_SIZE,
                parseHTML: (el) => parseSize(el.getAttribute('data-size')),
                renderHTML: (attrs) => ({ 'data-size': attrs.size }),
            },
        };
    },

    parseMarkdown: (token, helpers) =>
        helpers.createNode('image', {
            ...splitSrc(token.href ?? ''),
            title: token.title,
            alt: token.text,
        }),

    renderMarkdown: (node) => {
        const {
            src = '',
            alt = '',
            title = '',
            align,
            size,
        } = node.attrs ?? {};
        const params = new URLSearchParams();

        if (align !== DEFAULT_ALIGN) {
            params.set('a', align);
        }

        if (size !== DEFAULT_SIZE) {
            params.set('w', String(size));
        }

        const query = params.toString();
        const url = query ? `${src}#${query}` : src;

        return title ? `![${alt}](${url} "${title}")` : `![${alt}](${url})`;
    },
});
