import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { api, refreshPage } from '@/lib/api';
import { routesFor } from '@/lib/page-routes';
import { creatableSpaces, spaceLabel } from '@/lib/spaces';
import { show as spaceShow } from '@/routes/spaces';
import type { PageType, Space } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Layers, Lock } from 'lucide-react';
import { toast } from 'sonner';

/**
 * "Space /" above a page or board title: where it lives, a link back there, and
 * (for editors) a menu to move it. Shared by pages and boards so both say where they are.
 */
export default function ItemLocation({
    type,
    id,
    spaceId,
    canMove,
    onMoved,
    trailingSlash = true,
}: {
    type: PageType;
    id: number;
    spaceId: number;
    canMove: boolean;
    onMoved: (updatedAt: string) => void;
    trailingSlash?: boolean;
}) {
    const { spaces } = usePage<{ spaces: Space[] }>().props;
    const space = spaces.find((item) => item.id === spaceId);
    const destinations = creatableSpaces(spaces);
    const SpaceIcon = space?.visibility === 'private' ? Lock : Layers;

    const moveTo = async (value: string) => {
        const target = destinations.find((item) => item.id === Number(value));

        if (!target || target.id === spaceId) {
            return;
        }

        try {
            const { data } = await api<{ data: { updated_at: string } }>(
                'patch',
                routesFor(type).api.update(id).url,
                { space_id: target.id },
            );
            onMoved(data.updated_at);
            await refreshPage();
            toast(`Moved to ${spaceLabel(target)}`);
        } catch {
            toast.error(`Couldn't move the ${type}. Try again.`);
        }
    };

    if (!space) {
        return null;
    }

    return (
        <nav
            aria-label="Location"
            className="flex min-w-0 items-center gap-0.5 text-xs text-ink-3"
        >
            <Link
                href={spaceShow(space.id)}
                className="flex min-w-0 items-center gap-1.5 rounded-ctl py-0.5 hover:text-ink"
            >
                <SpaceIcon className="size-3.5 shrink-0" aria-hidden />
                <span className="truncate">{spaceLabel(space)}</span>
            </Link>
            {canMove && destinations.length > 1 && (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            variant="quiet"
                            size="icon-sm"
                            aria-label={`Move ${type} to another space`}
                            title="Move to…"
                            className="size-5 text-ink-3 hover:text-ink"
                        >
                            <ChevronDown className="size-3.5" aria-hidden />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="min-w-48">
                        <DropdownMenuLabel className="text-xs text-ink-3">
                            Move to
                        </DropdownMenuLabel>
                        <DropdownMenuRadioGroup
                            value={String(spaceId)}
                            onValueChange={moveTo}
                        >
                            {destinations.map((item) => (
                                <DropdownMenuRadioItem
                                    key={item.id}
                                    value={String(item.id)}
                                >
                                    {spaceLabel(item)}
                                </DropdownMenuRadioItem>
                            ))}
                        </DropdownMenuRadioGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
            {trailingSlash && (
                <span aria-hidden className="pl-1">
                    /
                </span>
            )}
        </nav>
    );
}
