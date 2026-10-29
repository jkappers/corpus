import EmptyState from '@/components/empty-state';
import { PageContainer, PageHeader } from '@/components/page-container';
import Pagination from '@/components/pagination';
import type { PaginationState } from '@/components/pagination';
import { useConfirm } from '@/components/confirm-dialog';
import { Button, buttonVariants } from '@/components/ui/button';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { api, ApiError, refreshPage } from '@/lib/api';
import { destroy, passwordReset } from '@/routes/api/v1/admin/users';
import { create, edit, index } from '@/routes/admin/users';

type Row = {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'admin' | 'member';
    can_delete: boolean;
    email_verified_at: string | null;
    created_at: string;
};

export default function Users({
    users,
    pagination,
    filters,
}: {
    users: Row[];
    pagination: PaginationState;
    filters: { q: string };
}) {
    const [q, setQ] = useState(filters.q);
    const { confirm, dialog } = useConfirm();

    const sendReset = async (user: Row) => {
        try {
            await api('post', passwordReset(user.id).url);
            toast.success(`Password reset email sent to ${user.email}.`);
        } catch (error) {
            toast.error(
                error instanceof ApiError
                    ? (Object.values(error.errors)[0]?.[0] ?? error.message)
                    : "Couldn't send the reset email.",
            );
        }
    };

    const remove = async (user: Row) => {
        try {
            await api('delete', destroy(user.id).url);
            await refreshPage();
            toast(`Deleted ${user.name}.`);
        } catch {
            toast.error(`Couldn't delete ${user.name}.`);
        }
    };

    const search = (e: FormEvent) => {
        e.preventDefault();
        router.get(index().url, q ? { q } : {}, { preserveState: true });
    };

    return (
        <>
            <Head title="Users" />
            {dialog}
            <PageContainer>
                <PageHeader
                    title="Users"
                    actions={
                        <Link
                            href={create()}
                            className={buttonVariants({ variant: 'primary' })}
                        >
                            New user
                        </Link>
                    }
                />

                <form onSubmit={search} className="mb-4 flex gap-2">
                    <input
                        className="input max-w-xs"
                        placeholder="Search name or email"
                        aria-label="Search users"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                    />
                    <Button type="submit">Search</Button>
                </form>

                <div role="list">
                    {users.map((user) => (
                        <div
                            key={user.id}
                            role="listitem"
                            className="flex items-center gap-2 border-b border-rule py-2"
                        >
                            <div className="flex min-w-0 flex-1 items-baseline gap-2">
                                <span className="truncate font-medium">
                                    {user.name}
                                </span>
                                {user.role !== 'member' && (
                                    <span className="tag">{user.role}</span>
                                )}
                                {!user.email_verified_at && (
                                    <span className="tag">unverified</span>
                                )}
                                <span className="truncate text-sm text-ink-3">
                                    {user.email}
                                </span>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => sendReset(user)}
                            >
                                Send reset email
                            </Button>
                            <Link
                                href={edit(user.id)}
                                className={buttonVariants({ size: 'sm' })}
                            >
                                Edit
                            </Link>
                            {user.can_delete && (
                                <Button
                                    type="button"
                                    variant="danger"
                                    size="sm"
                                    onClick={async () => {
                                        if (
                                            await confirm({
                                                title: `Delete ${user.name}?`,
                                                description:
                                                    'Their personal space and its pages are deleted too.',
                                                confirmLabel: 'Delete user',
                                                destructive: true,
                                            })
                                        ) {
                                            await remove(user);
                                        }
                                    }}
                                >
                                    Delete
                                </Button>
                            )}
                        </div>
                    ))}
                    {users.length === 0 && (
                        <EmptyState title="No users found" />
                    )}
                </div>

                <Pagination pagination={pagination} />
            </PageContainer>
        </>
    );
}
