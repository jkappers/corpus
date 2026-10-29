import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';
import { useConfirm } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { SectionHeading } from '@/components/page-container';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { ApiError, api, refreshPage } from '@/lib/api';
import { destroy } from '@/routes/api/v1/spaces';
import {
    destroy as destroyContent,
    transfer as transferContent,
} from '@/routes/api/v1/spaces/content';
import { index } from '@/routes/spaces';

type Space = {
    id: number;
    name: string;
    can_delete: boolean;
    delete_locked_reason: string | null;
    pages_count: number;
};

type Target = { id: number; name: string };

type Props = { space: Space; transfer_targets: Target[] };

type Confirm = ReturnType<typeof useConfirm>['confirm'];

const CONFIRMATION = 'I accept the consequences';

function DangerRow({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <div className="border-b border-rule py-4 last:border-b-0">
            <h3 className="text-sm font-medium">{title}</h3>
            <p className="mb-2 text-xs text-ink-3">{description}</p>
            {children}
        </div>
    );
}

function TransferContent({
    space,
    targets,
    confirm,
}: {
    space: Space;
    targets: Target[];
    confirm: Confirm;
}) {
    const [targetId, setTargetId] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const target = targets.find((t) => String(t.id) === targetId);

    const transfer = async () => {
        if (
            !target ||
            !(await confirm({
                title: `Transfer all content to "${target.name}"?`,
                description: `Every page, board, and trashed item in "${space.name}" moves there.`,
                confirmLabel: 'Transfer content',
                destructive: true,
            }))
        ) {
            return;
        }

        setError(null);
        setBusy(true);

        try {
            await api('post', transferContent(space.id).url, {
                target_space_id: target.id,
            });
            await refreshPage();
            setTargetId('');
            toast.success('Content transferred.');
        } catch (e) {
            setError(
                e instanceof ApiError
                    ? (e.first('target_space_id') ?? e.message)
                    : 'Something went wrong. Try again.',
            );
        } finally {
            setBusy(false);
        }
    };

    return (
        <DangerRow
            title="Transfer all content"
            description="Moves everything in this space to another space you can edit."
        >
            <div className="flex flex-wrap items-center gap-2">
                <select
                    className="input w-auto"
                    aria-label="Destination space"
                    value={targetId}
                    disabled={busy || space.pages_count === 0}
                    onChange={(e) => setTargetId(e.target.value)}
                >
                    <option value="">Choose a space…</option>
                    {targets.map((t) => (
                        <option key={t.id} value={t.id}>
                            {t.name}
                        </option>
                    ))}
                </select>
                <Button
                    type="button"
                    variant="danger"
                    disabled={busy || !target || space.pages_count === 0}
                    onClick={transfer}
                >
                    Transfer content
                </Button>
            </div>
            <InputError role="alert" className="mt-1" message={error ?? ''} />
        </DangerRow>
    );
}

function DeleteContent({ space }: { space: Space }) {
    const [open, setOpen] = useState(false);
    const [typed, setTyped] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const close = (next: boolean) => {
        setOpen(next);
        setTyped('');
        setError(null);
    };

    const remove = async (e: FormEvent) => {
        e.preventDefault();
        setError(null);
        setBusy(true);

        try {
            await api('delete', destroyContent(space.id).url, {
                confirmation: typed,
            });
            await refreshPage();
            close(false);
            toast.success('Content deleted.');
        } catch (err) {
            setError(
                err instanceof ApiError
                    ? (err.first('confirmation') ?? err.message)
                    : 'Something went wrong. Try again.',
            );
        } finally {
            setBusy(false);
        }
    };

    return (
        <DangerRow
            title="Delete all content"
            description="Permanently deletes every page, board, and file in this space, including trash."
        >
            <Button
                type="button"
                variant="danger"
                disabled={space.pages_count === 0}
                onClick={() => close(true)}
            >
                Delete all content
            </Button>
            <Dialog open={open} onOpenChange={close}>
                <DialogContent>
                    <form onSubmit={remove}>
                        <DialogHeader>
                            <DialogTitle>
                                Delete all content in "{space.name}"?
                            </DialogTitle>
                            <DialogDescription>
                                This cannot be undone. Type "{CONFIRMATION}" to
                                continue.
                            </DialogDescription>
                        </DialogHeader>
                        <Input
                            className="my-4"
                            aria-label="Confirmation"
                            autoComplete="off"
                            value={typed}
                            onChange={(e) => setTyped(e.target.value)}
                        />
                        <InputError role="alert" message={error ?? ''} />
                        <DialogFooter>
                            <Button type="button" onClick={() => close(false)}>
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="danger"
                                disabled={busy || typed !== CONFIRMATION}
                            >
                                Delete all content
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </DangerRow>
    );
}

function DeleteSpace({ space, confirm }: { space: Space; confirm: Confirm }) {
    const [deleting, setDeleting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const remove = async () => {
        if (
            !(await confirm({
                title: `Delete "${space.name}"?`,
                description: 'The space is deleted permanently.',
                confirmLabel: 'Delete space',
                destructive: true,
            }))
        ) {
            return;
        }

        setError(null);
        setDeleting(true);

        try {
            await api('delete', destroy(space.id).url);
            router.visit(index().url);
        } catch (e) {
            setError(
                e instanceof ApiError
                    ? e.message
                    : 'Something went wrong. Try again.',
            );
            setDeleting(false);
        }
    };

    return (
        <DangerRow
            title="Delete space"
            description={
                space.delete_locked_reason ??
                'Permanently deletes this empty space.'
            }
        >
            <Button
                type="button"
                variant="danger"
                disabled={deleting || space.delete_locked_reason !== null}
                onClick={remove}
            >
                Delete space
            </Button>
            <InputError role="alert" className="mt-1" message={error ?? ''} />
        </DangerRow>
    );
}

export default function Danger({ space, transfer_targets }: Props) {
    const { confirm, dialog } = useConfirm();

    return (
        <>
            {dialog}
            <Head title={`${space.name} danger zone`} />
            <section>
                <SectionHeading>Danger zone</SectionHeading>
                <TransferContent
                    space={space}
                    targets={transfer_targets}
                    confirm={confirm}
                />
                <DeleteContent space={space} />
                {space.can_delete && (
                    <DeleteSpace space={space} confirm={confirm} />
                )}
            </section>
        </>
    );
}
