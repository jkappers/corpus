import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { ApiError, api, refreshPage } from '@/lib/api';
import { destroy, store } from '@/routes/api/v1/tokens';
import { edit } from '@/routes/tokens';
import type { Auth } from '@/types';

type Token = {
    id: string;
    name: string;
    scopes: string[];
    created_at: string;
    expires_at: string;
};

type ScopeOption = { value: string; label: string; admin: boolean };

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
});

export default function Tokens({ tokens }: { tokens: Token[] }) {
    const { auth, tokenScopes } = usePage<{
        auth: Auth;
        tokenScopes: ScopeOption[];
    }>().props;
    const { confirm, dialog } = useConfirm();
    const available = tokenScopes.filter(
        (scope) => !scope.admin || auth.user.is_admin,
    );
    const [name, setName] = useState('');
    const [scopes, setScopes] = useState<string[]>([]);
    const [error, setError] = useState<ApiError | null>(null);
    const [created, setCreated] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const toggle = (scope: string) =>
        setScopes((current) =>
            current.includes(scope)
                ? current.filter((value) => value !== scope)
                : [...current, scope],
        );

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setError(null);

        try {
            const response = await api<{ meta: { token: string } }>(
                'post',
                store().url,
                { name, scopes },
            );

            setCreated(response.meta.token);
            setName('');
            setScopes([]);
            await refreshPage();
        } catch (e) {
            setError(e instanceof ApiError ? e : null);
        } finally {
            setProcessing(false);
        }
    };

    return (
        <>
            {dialog}
            <Head title="API tokens" />
            <h1 className="sr-only">API tokens</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="API tokens"
                    description="Tokens let scripts and integrations act as you, limited to the scopes you choose."
                />

                {created && (
                    <div
                        role="status"
                        className="rounded-md border border-rule p-3 text-sm"
                    >
                        <p className="mb-1 font-medium">
                            Copy your token now. It will not be shown again.
                        </p>
                        <code className="block text-xs break-all">
                            {created}
                        </code>
                        <Button
                            type="button"
                            size="sm"
                            className="mt-2"
                            onClick={() => setCreated(null)}
                        >
                            Done
                        </Button>
                    </div>
                )}

                <form onSubmit={submit} className="space-y-3">
                    <label className="flex flex-col gap-1 text-sm">
                        Name
                        <input
                            className="input"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="CI pipeline"
                            required
                        />
                        <InputError message={error?.first('name')} />
                    </label>
                    <fieldset className="space-y-1">
                        <legend className="mb-1 text-sm">Scopes</legend>
                        {available.map((scope) => (
                            <label
                                key={scope.value}
                                className="flex items-start gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    className="mt-1"
                                    checked={scopes.includes(scope.value)}
                                    onChange={() => toggle(scope.value)}
                                />
                                <span>
                                    <code className="text-xs">
                                        {scope.value}
                                    </code>
                                    <span className="block text-xs text-ink-3">
                                        {scope.label}
                                    </span>
                                </span>
                            </label>
                        ))}
                        <InputError
                            message={
                                error?.first('scopes') ??
                                error?.first('scopes.0')
                            }
                        />
                    </fieldset>
                    <Button
                        type="submit"
                        variant="primary"
                        disabled={
                            processing || scopes.length === 0 || name === ''
                        }
                    >
                        Create token
                    </Button>
                </form>

                <section>
                    <Heading variant="small" title="Active tokens" />
                    {tokens.length === 0 ? (
                        <p className="text-sm text-ink-3">No active tokens.</p>
                    ) : (
                        <ul role="list">
                            {tokens.map((token) => (
                                <li
                                    key={token.id}
                                    className="flex flex-wrap items-center gap-2 border-b border-rule py-2 text-sm"
                                >
                                    <span className="min-w-0 flex-1">
                                        <span className="font-medium">
                                            {token.name}
                                        </span>
                                        <span className="block truncate text-xs text-ink-3">
                                            {token.scopes.join(', ')} · expires{' '}
                                            {dateFormatter.format(
                                                new Date(token.expires_at),
                                            )}
                                        </span>
                                    </span>
                                    <Button
                                        type="button"
                                        variant="quiet"
                                        size="sm"
                                        onClick={async () => {
                                            if (
                                                await confirm({
                                                    title: `Revoke "${token.name}"?`,
                                                    description:
                                                        'Anything using this token stops working immediately.',
                                                    confirmLabel:
                                                        'Revoke token',
                                                    destructive: true,
                                                })
                                            ) {
                                                await api(
                                                    'delete',
                                                    destroy(token.id).url,
                                                );
                                                await refreshPage();
                                            }
                                        }}
                                    >
                                        Revoke
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

Tokens.layout = {
    breadcrumbs: [{ title: 'API tokens', href: edit() }],
};
