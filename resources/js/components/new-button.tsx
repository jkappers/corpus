import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useCreatePage } from '@/hooks/use-create-page';
import { isTyping, shiftKey } from '@/lib/shortcuts';
import { useCreateTarget } from '@/lib/spaces';
import type { PageType } from '@/types';
import { FileText, LayoutDashboard } from 'lucide-react';
import { useEffect, useEffectEvent } from 'react';

export const typeIcons = {
    page: FileText,
    board: LayoutDashboard,
} as const;

const shortcutFor = (type: PageType): string =>
    type === 'page' ? 'N' : `${shiftKey}N`;

/**
 * N creates a page and Shift+N a board, in the same space the New button shows.
 * Mounted once, by the app layout.
 */
export function useNewShortcuts(): void {
    const { create } = useCreatePage();
    const { target } = useCreateTarget();

    const onKeyDown = useEffectEvent((event: KeyboardEvent) => {
        if (
            event.metaKey ||
            event.ctrlKey ||
            event.altKey ||
            event.key.toLowerCase() !== 'n' ||
            isTyping(event.target) ||
            !target
        ) {
            return;
        }

        event.preventDefault();
        void create(event.shiftKey ? 'board' : 'page', target.id);
    });

    useEffect(() => {
        const listener = (event: KeyboardEvent) => onKeyDown(event);

        window.addEventListener('keydown', listener);

        return () => window.removeEventListener('keydown', listener);
    }, []);
}

/**
 * A space's own create controls: pages and boards are peers, so each gets an equal button.
 */
export default function NewButton({ spaceId }: { spaceId: number }) {
    const { create, pending } = useCreatePage();

    return (
        <div className="flex gap-2">
            {(['page', 'board'] as const).map((type) => {
                const Icon = typeIcons[type];

                return (
                    <Button
                        key={type}
                        type="button"
                        aria-busy={pending === type}
                        title={`New ${type} (${shortcutFor(type)})`}
                        onClick={() => create(type, spaceId)}
                    >
                        {pending === type ? (
                            <Spinner aria-hidden />
                        ) : (
                            <Icon aria-hidden />
                        )}
                        New {type}
                    </Button>
                );
            })}
        </div>
    );
}
