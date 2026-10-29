import { Button } from '@/components/ui/button';
import { Link, router, usePage } from '@inertiajs/react';
import {
    ChevronsUpDown,
    House,
    Inbox,
    Paperclip,
    Layers,
    LayoutGrid,
    Lock,
    LogOut,
    Menu,
    Plus,
    Search,
    Settings,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import GlobalSearch from '@/components/global-search';
import { typeIcons, useNewShortcuts } from '@/components/new-button';
import { Spinner } from '@/components/ui/spinner';
import { useCreatePage } from '@/hooks/use-create-page';
import { isTyping, modKey } from '@/lib/shortcuts';
import { spaceLabel } from '@/lib/spaces';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useInitials } from '@/hooks/use-initials';
import { home, logout } from '@/routes';
import { trash } from '@/routes/pages';
import { index as filesIndex } from '@/routes/files';
import { index as inboxIndex } from '@/routes/inbox';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { edit } from '@/routes/profile';
import { index as spacesIndex, show as spaceShow } from '@/routes/spaces';
import type { Auth, BreadcrumbItem, PageType, Space } from '@/types';

const navItem =
    'text-ink-2 hover:bg-hover hover:text-ink flex h-7 w-full items-center gap-2.5 rounded-ctl px-2 text-sm font-medium [@media(pointer:coarse)]:h-11';

const sectionLabel = 'px-2 pb-1 text-xs font-medium text-ink-3';

function isActive(path: string, base: string): boolean {
    return path === base || path.startsWith(`${base}/`);
}

type NavLinkProps = {
    href: Parameters<typeof Link>[0]['href'];
    active: boolean;
    icon: LucideIcon;
    children: ReactNode;
    onNavigate?: () => void;
    badge?: ReactNode;
    prefetch?: boolean;
};

function NavLink({
    href,
    active,
    icon: NavIcon,
    children,
    onNavigate,
    badge,
    prefetch,
}: NavLinkProps) {
    return (
        <Link
            href={href}
            onClick={onNavigate}
            prefetch={prefetch}
            aria-current={active ? 'page' : undefined}
            className={`${navItem} ${active ? 'bg-sel text-ink' : ''}`}
        >
            <NavIcon
                className={`size-4 shrink-0 ${active ? 'text-[var(--accent-blue)]' : 'text-ink-3'}`}
                aria-hidden
            />
            {children}
            {badge}
        </Link>
    );
}

type RowCreate = {
    key: string;
    types: [PageType, ...PageType[]];
    spaceId?: number;
    label: string;
};

/**
 * A nav row with a "+" that appears on hover or focus (always on touch). One type
 * creates directly; several open a small menu.
 */
function CreatableRow({
    create,
    busyKey,
    onCreate,
    children,
}: {
    create: RowCreate;
    busyKey: string | null;
    onCreate: (create: RowCreate, type: PageType) => void;
    children: ReactNode;
}) {
    const busy = busyKey === create.key;
    const trigger = (
        <Button
            type="button"
            variant="quiet"
            size="icon-sm"
            aria-label={create.label}
            title={create.label}
            aria-busy={busy}
            onClick={
                create.types.length === 1
                    ? () => onCreate(create, create.types[0])
                    : undefined
            }
            className="absolute top-1/2 right-1 -translate-y-1/2 text-ink-3 opacity-0 group-focus-within/row:opacity-100 group-hover/row:opacity-100 hover:text-ink aria-busy:opacity-100 aria-expanded:opacity-100 [@media(pointer:coarse)]:opacity-100"
        >
            {busy ? (
                <Spinner className="size-3.5" aria-hidden />
            ) : (
                <Plus aria-hidden />
            )}
        </Button>
    );

    return (
        <div className="group/row relative [&>a]:pr-8">
            {children}
            {create.types.length === 1 ? (
                trigger
            ) : (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>{trigger}</DropdownMenuTrigger>
                    <DropdownMenuContent align="start" side="right">
                        {create.types.map((type) => {
                            const Icon = typeIcons[type];

                            return (
                                <DropdownMenuItem
                                    key={type}
                                    onSelect={() => onCreate(create, type)}
                                >
                                    <Icon aria-hidden />
                                    New {type}
                                </DropdownMenuItem>
                            );
                        })}
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
        </div>
    );
}

function AccountMenu({ onNavigate }: { onNavigate?: () => void }) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const getInitials = useInitials();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className={`${navItem} h-9 justify-between gap-2 px-2 [@media(pointer:coarse)]:h-11`}
                data-test="account-menu"
            >
                <Avatar className="size-5 shrink-0 rounded-full">
                    <AvatarFallback className="bg-hover text-[11px] text-ink">
                        {getInitials(auth.user.name)}
                    </AvatarFallback>
                </Avatar>
                <span className="min-w-0 flex-1 truncate text-left">
                    {auth.user.name}
                </span>
                <ChevronsUpDown
                    className="size-3.5 shrink-0 text-ink-3"
                    aria-hidden
                />
            </DropdownMenuTrigger>
            <DropdownMenuContent
                side="top"
                align="start"
                className="w-[var(--radix-dropdown-menu-trigger-width)] min-w-48"
            >
                <div className="truncate px-2 py-1.5 text-xs text-ink-3">
                    {auth.user.email}
                </div>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={edit()} onClick={onNavigate} prefetch>
                        <Settings aria-hidden />
                        Settings
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        href={logout()}
                        as="button"
                        className="w-full"
                        onClick={() => router.flushAll()}
                        data-test="logout-button"
                    >
                        <LogOut aria-hidden />
                        Log out
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function Rail({ onNavigate }: { onNavigate?: () => void }) {
    const { auth, spaces, space, page, inboxCount } = usePage<{
        auth: Auth;
        inboxCount: number;
        spaces: Space[];
        space?: Space | null;
        page?: { space_id?: number } | null;
    }>().props;
    const { url } = usePage();
    const path = url.split('?')[0].replace(/(.)\/$/, '$1');
    const spaceId = space?.id ?? page?.space_id;
    const browsing = path === '/spaces';
    const mySpace = spaces.find((item) => item.is_mine);
    const sharedSpaces = spaces.filter((item) => !item.is_personal);
    const { create, pending } = useCreatePage();
    const [creatingIn, setCreatingIn] = useState<string | null>(null);
    const busyKey = pending ? creatingIn : null;

    const onCreate = (row: RowCreate, type: PageType) => {
        setCreatingIn(row.key);
        void create(type, row.spaceId);
    };

    const spaceRow = (item: Space): RowCreate => ({
        key: `space-${item.id}`,
        types: ['page', 'board'],
        spaceId: item.id,
        label: `New in ${spaceLabel(item)}`,
    });

    const spaceLink = (item: Space, icon: LucideIcon) => (
        <NavLink
            key={item.id}
            href={spaceShow(item.id)}
            active={spaceId === item.id}
            icon={icon}
            onNavigate={onNavigate}
        >
            <span className="truncate">{spaceLabel(item)}</span>
            {item.visibility !== 'open' && !item.is_personal && (
                <span className="sr-only">({item.visibility})</span>
            )}
        </NavLink>
    );

    return (
        <div className="flex h-full min-h-0 flex-col">
            <nav
                aria-label="Main"
                className="flex shrink-0 flex-col gap-0.5 px-2 pt-2"
            >
                <NavLink
                    href={home()}
                    active={path === '/'}
                    icon={House}
                    onNavigate={onNavigate}
                >
                    Home
                </NavLink>
                <NavLink
                    href={inboxIndex()}
                    active={isActive(path, '/inbox')}
                    icon={Inbox}
                    onNavigate={onNavigate}
                    badge={
                        inboxCount > 0 && (
                            <span className="tag ml-auto">
                                {inboxCount}
                                <span className="sr-only"> unread</span>
                            </span>
                        )
                    }
                >
                    Inbox
                </NavLink>
                <NavLink
                    href={filesIndex()}
                    active={isActive(path, '/files')}
                    icon={Paperclip}
                    onNavigate={onNavigate}
                >
                    Files
                </NavLink>
                <NavLink
                    href={trash()}
                    active={isActive(path, '/trash')}
                    icon={Trash2}
                    onNavigate={onNavigate}
                >
                    Trash
                </NavLink>
            </nav>

            <nav
                aria-label="Spaces"
                className="flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto px-2 pt-4 pb-2"
            >
                <div className={sectionLabel}>Spaces</div>
                {mySpace && (
                    <CreatableRow
                        create={spaceRow(mySpace)}
                        busyKey={busyKey}
                        onCreate={onCreate}
                    >
                        {spaceLink(mySpace, Lock)}
                    </CreatableRow>
                )}
                {sharedSpaces.map((item) => {
                    const link = spaceLink(
                        item,
                        item.visibility === 'private' ? Lock : Layers,
                    );

                    return item.can_edit ? (
                        <CreatableRow
                            key={item.id}
                            create={spaceRow(item)}
                            busyKey={busyKey}
                            onCreate={onCreate}
                        >
                            {link}
                        </CreatableRow>
                    ) : (
                        link
                    );
                })}
                <NavLink
                    href={spacesIndex()}
                    active={browsing}
                    icon={LayoutGrid}
                    onNavigate={onNavigate}
                >
                    Browse spaces
                </NavLink>
            </nav>

            <div className="flex shrink-0 flex-col gap-2 border-t border-rule p-2">
                <nav aria-label="Utility" className="flex flex-col gap-0.5">
                    {auth.user.is_admin && (
                        <NavLink
                            href={adminUsersIndex()}
                            active={isActive(path, '/admin')}
                            icon={ShieldCheck}
                            onNavigate={onNavigate}
                        >
                            Admin
                        </NavLink>
                    )}
                </nav>
                <AccountMenu onNavigate={onNavigate} />
            </div>
        </div>
    );
}

function Wordmark() {
    return (
        <Link
            href={home()}
            className="flex items-center gap-2 font-semibold tracking-tight"
        >
            <AppLogoIcon className="size-5 fill-current" />
            Corpus
        </Link>
    );
}

function SearchButton({ onClick }: { onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-keyshortcuts="Control+K Meta+K"
            className={`${navItem} border border-rule`}
        >
            <Search className="size-4 shrink-0 text-ink-3" aria-hidden />
            <span className="flex-1 text-left">Search</span>
            <kbd className="hidden text-xs text-ink-3 md:inline">{modKey}K</kbd>
        </button>
    );
}

export default function AppLayout({
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [searching, setSearching] = useState(false);

    useNewShortcuts();

    useEffect(() => router.on('navigate', () => setOpen(false)), []);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            // The page editor claims ⌘K for links while text is selected.
            if (event.defaultPrevented) {
                return;
            }

            if (
                (event.metaKey || event.ctrlKey) &&
                !event.altKey &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                setSearching(true);
            } else if (
                event.key === '/' &&
                !event.metaKey &&
                !event.ctrlKey &&
                !event.altKey &&
                !isTyping(event.target)
            ) {
                event.preventDefault();
                setSearching(true);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    const openSearch = () => {
        setOpen(false);
        setSearching(true);
    };

    return (
        <div className="min-h-dvh bg-ground text-ink md:grid md:grid-cols-[var(--spacing-rail)_minmax(0,1fr)]">
            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-ctl focus:bg-raised focus:px-3 focus:py-2 focus:text-sm focus:shadow-md"
            >
                Skip to content
            </a>
            <aside className="hidden flex-col border-r border-rule bg-rail md:sticky md:top-0 md:flex md:h-dvh">
                <div className="flex h-12 shrink-0 items-center px-4">
                    <Wordmark />
                </div>
                <div className="shrink-0 px-2">
                    <SearchButton onClick={openSearch} />
                </div>
                <div className="min-h-0 flex-1">
                    <Rail />
                </div>
            </aside>

            <Sheet open={open} onOpenChange={setOpen}>
                <header className="sticky top-0 z-30 flex h-12 items-center justify-between border-b border-rule bg-rail px-4 md:hidden">
                    <Wordmark />
                    <div className="flex items-center gap-1">
                        <Button
                            type="button"
                            variant="quiet"
                            size="icon"
                            aria-label="Search"
                            onClick={openSearch}
                        >
                            <Search className="size-4" aria-hidden />
                        </Button>
                        <SheetTrigger asChild>
                            <Button
                                type="button"
                                variant="quiet"
                                size="icon"
                                aria-label="Open menu"
                            >
                                <Menu className="size-4" aria-hidden />
                            </Button>
                        </SheetTrigger>
                    </div>
                </header>
                <SheetContent
                    side="left"
                    onOpenAutoFocus={(event) => {
                        event.preventDefault();
                        (event.currentTarget as HTMLElement).focus();
                    }}
                    className="w-[min(18rem,85vw)] gap-0 border-rule bg-rail p-0 sm:max-w-none"
                >
                    <SheetTitle className="sr-only">Menu</SheetTitle>
                    <SheetDescription className="sr-only">
                        Navigate Corpus
                    </SheetDescription>
                    <div className="flex h-12 shrink-0 items-center px-4">
                        <Wordmark />
                    </div>
                    <div className="min-h-0 flex-1">
                        <Rail onNavigate={() => setOpen(false)} />
                    </div>
                </SheetContent>
            </Sheet>

            <GlobalSearch open={searching} onOpenChange={setSearching} />

            <main id="main" tabIndex={-1} className="min-w-0 outline-none">
                {children}
            </main>
        </div>
    );
}
