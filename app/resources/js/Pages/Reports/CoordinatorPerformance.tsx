import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Download, ArrowLeft } from 'lucide-react';

type Row = { id: number; name: string; email: string; active_projects: number; total_tasks: number; completed_tasks: number; task_completion_rate: number; total_work_items: number; completed_work_items: number; work_item_completion_rate: number };

export default function CoordinatorPerformance({ rows, canExport, pageTitle = 'Coordinator Performance Report' }: { rows: Row[]; canExport: boolean; pageTitle?: string }) {
    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <Link href={route('reports.index')} className="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900"><ArrowLeft className="h-4 w-4" /> Back to Reports</Link>
                    <h1 className="mt-1 text-xl font-semibold text-gray-900">{pageTitle}</h1>
                </div>
                {canExport && <Link href={route('reports.coordinator-performance', { export: 'csv' })} className="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"><Download className="h-4 w-4" /> Export CSV</Link>}
            </div>
            <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                {!rows.length ? <div className="py-12 text-center text-sm text-gray-400">No coordinator data available.</div> : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Name</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Active Projects</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tasks</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Task Rate</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Work Items</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Work Item Rate</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {rows.map((r) => (
                                    <tr key={r.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3"><div className="text-sm font-medium text-gray-900">{r.name}</div><div className="text-xs text-gray-500">{r.email}</div></td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.active_projects}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.completed_tasks}/{r.total_tasks}</td>
                                        <td className="px-4 py-3 text-sm"><span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">{r.task_completion_rate}%</span></td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.completed_work_items}/{r.total_work_items}</td>
                                        <td className="px-4 py-3 text-sm"><span className="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">{r.work_item_completion_rate}%</span></td>
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
