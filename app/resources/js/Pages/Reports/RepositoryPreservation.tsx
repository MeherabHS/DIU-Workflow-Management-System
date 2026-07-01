import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Download, ArrowLeft } from 'lucide-react';

type Row = { id: number; title: string; type?: string | null; status: string | null; project?: string | null; department?: string | null; created_by?: string | null; finalized_at?: string | null; finalized_by?: string | null; update_count: number; file_count: number };

export default function RepositoryPreservation({ total, by_status, rows, canExport, pageTitle = 'Repository Preservation Report' }: { total: number; by_status: Record<string, number>; rows: Row[]; canExport: boolean; pageTitle?: string }) {
    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <Link href={route('reports.index')} className="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900"><ArrowLeft className="h-4 w-4" /> Back to Reports</Link>
                    <h1 className="mt-1 text-xl font-semibold text-gray-900">{pageTitle}</h1>
                </div>
                {canExport && <Link href={route('reports.repository-preservation', { export: 'csv' })} className="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"><Download className="h-4 w-4" /> Export CSV</Link>}
            </div>
            <div className="mb-4 flex gap-3">
                <div className="rounded-lg border border-gray-200 bg-white px-4 py-3"><p className="text-xs text-gray-500">Total Entries</p><p className="text-lg font-bold text-gray-900">{total}</p></div>
                {Object.entries(by_status).map(([s, c]) => <div key={s} className="rounded-lg border border-gray-200 bg-white px-4 py-3"><p className="text-xs text-gray-500">{s}</p><p className="text-lg font-bold text-gray-900">{c}</p></div>)}
            </div>
            <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                {!rows.length ? <div className="py-12 text-center text-sm text-gray-400">No repository data available.</div> : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Title</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Finalized</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Updates</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Files</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {rows.map((r) => (
                                    <tr key={r.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-medium text-blue-600"><Link href={route('repository.show', r.id)} className="hover:underline">{r.title}</Link></td>
                                        <td className="px-4 py-3 text-sm"><span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">{r.status}</span></td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.project ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.finalized_at ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.update_count}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.file_count}</td>
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
