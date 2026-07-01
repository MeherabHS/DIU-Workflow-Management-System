import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Download, ArrowLeft } from 'lucide-react';

type ItemRow = { id: number; title: string; status: string | null; priority?: string | null; project?: string | null; deadline?: string | null; subtask_count?: number; task?: string | null };
type ReportData = { total: number; by_status: Record<string, number>; rows: ItemRow[] };

export default function TaskStatus({
    tasks,
    work_items,
    canExport,
    pageTitle = 'Task / Work Item Status Report',
}: {
    tasks: ReportData;
    work_items: ReportData;
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
                    <Link href={route('reports.task-status', { export: 'csv' })} className="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        <Download className="h-4 w-4" /> Export CSV
                    </Link>
                )}
            </div>

            {/* Tasks Section */}
            <div className="mb-6 rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="border-b border-gray-100 px-5 py-3">
                    <h3 className="text-sm font-semibold text-gray-900">Tasks ({tasks.total})</h3>
                </div>
                {!tasks.rows.length ? (
                    <div className="py-8 text-center text-sm text-gray-400">No task data available.</div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Title</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Deadline</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {tasks.rows.map((r) => (
                                    <tr key={r.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-medium text-blue-600"><Link href={route('tasks.show', r.id)} className="hover:underline">{r.title}</Link></td>
                                        <td className="px-4 py-3 text-sm"><span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">{r.status}</span></td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.project ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.deadline ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Work Items Section */}
            <div className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="border-b border-gray-100 px-5 py-3">
                    <h3 className="text-sm font-semibold text-gray-900">Work Items ({work_items.total})</h3>
                </div>
                {!work_items.rows.length ? (
                    <div className="py-8 text-center text-sm text-gray-400">No work item data available.</div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-100">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Title</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Project</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Deadline</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {work_items.rows.map((r) => (
                                    <tr key={r.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-medium text-blue-600"><Link href={route('subtasks.mine.show', r.id)} className="hover:underline">{r.title}</Link></td>
                                        <td className="px-4 py-3 text-sm"><span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">{r.status}</span></td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{r.project ?? '—'}</td>
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
