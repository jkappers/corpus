import { useHttp } from '@inertiajs/react';
import { useCallback, useRef } from 'react';
import { store } from '@/routes/api/v1/pages/attachments';
import type { Attachment } from '@/types/attachment';

type Payload = { file: File | null };

const FALLBACK_ERROR = 'Upload failed.';

/**
 * Uploads files to a page one at a time (a single useHttp instance
 * can't track concurrent requests) and resolves with the stored attachment.
 */
export function useAttachmentUpload(pageId: number) {
    const http = useHttp<Payload, { data: Attachment }>({ file: null });
    const httpRef = useRef(http);
    httpRef.current = http;
    const queue = useRef<Promise<unknown>>(Promise.resolve());

    const upload = useCallback(
        (file: File): Promise<Attachment> => {
            const run = async () => {
                try {
                    httpRef.current.transform(() => ({ file }));

                    const response = await httpRef.current.post(
                        store(pageId).url,
                    );

                    return response.data;
                } catch (error) {
                    const message = httpRef.current.errors.file;

                    throw new Error(
                        message ??
                            (error instanceof Error
                                ? error.message
                                : FALLBACK_ERROR),
                    );
                }
            };
            const result = queue.current.then(run, run);
            queue.current = result.catch(() => {});

            return result;
        },
        [pageId],
    );

    return { upload, uploading: http.processing };
}
