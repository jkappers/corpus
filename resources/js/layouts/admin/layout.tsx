import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { auditLog } from '@/routes/admin';
import { index as usersIndex } from '@/routes/admin/users';
import type { NavItem } from '@/types';

const adminNavItems: (NavItem & { match: string })[] = [
    { title: 'Users', href: usersIndex(), icon: null, match: '/admin/users' },
    {
        title: 'Audit log',
        href: auditLog(),
        icon: null,
        match: '/admin/audit-log',
    },
];

export default function AdminLayout({ children }: PropsWithChildren) {
    const path = usePage().url.split('?')[0];

    return (
        <>
            <div className="flex h-12 items-center gap-6 border-b border-rule px-4 md:px-6">
                <span className="text-sm font-semibold tracking-tight">
                    Admin
                </span>
                <nav aria-label="Admin" className="flex h-full gap-4">
                    {adminNavItems.map((item) => {
                        const active =
                            path === item.match ||
                            path.startsWith(`${item.match}/`);

                        return (
                            <Link
                                key={item.match}
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                className="flex h-full items-center border-b-2 border-transparent px-0.5 pt-0.5 text-sm font-medium text-ink-3 hover:text-ink aria-[current=page]:border-[var(--accent-blue)] aria-[current=page]:text-ink"
                            >
                                {item.title}
                            </Link>
                        );
                    })}
                </nav>
            </div>
            {children}
        </>
    );
}
