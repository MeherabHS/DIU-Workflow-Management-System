import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useCallback, useState, useEffect } from 'react';

type Actor = { id: number; name: string };
type Proj = { id: number; title: string };
type Log = {
    id: number;
    action: string;
    entity_type: string | null;
    entity_id: number | null;
    ip_address: string | null;
    created_at: string;
    actor?: Actor | null;
    project?: Proj | null;
    metadata?: Record<string, unknown> | null;
};

export default function Index({
    logs,
    pageTitle = 'Audit Trail',
    pageSubtitle = 'System activity log for governance and accountability.',
    actions,
    entityTypes,
    actors,
    projects,
    filters,
}: {
    logs: { data: Log[]; links: { url: string | null; label: string; active: boolean }[] };
    pageTitle?: string;
    pageSubtitle?: string;
    actions: string[];
    entityTypes: string[];
    actors: Actor[];
    projects: Proj[];
    filters?: Record<string, string>;
}) {
    const [action, setAction] = useState(filters?.action ?? '');
    const [entityType, setEntityType] = useState(filters?.entity_type ?? '');
    const [actorId, setActorId] = useState(filters?.actor_id ?? '');
    const [projectId, setProjectId] = useState(filters?.project_id ?? '');
    const [dateFrom, setDateFrom] = useState(filters?.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters?.date_to ?? '');
    const [expandedId, setExpandedId] = useState<number | null>(null);

    const navigate = useCallback((params: Record<string, string | undefined>) => {
        router.get(route('admin.audit-logs.index'), params, { preserveState: true, replace: true });
    }, []);

    const applyFilters = () => {
        navigate({
            action: action || undefined,
            entity_type: entityType || undefined,
            actor_id: actorId || undefined,
            project_id: projectId || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
        });
    };

    useEffect(() => {
        const timer = setTimeout(applyFilters, 500);
        return () => clearTimeout(timer);
    }, [action, entityType]);

    const formatAction = (action: string) => action.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

    const formatMetadata = (meta: Record<string, unknown> | null) => {
        if (!meta) return null;
        const safe = { ...meta };
        delete safe.changes;
        const entries = Object.entries(safe).filter(([, v]) => v !== null && v !== undefined);
        if (entries.length === 0) return null;
        return entries.map(([k, v]) => `${k}: ${v}`).join(', ');
    };

    const formatChanges = (meta: Record<string, unknown> | null) => {
        if (!meta?.changes) return null;
        const changes = meta.changes as Record<string, { old?: string; new?: string } | string>;
        return Object.entries(changes).map(([key, val]) => {
            if (typeof val === 'object' && val !== null && 'old' in val) {
                return `${key}: "${val.old ?? '—'}" → "${val.new ?? '—'}"`;
            }
            return `${key}: ${val}`;
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <div className="mb-6">
                <h1 className="text-2xl font-bold tracking-tight text-gray-950">{pageTitle}</h1>
                <p className="mt-1 text-sm text-gray-500">{pageSubtitle}</p>
            </div>

            <div className="mb-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <select value={action} onChange={(e) => setAction(e.target.value)} className="rounded-lg border px-3 py-2 text-sm">
                    <option value="">All Actions</option>
                    {actions.map((a) => <option key={a} value={a}>{formatAction(a)}</option>)}
                </select>
                <select value={entityType} onChange={(e) => setEntityType(e.target.value)} className="rounded-lg border px-3 py-2 text-sm">
                    <option value="">All Entities</option>
                    {entityTypes.map((e) => <option key={e} value={e}>{e}</option>)}
                </select>
                <select value={actorId} onChange={(e) => setActorId(e.target.value)} className="rounded-lg border px-3 py-2 text-sm">
                    <option value="">All Users</option>
                    {actors.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                </select>
                <select value={projectId} onChange={(e) => setProjectId(e.target.value)} className="rounded-lg border px-3 py-2 text-sm">
                    <option value="">All Projects</option>
                    {projects.map((p) => <option key={p.id} value={p.id}>{p.title}</option>)}
                </select>
                <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} className="rounded-lg border px-3 py-2 text-sm" />
                <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} className="rounded-lg border px-3 py-2 text-sm" />
            </div>

            {!logs.data.length ? (
                <div className="rounded-xl border border-gray-200 bg-white p-8 text-center text-sm font-medium text-gray-500">No audit records found.</div>
            ) : (
                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-gray-100">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Time</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Actor</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Action</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Entity</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Details</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {logs.data.map((log) => (
                                <tr key={log.id} className="hover:bg-gray-50">
                                    <td className="whitespace-nowrap px-4 py-3 text-xs text-gray-600">{new Date(log.created_at).toLocaleString()}</td>
                                    <td className="px-4 py-3 text-sm">{log.actor?.name ?? 'System'}</td>
                                    <td className="px-4 py-3 text-sm">
                                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium">{formatAction(log.action)}</span>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{log.entity_type ?? '—'}</td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{log.project?.title ?? '—'}</td>
                                    <td className="px-4 py-3 text-right">
                                        <button onClick={() => setExpandedId(expandedId === log.id ? null : log.id)} className="text-xs text-blue-600 hover:underline">
                                            {expandedId === log.id ? 'Hide' : 'Show'}
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {expandedId && (() => {
                        const log = logs.data.find((l) => l.id === expandedId);
                        if (!log) return null;
                        const changes = formatChanges(log.metadata ?? null);
                        const meta = formatMetadata(log.metadata ?? null);
                        return (
                            <div className="border-t border-gray-100 bg-gray-50 px-6 py-4 text-sm">
                                <dl className="grid gap-2 sm:grid-cols-2">
                                    <div><dt className="font-medium text-gray-500">IP</dt><dd>{log.ip_address ?? '—'}</dd></div>
                                    <div><dt className="font-medium text-gray-500">User Agent</dt><dd className="truncate">{String(log.metadata?.user_agent ?? '—')}</dd></div>
                                    {meta && <div className="sm:col-span-2"><dt className="font-medium text-gray-500">Context</dt><dd>{meta}</dd></div>}
                                    {changes && changes.length > 0 && (
                                        <div className="sm:col-span-2">
                                            <dt className="font-medium text-gray-500">Changes</dt>
                                            <ul className="mt-1 space-y-1">
                                                {changes.map((c, i) => <li key={i} className="font-mono text-xs text-gray-700">{c}</li>)}
                                            </ul>
                                        </div>
                                    )}
                                </dl>
                            </div>
                        );
                    })()}
                    {logs.links.length > 3 && (
                        <div className="flex justify-center gap-1 border-t border-gray-100 px-4 py-3">
                            {logs.links.map((link, i) =>
                                link.url ? (
                                    <button key={i} onClick={() => router.get(link.url!, {}, { preserveState: true })} className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100'}`}>
                                        {link.label}
                                    </button>
                                ) : (
                                    <span key={i} className="rounded px-3 py-1 text-sm text-gray-400">
                                        {link.label}
                                    </span>
                                )
                            )}
                        </div>
                    )}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
