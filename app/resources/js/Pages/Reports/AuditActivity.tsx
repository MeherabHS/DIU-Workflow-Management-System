import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Download, ArrowLeft } from 'lucide-react';

type Row = { id: number; action: string; actor?: string | null; entity_type?: string | null; project?: string | null; ip_address?: string | null; created_at: string };

export default function AuditActivity({ total, by_action, rows, canExport, pageTitle = 'Audit Activity Report' }: { total: number; by_action: Record<string, number>; rows: Row[]; canExport: boolean; pageTitle?: string }) {
    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <Link href={route('reports.index')} className="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900"><ArrowLeft className="h-4 w-4" /> Back to Reports</Link>
                    <h1 className="mt-1 text-xl font-semibold text-gray-900">{pageTitle}</h1>
                </div>
                {canExport && <Link href={route('reports.audit-activity', { export: 'csv' })} className="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"><Download className="h-4 w-4" /> Export CSV</Link>}
            </div>
            <div className="mb-4 flex gap-3 flex-wrap">
                <div className="rounded-lg border border-gray-200 bg-white px-4 py-3"><p className="text-xs text-gray-500">Total Activities</p><p className="text-lg font-bold text-gray-900">{total}</p></div>
                {Object.entries(by_action).slice(0, 5).map(([a, c]) => <div key={a} className="rounded-lg border border-gray-200 bg-white px-4 py-3"><p className="text-xs text-gray-500">{a}</p><p className="text-lg font-bold text-gray-900">{c}</p></div>)}
            </div>
            <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                {!rows.length ? <div className="py-12 text-center text-sm text-gray-400">No audit data available.</div> : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Action</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Actor</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Entity</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">IP</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Timestamp</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {rows.map((r) => (
                                    <tr key={r.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm"><span className="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">{r.action}</span></td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.actor ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.entity_type ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.project ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600 font-mono text-xs">{r.ip_address ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.created_at}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
