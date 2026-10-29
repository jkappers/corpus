import { usePage } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';
import type { Space } from '@/types';

const LAST_SPACE_KEY = 'corpus.lastSpace';

/**
 * What a space is called in the UI. Your own personal space is always "Personal";
 * an admin sees other people's as "<owner> (personal)".
 */
export function spaceLabel(
    space: Pick<Space, 'name' | 'is_mine' | 'is_personal' | 'owner_name'>,
): string {
    if (space.is_mine) {
        return 'Personal';
    }

    if (space.is_personal && space.owner_name) {
        return `${space.owner_name} (personal)`;
    }

    return space.name;
}

/**
 * Labels a space known only by id and server name (listing rows, search results),
 * using the shared space list so personal spaces read the same everywhere.
 */
export function useSpaceLabel(): (space: {
    id: number;
    name: string;
}) => string {
    const { spaces } = usePage<{ spaces: Space[] }>().props;

    return (space) => {
        const known = spaces.find((item) => item.id === space.id);

        return known ? spaceLabel(known) : space.name;
    };
}

/** Spaces new content can go into: shared spaces you can edit, plus your own private one. */
export function creatableSpaces(spaces: Space[]): Space[] {
    return spaces.filter(
        (item) => item.can_edit && (!item.is_personal || item.is_mine),
    );
}

const listeners = new Set<() => void>();

function readLastSpace(): number | null {
    try {
        const value = window.localStorage.getItem(LAST_SPACE_KEY);

        return value ? Number(value) : null;
    } catch {
        return null;
    }
}

export function rememberSpace(id: number): void {
    try {
        window.localStorage.setItem(LAST_SPACE_KEY, String(id));
    } catch {
        // The default just won't carry over.
    }

    listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/**
 * Where "New" puts things: the space you're in, else the last one you created in,
 * else Personal. Every entry point shows this so nothing lands somewhere unseen.
 */
export function useCreateTarget(): {
    target: Space | undefined;
    destinations: Space[];
} {
    const { spaces, space, page } = usePage<{
        spaces: Space[];
        space?: { id: number } | null;
        page?: { space_id?: number } | null;
    }>().props;
    const lastSpaceId = useSyncExternalStore(
        subscribe,
        readLastSpace,
        () => null,
    );
    const destinations = creatableSpaces(spaces);
    const byId = (id: number | null | undefined) =>
        destinations.find((item) => item.id === id);

    return {
        target:
            byId(space?.id ?? page?.space_id) ??
            byId(lastSpaceId) ??
            destinations.find((item) => item.is_mine) ??
            destinations[0],
        destinations,
    };
}
