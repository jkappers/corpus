import { Head, useHttp, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { useConfirm } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { SectionHeading } from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useEnums } from '@/hooks/use-enums';
import { ApiError, api, refreshPage } from '@/lib/api';
import {
    destroy as removeMember,
    store as addMember,
    update as updateMember,
} from '@/routes/api/v1/spaces/members';
import type { SpaceRole } from '@/types';

type Member = { id: number; name: string; email: string; role: SpaceRole };

type Space = {
    id: number;
    name: string;
    members: Member[];
};

function RoleOptions() {
    const { spaceRole } = useEnums();

    return (
        <>
            {spaceRole.map((role) => (
                <option key={role.value} value={role.value}>
                    {role.label}
                </option>
            ))}
        </>
    );
}

function InviteForm({ spaceId }: { spaceId: number }) {
    const form = useHttp<{ email: string; role: SpaceRole }>({
        email: '',
        role: 'editor',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(addMember(spaceId).url, {
            onSuccess: () => {
                toast.success('Member invited.');
                form.reset();
                refreshPage();
            },
        });
    };

    return (
        <form onSubmit={submit} className="mt-4">
            <label className="label" htmlFor="invite-email">
                Invite by email
            </label>
            <div className="flex flex-wrap items-center gap-2">
                <Input
                    id="invite-email"
                    type="email"
                    className="min-w-0 flex-1 basis-48"
                    value={form.data.email}
                    aria-invalid={form.errors.email ? true : undefined}
                    onChange={(e) => form.setData('email', e.target.value)}
                />
                <select
                    className="input w-auto"
                    aria-label="Role"
                    value={form.data.role}
                    onChange={(e) =>
                        form.setData('role', e.target.value as SpaceRole)
                    }
                >
                    <RoleOptions />
                </select>
                <Button
                    type="submit"
                    variant="primary"
                    disabled={form.processing || form.data.email === ''}
                >
                    Invite
                </Button>
            </div>
            <InputError
                role="alert"
                className="mt-1"
                message={form.errors.email ?? form.errors.role}
            />
        </form>
    );
}

export default function Members({ space }: { space: Space }) {
    const { confirm, dialog } = useConfirm();
    const { spaceRole } = useEnums();
    const currentUserId = usePage().props.auth.user.id;
    const [pendingId, setPendingId] = useState<number | null>(null);
    const [failure, setFailure] = useState<{
        id: number;
        message: string;
    } | null>(null);

    const run = async (
        member: Member,
        request: () => Promise<unknown>,
        success: string,
    ) => {
        setFailure(null);
        setPendingId(member.id);

        try {
            await request();
            await refreshPage();
            toast.success(success);
        } catch (e) {
            setFailure({
                id: member.id,
                message:
                    e instanceof ApiError
                        ? (e.first('role') ?? e.message)
                        : 'Something went wrong. Try again.',
            });
        } finally {
            setPendingId(null);
        }
    };

    return (
        <>
            {dialog}
            <Head title={`${space.name} members`} />
            <section>
                <SectionHeading
                    description={
                        <>
                            <dl className="space-y-0.5">
                                {spaceRole.map((role) => (
                                    <div key={role.value}>
                                        <dt className="inline font-medium text-ink-2">
                                            {role.label}:
                                        </dt>{' '}
                                        <dd className="inline">
                                            {role.description}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            <p className="mt-1">
                                Emails without an account get an invitation.
                            </p>
                        </>
                    }
                >
                    Members
                </SectionHeading>
                {space.members.length === 0 ? (
                    <p className="text-sm text-ink-3">No members yet.</p>
                ) : (
                    <ul role="list">
                        {space.members.map((member) => (
                            <li
                                key={member.id}
                                className="border-b border-rule py-2 text-sm"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="min-w-0 flex-1">
                                        <span className="font-medium">
                                            {member.name}
                                        </span>
                                        {member.id === currentUserId && (
                                            <span className="ml-1.5 text-xs text-ink-3">
                                                You
                                            </span>
                                        )}
                                        <span className="block truncate text-xs text-ink-3">
                                            {member.email}
                                        </span>
                                    </span>
                                    <select
                                        className="input w-auto"
                                        aria-label={`Role for ${member.name}`}
                                        value={member.role}
                                        disabled={pendingId === member.id}
                                        onChange={(e) =>
                                            run(
                                                member,
                                                () =>
                                                    api(
                                                        'patch',
                                                        updateMember({
                                                            space: space.id,
                                                            user: member.id,
                                                        }).url,
                                                        {
                                                            role: e.target
                                                                .value,
                                                        },
                                                    ),
                                                'Role updated.',
                                            )
                                        }
                                    >
                                        <RoleOptions />
                                    </select>
                                    <Button
                                        type="button"
                                        variant="quiet"
                                        size="sm"
                                        disabled={pendingId === member.id}
                                        onClick={async () => {
                                            if (
                                                await confirm({
                                                    title: `Remove ${member.name}?`,
                                                    description: `They lose access to "${space.name}".`,
                                                    confirmLabel:
                                                        'Remove member',
                                                    destructive: true,
                                                })
                                            ) {
                                                run(
                                                    member,
                                                    () =>
                                                        api(
                                                            'delete',
                                                            removeMember({
                                                                space: space.id,
                                                                user: member.id,
                                                            }).url,
                                                        ),
                                                    'Member removed.',
                                                );
                                            }
                                        }}
                                    >
                                        Remove
                                    </Button>
                                </div>
                                {failure?.id === member.id && (
                                    <InputError
                                        role="alert"
                                        className="mt-1"
                                        message={failure.message}
                                    />
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                <InviteForm spaceId={space.id} />
            </section>
        </>
    );
}
