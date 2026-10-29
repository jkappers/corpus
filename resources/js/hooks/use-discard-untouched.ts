import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef } from 'react';
import { toast } from 'sonner';
import { takeFresh } from '@/hooks/use-create-page';
import { api, refreshPage } from '@/lib/api';
import { routesFor } from '@/lib/page-routes';
import type { PageType } from '@/types';

/**
 * Moves a just-created page or board to Trash if the user navigates away without
 * changing anything, so "New" by accident doesn't leave Untitled clutter behind.
 * Call the returned function on the first edit.
 */
export function useDiscardUntouched(type: PageType, id: number): () => void {
    const fresh = useRef<boolean | null>(null);
    const touched = useRef(false);

    useEffect(() => {
        fresh.current ??= takeFresh(type, id);

        const path = window.location.pathname;

        return router.on('before', (event) => {
            const { visit } = event.detail;

            if (
                !fresh.current ||
                touched.current ||
                visit.prefetch ||
                visit.url.pathname === path
            ) {
                return;
            }

            fresh.current = false;

            // Wait until the next screen is showing, so the refresh reloads it rather than this item.
            const stop = router.on('navigate', () => {
                stop();
                api('delete', routesFor(type).api.destroy(id).url)
                    .then(() => refreshPage())
                    .then(() => toast(`Discarded the empty ${type}`))
                    .catch(() => {});
            });
        });
    }, [type, id]);

    return useCallback(() => {
        touched.current = true;
    }, []);
}
