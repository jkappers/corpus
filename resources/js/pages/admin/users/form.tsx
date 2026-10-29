import { PageContainer, PageHeader } from '@/components/page-container';
import { Button, buttonVariants } from '@/components/ui/button';
import { Head, Link, router, useHttp } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { useEnums } from '@/hooks/use-enums';
import { store, update } from '@/routes/api/v1/admin/users';
import { index } from '@/routes/admin/users';

type Managed = {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'admin' | 'member';
    can_update_role: boolean;
    assignable_roles: { value: Managed['role']; label: string }[];
};

export default function UserForm({ managed }: { managed: Managed | null }) {
    const { userRole } = useEnums();
    const form = useHttp({
        name: managed?.name ?? '',
        email: managed?.email ?? '',
        role: managed?.role ?? 'member',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (managed) {
            form.transform((data) =>
                managed.can_update_role
                    ? data
                    : { name: data.name, email: data.email },
            );
            void form.patch(update(managed.id).url, {
                onSuccess: () => {
                    toast.success('User saved.');
                    router.visit(index().url);
                },
            });
        } else {
            void form.post(store().url, {
                onSuccess: () => {
                    toast.success(`Invitation sent to ${form.data.email}.`);
                    router.visit(index().url);
                },
            });
        }
    };

    return (
        <>
            <Head title={managed ? 'Edit user' : 'New user'} />
            <PageContainer size="narrow">
                <PageHeader
                    title={managed ? `Edit ${managed.name}` : 'New user'}
                />

                <form onSubmit={submit} className="flex flex-col gap-3">
                    <label className="flex flex-col gap-1 text-sm">
                        Name
                        <input
                            className="input"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.name} />
                    </label>
                    <label className="flex flex-col gap-1 text-sm">
                        Email
                        <input
                            type="email"
                            className="input"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.email} />
                    </label>
                    <label className="flex flex-col gap-1 text-sm">
                        Role
                        <select
                            className="input"
                            value={form.data.role}
                            disabled={
                                managed ? !managed.can_update_role : false
                            }
                            onChange={(e) =>
                                form.setData(
                                    'role',
                                    e.target.value as Managed['role'],
                                )
                            }
                        >
                            {(managed?.assignable_roles ?? userRole).map(
                                (role) => (
                                    <option key={role.value} value={role.value}>
                                        {role.label}
                                    </option>
                                ),
                            )}
                        </select>
                        <InputError message={form.errors.role} />
                    </label>
                    {!managed && (
                        <p className="text-sm text-ink-3">
                            An invitation email will be sent so they can set
                            their own password.
                        </p>
                    )}

                    <div className="flex gap-2">
                        <Button
                            type="submit"
                            variant="primary"
                            disabled={form.processing}
                        >
                            {managed ? 'Save' : 'Send invitation'}
                        </Button>
                        <Link href={index()} className={buttonVariants()}>
                            Cancel
                        </Link>
                    </div>
                </form>
            </PageContainer>
        </>
    );
}
