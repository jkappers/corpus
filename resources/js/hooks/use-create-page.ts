import { router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { routesFor } from '@/lib/page-routes';
import { rememberSpace, spaceLabel } from '@/lib/spaces';
import type { PageType, Space } from '@/types';

const fresh = new Set<string>();
const freshKey = (type: PageType, id: number) => `${type}:${id}`;

/**
 * True once for an item this tab just created, so an editor can discard it if the
 * user leaves without touching it.
 */
export function takeFresh(type: PageType, id: number): boolean {
    return fresh.delete(freshKey(type, id));
}

/**
 * Creates a page or board in the given space and opens it. Shared by every "New"
 * entry point so they report where it went, failure, retry and undo the same way.
 */
export function useCreatePage() {
    const [pending, setPending] = useState<PageType | null>(null);
    const busy = useRef(false);
    const { spaces } = usePage<{ spaces: Space[] }>().props;

    const create = async (
        type: PageType,
        spaceId?: number | null,
    ): Promise<void> => {
        if (busy.current) {
            return;
        }

        busy.current = true;
        setPending(type);

        const routes = routesFor(type);
        const from = window.location.href;

        try {
            const { data } = await api<{
                data: { id: number; space_id: number };
            }>('post', routes.api.store().url, {
                space_id: spaceId ?? undefined,
            });

            fresh.add(freshKey(type, data.id));
            rememberSpace(data.space_id);

            router.visit(routes.show(data.id).url, {
                onFinish: () => {
                    busy.current = false;
                    setPending(null);
                },
            });

            const space = spaces.find((item) => item.id === data.space_id);

            toast(
                space
                    ? `New ${type} in ${spaceLabel(space)}`
                    : `New ${type} created`,
                {
                    action: {
                        label: 'Undo',
                        onClick: async () => {
                            takeFresh(type, data.id);
                            await api(
                                'delete',
                                routes.api.destroy(data.id).url,
                            );
                            router.visit(from);
                        },
                    },
                },
            );
        } catch {
            busy.current = false;
            setPending(null);
            toast.error(`Couldn't create the ${type}.`, {
                action: {
                    label: 'Retry',
                    onClick: () => create(type, spaceId),
                },
            });
        }
    };

    return { create, pending };
}
