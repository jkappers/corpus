import { Plugin, PluginKey } from '@tiptap/pm/state';
import { Extension } from '@tiptap/react';
import type { Editor, JSONContent } from '@tiptap/react';
import type { Attachment, AttachmentKind } from '@/types/attachment';

type Options = {
    upload: (file: File) => Promise<Attachment>;
    onError: (message: string) => void;
};

type AttachmentStorage = {
    insertFiles: (files: File[], pos?: number) => void;
};

const key = new PluginKey('attachments');

/**
 * How each attachment kind lands in the page. Images are inline nodes;
 * other files become an attachment card.
 */
const NODE_BY_KIND: Record<AttachmentKind, (a: Attachment) => JSONContent> = {
    image: (a) => ({ type: 'image', attrs: { src: a.url, alt: a.name } }),
    file: (a) => ({
        type: 'attachment',
        attrs: { url: a.url, name: a.name },
    }),
};

export function insertFiles(editor: Editor, files: File[], pos?: number) {
    const storage = editor.storage as unknown as {
        attachments: AttachmentStorage;
    };

    storage.attachments.insertFiles(files, pos);
}

export const AttachmentUploads = Extension.create<Options, AttachmentStorage>({
    name: 'attachments',

    addOptions() {
        return {
            upload: () => Promise.reject(new Error('Uploads are unavailable.')),
            onError: () => {},
        };
    },

    addStorage() {
        return { insertFiles: () => {} };
    },

    onCreate() {
        const { editor, options } = this;

        this.storage.insertFiles = (files, pos) => {
            let at = pos ?? editor.state.selection.to;

            // Uploads are sequential; each insert lands after the previous one.
            void files.reduce(
                (previous, file) =>
                    previous.then(async () => {
                        try {
                            const attachment = await options.upload(file);

                            editor
                                .chain()
                                .insertContentAt(
                                    Math.min(at, editor.state.doc.content.size),
                                    NODE_BY_KIND[attachment.kind](attachment),
                                )
                                .run();
                            at = editor.state.selection.to;
                        } catch (error) {
                            options.onError(
                                error instanceof Error
                                    ? error.message
                                    : 'Upload failed.',
                            );
                        }
                    }),
                Promise.resolve(),
            );
        };
    },

    addProseMirrorPlugins() {
        const { editor, options } = this;

        return [
            new Plugin({
                key,
                props: {
                    handlePaste: (_view, event) => {
                        const files = Array.from(
                            event.clipboardData?.files ?? [],
                        );

                        if (files.length === 0 || !editor.isEditable) {
                            return false;
                        }

                        insertFiles(editor, files);

                        return true;
                    },
                    handleDrop: (view, event) => {
                        const files = Array.from(
                            event.dataTransfer?.files ?? [],
                        );

                        if (files.length === 0 || !editor.isEditable) {
                            return false;
                        }

                        const target = view.posAtCoords({
                            left: event.clientX,
                            top: event.clientY,
                        });

                        event.preventDefault();
                        insertFiles(editor, files, target?.pos);

                        return true;
                    },
                },
            }),
        ];
    },
});
