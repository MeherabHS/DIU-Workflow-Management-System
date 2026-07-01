import { ActionLink, Card, DetailGrid, PageHeader, StatusPill, buttonClass } from '@/Components/Dius/ui';
import FileList from '@/Components/WorkManagement/FileList';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { dateText, humanize } from '@/lib/utils';
import { RepositoryEntry } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Show({ entry, statuses = [], files = [], canUploadFile = false, fileUploadUrl = null, allowedFileTypes, maxFileSizeMb = 10, fileSectionLabel = 'Attachments' }: { entry: RepositoryEntry; statuses: string[]; files?: any[]; canUploadFile?: boolean; fileUploadUrl?: string | null; allowedFileTypes?: string; maxFileSizeMb?: number; fileSectionLabel?: string }) {
    const { data, setData, post, processing, errors } = useForm({ update_type: 'status_update', new_status: '', note: '' });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post(route('repository.updates.store', entry.id));
    }

    return (
        <AuthenticatedLayout>
            <Head title="Repository Details" />
            <PageHeader title="Repository Details" subtitle={entry.title} action={<>{entry.project && <Link href={route('projects.show', entry.project.id)} className="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 shadow-sm hover:bg-gray-50">View Source Project</Link>}<ActionLink href={route('repository.edit', entry.id)}>Edit</ActionLink></>} />
            <DetailGrid items={[
                { label: 'Type', value: entry.type || 'Not set' },
                { label: 'Department', value: entry.department?.name || 'Not set' },
                { label: 'Client/Office', value: entry.client_or_office || 'Not set' },
                { label: 'Status', value: <StatusPill value={entry.status} /> },
                { label: 'Deadline', value: dateText(entry.deadline) },
                { label: 'Responsible Person', value: entry.responsible_user?.name || entry.responsibleUser?.name || 'Not set' },
                { label: 'Created By', value: entry.creator?.name || 'Not set' },
            ]} />
            <FileList files={files} canUploadFile={canUploadFile} fileUploadUrl={fileUploadUrl} allowedFileTypes={allowedFileTypes} maxFileSizeMb={maxFileSizeMb} title={fileSectionLabel} />
            <Card className="mt-5 p-5">
                <h2 className="text-base font-bold text-gray-950">Timeline Updates</h2>
                <div className="mt-4 divide-y divide-gray-100">
                    {(entry.updates || []).map((update) => (
                        <div key={update.id} className="py-3 text-sm">
                            <div className="font-semibold text-gray-950">{humanize(update.update_type)} {update.new_status ? `to ${humanize(update.new_status)}` : ''}</div>
                            <div className="mt-1 text-gray-500">{update.note || 'No note'} - {update.user?.name || 'Unknown'}</div>
                        </div>
                    ))}
                    {!entry.updates?.length && <p className="py-3 text-sm text-gray-500">No timeline updates found.</p>}
                </div>
            </Card>
            {entry.finalized_at && (
                <Card className="mt-5 p-5">
                    <h2 className="text-base font-bold text-gray-950">Project Finalization</h2>
                    <div className="mt-4 grid gap-3 text-sm md:grid-cols-2">
                        <div><span className="font-semibold text-gray-900">Finalized At:</span> <span className="text-gray-600">{dateText(entry.finalized_at)}</span></div>
                        <div><span className="font-semibold text-gray-900">Finalized By:</span> <span className="text-gray-600">{entry.finalized_by_name || 'Unknown'}</span></div>
                    </div>
                    {entry.final_status_snapshot && (() => {
                        const snap = entry.final_status_snapshot!;
                        const proj = snap.project as Record<string, string> | undefined;
                        const coord = snap.active_coordinator ? String(snap.active_coordinator) : null;
                        const taskCount = String(snap.task_count ?? 0);
                        const approvedTaskCount = String(snap.approved_task_count ?? 0);
                        const workItemCount = String(snap.work_item_count ?? 0);
                        const approvedWorkItemCount = String(snap.approved_work_item_count ?? 0);
                        const fileCount = String(snap.file_count ?? 0);
                        const aiSummary = snap.ai_comparison_summary ? String(snap.ai_comparison_summary) : null;
                        return (
                        <div className="mt-4 rounded-lg bg-gray-50 p-4 text-sm">
                            <h3 className="mb-2 font-semibold text-gray-900">Final Status Snapshot</h3>
                            <div className="grid gap-2 md:grid-cols-2">
                                {proj && (
                                    <div className="space-y-1">
                                        <p><span className="font-medium text-gray-700">Project:</span> {String(proj.title || '')}</p>
                                        <p><span className="font-medium text-gray-700">Status:</span> {String(proj.status || '')}</p>
                                        {proj.priority && <p><span className="font-medium text-gray-700">Priority:</span> {String(proj.priority)}</p>}
                                        {proj.deadline && <p><span className="font-medium text-gray-700">Deadline:</span> {String(proj.deadline)}</p>}
                                    </div>
                                )}
                                <div className="space-y-1">
                                    {coord && <p><span className="font-medium text-gray-700">Coordinator:</span> {coord}</p>}
                                    <p><span className="font-medium text-gray-700">Tasks:</span> {taskCount} ({approvedTaskCount} approved)</p>
                                    <p><span className="font-medium text-gray-700">Work Items:</span> {workItemCount} ({approvedWorkItemCount} approved)</p>
                                    <p><span className="font-medium text-gray-700">Files:</span> {fileCount}</p>
                                </div>
                            </div>
                            {aiSummary && (
                                <p className="mt-3"><span className="font-medium text-gray-700">AI Comparison:</span> {aiSummary}</p>
                            )}
                        </div>
                        );
                    })()}
                </Card>
            )}
            <Card className="mt-5 p-5">
                <h2 className="text-base font-bold text-gray-950">Add Timeline Update</h2>
                <form onSubmit={submit} className="mt-4 space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div><label className="text-sm font-semibold">Update Type</label><input value={data.update_type} onChange={(event) => setData('update_type', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm" /></div>
                        <div><label className="text-sm font-semibold">New Status</label><select value={data.new_status} onChange={(event) => setData('new_status', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm"><option value="">No status change</option>{statuses.map((status) => <option key={status} value={status}>{status}</option>)}</select></div>
                    </div>
                    <div><label className="text-sm font-semibold">Note</label><textarea rows={4} value={data.note} onChange={(event) => setData('note', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm" />{errors.note && <p className="text-sm text-red-600">{errors.note}</p>}</div>
                    <button disabled={processing} className={buttonClass.primary}>Add Update</button>
                </form>
            </Card>
        </AuthenticatedLayout>
    );
}
