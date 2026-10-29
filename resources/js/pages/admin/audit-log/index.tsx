import EmptyState from '@/components/empty-state';
import { PageContainer, PageHeader } from '@/components/page-container';
import Pagination from '@/components/pagination';
import type { PaginationState } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Head, router } from '@inertiajs/react';
import { useEnums } from '@/hooks/use-enums';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { auditLog } from '@/routes/admin';

type Row = {
    id: number;
    event: string;
    event_label: string;
    actor: { id: number; name: string } | null;
    subject_type: string | null;
    subject_label: string | null;
    metadata: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
};

type Filters = { event: string; actor: string; from: string; to: string };

export default function AuditLog({
    logs,
    pagination,
    filters,
}: {
    logs: Row[];
    pagination: PaginationState;
    filters: Filters;
}) {
    const { auditEvent } = useEnums();
    const [form, setForm] = useState<Filters>(filters);

    const apply = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            auditLog().url,
            Object.fromEntries(
                Object.entries(form).filter(([, v]) => v !== ''),
            ),
            { preserveState: true },
        );
    };

    return (
        <>
            <Head title="Audit log" />
            <PageContainer size="wide">
                <PageHeader title="Audit log" />

                <form onSubmit={apply} className="mb-4 flex flex-wrap gap-2">
                    <select
                        className="input max-w-xs"
                        aria-label="Event"
                        value={form.event}
                        onChange={(e) =>
                            setForm({ ...form, event: e.target.value })
                        }
                    >
                        <option value="">All events</option>
                        {auditEvent.map((event) => (
                            <option key={event.value} value={event.value}>
                                {event.label}
                            </option>
                        ))}
                    </select>
                    <input
                        type="date"
                        className="input w-auto"
                        aria-label="From"
                        value={form.from}
                        onChange={(e) =>
                            setForm({ ...form, from: e.target.value })
                        }
                    />
                    <input
                        type="date"
                        className="input w-auto"
                        aria-label="To"
                        value={form.to}
                        onChange={(e) =>
                            setForm({ ...form, to: e.target.value })
                        }
                    />
                    <Button type="submit">Filter</Button>
                </form>

                <div role="list">
                    {logs.map((log) => (
                        <div
                            key={log.id}
                            role="listitem"
                            className="border-b border-rule py-2 text-sm"
                        >
                            <div className="flex flex-wrap items-baseline gap-2">
                                <span className="tag">{log.event_label}</span>
                                <span className="font-medium">
                                    {log.subject_label ?? '—'}
                                </span>
                                <span className="text-ink-3">
                                    by {log.actor?.name ?? 'system'}
                                </span>
                                <time
                                    dateTime={log.created_at}
                                    className="ml-auto text-xs text-ink-3"
                                >
                                    {new Date(log.created_at).toLocaleString()}
                                </time>
                            </div>
                            {(log.metadata || log.ip_address) && (
                                <div className="mt-1 truncate text-xs text-ink-3">
                                    {log.ip_address}
                                    {log.metadata &&
                                        ` · ${JSON.stringify(log.metadata)}`}
                                </div>
                            )}
                        </div>
                    ))}
                    {logs.length === 0 && (
                        <EmptyState title="No entries found" />
                    )}
                </div>

                <Pagination pagination={pagination} />
            </PageContainer>
        </>
    );
}
