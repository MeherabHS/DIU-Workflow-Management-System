import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Download, ArrowLeft } from 'lucide-react';

type Row = { id: number; title: string; status: string | null; priority?: string | null; department?: string | null; coordinator?: string | null; deadline?: string | null; task_count: number; completed_task_count: number; work_item_count: number; completed_work_item_count: number };

export default function ProjectProgress({
    total,
    by_status,
    rows,
    canExport,
    pageTitle = 'Project Progress Report',
}: {
    total: number;
    by_status: Record<string, number>;
    rows: Row[];
    canExport: boolean;
    pageTitle?: string;
}) {
    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <Link href={route('reports.index')} className="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900">
                        <ArrowLeft className="h-4 w-4" /> Back to Reports
                    </Link>
                    <h1 className="mt-1 text-xl font-semibold text-gray-900">{pageTitle}</h1>
                </div>
                {canExport && (
                    <Link href={route('reports.project-progress', { export: 'csv' })} className="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        <Download className="h-4 w-4" /> Export CSV
                    </Link>
                )}
            </div>

            {/* Summary */}
            <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-lg border border-gray-200 bg-white px-4 py-3">
                    <p className="text-xs text-gray-500">Total Projects</p>
                    <p className="text-lg font-bold text-gray-900">{total}</p>
                </div>
                {Object.entries(by_status).map(([status, count]) => (
                    <div key={status} className="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <p className="text-xs text-gray-500">{status}</p>
                        <p className="text-lg font-bold text-gray-900">{count}</p>
                    </div>
                ))}
            </div>

            {/* Table */}
            <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                {!rows.length ? (
                    <div className="py-12 text-center text-sm text-gray-400">No project data available.</div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Title</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Department</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Coordinator</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tasks</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Work Items</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Deadline</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {rows.map((r) => (
                                    <tr key={r.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-medium text-blue-600">
                                            <Link href={route('projects.show', r.id)} className="hover:underline">{r.title}</Link>
                                        </td>
                                        <td className="px-4 py-3 text-sm"><span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">{r.status}</span></td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.department ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.coordinator ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.completed_task_count}/{r.task_count}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.completed_work_item_count}/{r.work_item_count}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.deadline ?? '—'}</td>
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
