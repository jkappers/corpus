import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { PageContainer, PageHeader } from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { danger, edit, members, show } from '@/routes/spaces';

export default function SpaceSettingsLayout({ children }: PropsWithChildren) {
    const { space } = usePage<{ space: { id: number; name: string } }>().props;
    const { isCurrentUrl } = useCurrentUrl();

    const navItems = [
        { title: 'General', href: edit(space.id) },
        { title: 'Members', href: members(space.id) },
        { title: 'Danger zone', href: danger(space.id) },
    ];

    return (
        <PageContainer>
            <Link
                href={show(space.id)}
                className="text-xs text-ink-2 hover:underline"
            >
                ← {space.name}
            </Link>
            <PageHeader title="Space settings" />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-40">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Space settings"
                    >
                        {navItems.map((item) => (
                            <Button
                                key={item.title}
                                size="sm"
                                variant="quiet"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>{item.title}</Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <section className="max-w-xl flex-1 space-y-8">
                    {children}
                </section>
            </div>
        </PageContainer>
    );
}
