import { useCallback, useEffect, useRef, useState } from 'react';
import { ApiError, api } from '@/lib/api';

export type SaveStatus =
    | 'idle'
    | 'pending'
    | 'saving'
    | 'saved'
    | 'error'
    | 'conflict';

type Payload = { title?: string; body?: string; board?: object };

const DEBOUNCE_MS = 800;

/**
 * Debounced PATCH of a page or board to its update URL. Changes made while a request is in flight
 * are merged and sent once it settles. Each save carries `base_updated_at`, so an edit
 * made elsewhere (another tab, an integration) is reported as a conflict instead of overwritten.
 */
export function useAutosave(url: string, updatedAt: string) {
    const baseUpdatedAt = useRef(updatedAt);
    const [status, setStatus] = useState<SaveStatus>('idle');
    const [savedAt, setSavedAt] = useState<Date | null>(null);

    const pending = useRef<Payload>({});
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const inFlight = useRef(false);
    const conflicted = useRef(false);

    const send = useCallback(async () => {
        if (inFlight.current || Object.keys(pending.current).length === 0) {
            return;
        }

        const payload = pending.current;
        pending.current = {};
        inFlight.current = true;
        setStatus('saving');

        let failed = false;

        try {
            const response = await api<{ data: { updated_at: string } }>(
                'patch',
                url,
                { ...payload, base_updated_at: baseUpdatedAt.current },
            );

            baseUpdatedAt.current = response.data.updated_at;
            setStatus('saved');
            setSavedAt(new Date());
        } catch (error) {
            failed = true;
            pending.current = { ...payload, ...pending.current };
            conflicted.current =
                error instanceof ApiError && error.status === 409;
            setStatus(conflicted.current ? 'conflict' : 'error');
        } finally {
            inFlight.current = false;
        }

        // Changes made mid-request go out right after; a failure waits for the next edit.
        if (!failed) {
            void send();
        }
    }, [url]);

    const flush = useCallback(() => {
        if (conflicted.current) {
            return;
        }

        if (timer.current) {
            clearTimeout(timer.current);
            timer.current = null;
        }

        void send();
    }, [send]);

    const save = useCallback(
        (changes: Payload) => {
            pending.current = { ...pending.current, ...changes };

            // Show the edit as unsaved right away, not only once the request starts.
            if (!inFlight.current && !conflicted.current) {
                setStatus((current) =>
                    current === 'error' ? current : 'pending',
                );
            }

            if (timer.current) {
                clearTimeout(timer.current);
            }

            timer.current = setTimeout(flush, DEBOUNCE_MS);
        },
        [flush],
    );

    useEffect(() => {
        const onUnload = () => flush();

        window.addEventListener('beforeunload', onUnload);

        return () => {
            window.removeEventListener('beforeunload', onUnload);
            flush();
        };
    }, [flush]);

    /** Adopt a newer version written by this tab outside autosave (a move), so it isn't taken for a conflict. */
    const rebase = useCallback((nextUpdatedAt: string) => {
        baseUpdatedAt.current = nextUpdatedAt;
    }, []);

    return { save, flush, rebase, status, savedAt };
}
