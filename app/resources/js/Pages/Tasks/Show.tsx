import FileList from '@/Components/WorkManagement/FileList';
import DetailModal from '@/Components/WorkManagement/DetailModal';
import MessageThread from '@/Components/WorkManagement/MessageThread';
import PriorityBadge from '@/Components/WorkManagement/PriorityBadge';
import ProgressComparison from '@/Components/WorkManagement/ProgressComparison';
import RequirementDeliverableComparison from '@/Components/WorkManagement/RequirementDeliverableComparison';
import StatusBadge from '@/Components/WorkManagement/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { dateText } from '@/lib/utils';
import { BaseUser, Task } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';

function SummaryGrid({ task }: { task: Task }) {
    const items = [
        { label: 'Project', value: task.project?.title || 'Not set' },
        { label: 'Status', value: <StatusBadge value={task.status} /> },
        { label: 'Priority', value: <PriorityBadge value={task.priority} /> },
        { label: 'Deadline', value: dateText(task.deadline) },
        { label: 'Created By', value: task.creator?.name || 'Not set' },
    ];
    return <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">{items.map((item) => <div key={item.label} className="min-w-0"><p className="text-xs font-medium uppercase tracking-wide text-gray-500">{item.label}</p><div className="mt-1 break-words text-sm font-semibold text-gray-950">{item.value}</div></div>)}</div>;
}

type ComparisonResult = {
    status: string;
    completion_percentage: number;
    summary: string | null;
    items: Array<{ requirement: string; status: string; matched_deliverable: string | null; notes: string }>;
    error_message: string | null;
    created_at: string | null;
};

type Props = { task: Task; canCreateSubtask?: boolean; canAssignSubordinate?: boolean; canAssignSubordinateOnTask?: boolean; assignSubordinateAction?: string | null; assignableSubordinates?: BaseUser[]; assignedSubordinates?: BaseUser[]; canRevokeSubordinate?: boolean; canUpdateTask?: boolean; messages?: any[]; files?: any[]; canUploadFile?: boolean; fileUploadUrl?: string | null; allowedFileTypes?: string; maxFileSizeMb?: number; fileSectionLabel?: string; fileCategoryOptions?: any[]; defaultFileCategory?: string; fileUploadHelperText?: string; canCreateMessage?: boolean; messageStoreUrl?: string | null; allowedMessageTypes?: any[]; defaultMessageType?: string; canShowComparison?: boolean; comparisonResult?: ComparisonResult | null; isComparisonConfigured?: boolean; comparisonRunUrl?: string | null; comparisonClearUrl?: string | null };

export default function Show({ task, canCreateSubtask = false, canAssignSubordinate = false, canAssignSubordinateOnTask = false, assignSubordinateAction = null, assignableSubordinates = [], assignedSubordinates = [], canRevokeSubordinate = false, canUpdateTask = false, messages = [], canCreateMessage = false, messageStoreUrl, allowedMessageTypes = [], defaultMessageType = 'message', files = [], canUploadFile = false, fileUploadUrl = null, allowedFileTypes, maxFileSizeMb = 10, fileSectionLabel = 'Attachments', fileCategoryOptions = [], defaultFileCategory, fileUploadHelperText, canShowComparison = false, comparisonResult = null, isComparisonConfigured = false, comparisonRunUrl = null, comparisonClearUrl = null }: Props) {
    const { data, setData, post, processing, errors } = useForm({ subordinate_id: '' });

    function assignSubordinate(event: React.FormEvent) {
        event.preventDefault();
        if (assignSubordinateAction) {
            post(assignSubordinateAction);
        }
    }

    const hasAssignedSubordinate = assignedSubordinates.length > 0;

    return (
        <AuthenticatedLayout>
            <Head title="Task Details" />
            <DetailModal title={task.title} description={task.description || 'Task Details'} onCloseHref={task.project ? route('project.tasks.index', task.project.id) : route('dashboard')} actions={<>{canCreateSubtask && <Link href={route('tasks.subtasks.create', task.id)} className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50">Create Work Item</Link>}<a href="#work-items" className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50">View Work Items</a>{canUpdateTask && <Link href={route('tasks.edit', task.id)} className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50">Edit</Link>}</>}>
                <SummaryGrid task={task} />
                <section className="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <h2 className="text-base font-semibold text-gray-950">Assigned Subordinate</h2>
                    {hasAssignedSubordinate ? <p className="mt-1 text-sm text-gray-700">{assignedSubordinates.map((subordinate) => subordinate.name).join(', ')}</p> : <p className="mt-1 text-sm text-gray-500">No Subordinate assigned yet.</p>}
                    {canAssignSubordinateOnTask && !hasAssignedSubordinate && assignSubordinateAction && (
                        <form onSubmit={assignSubordinate} className="mt-3 flex flex-col gap-2 sm:flex-row">
                            <select value={data.subordinate_id} onChange={(event) => setData('subordinate_id', event.target.value)} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:max-w-xs">
                                <option value="">Assign later</option>
                                {assignableSubordinates.map((subordinate) => <option key={subordinate.id} value={subordinate.id}>{subordinate.name}</option>)}
                            </select>
                            <button type="submit" disabled={processing || !data.subordinate_id} className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-50">Assign Subordinate</button>
                            {errors.subordinate_id && <p className="text-sm text-red-600 sm:self-center">{errors.subordinate_id}</p>}
                        </form>
                    )}
                </section>
                <FileList files={files} canUploadFile={canUploadFile} fileUploadUrl={fileUploadUrl} allowedFileTypes={allowedFileTypes} maxFileSizeMb={maxFileSizeMb} title={fileSectionLabel} fileCategoryOptions={fileCategoryOptions} defaultFileCategory={defaultFileCategory} fileUploadHelperText={fileUploadHelperText} />
                {canShowComparison && <RequirementDeliverableComparison isConfigured={isComparisonConfigured} result={comparisonResult} runUrl={comparisonRunUrl || ''} clearUrl={comparisonClearUrl || ''} />}
                {canShowComparison && <ProgressComparison result={comparisonResult} />}
                <MessageThread messages={messages} canCreateMessage={canCreateMessage} messageStoreUrl={messageStoreUrl} allowedMessageTypes={allowedMessageTypes} defaultMessageType={defaultMessageType} viewAllHref={route('tasks.messages.index', task.id)} />
                <section id="work-items" className="mt-6"><h2 className="mb-3 text-base font-semibold text-gray-950">Assigned Work Items</h2><div className="space-y-2">{(task.subtasks || []).map((subtask) => {
                    const activeAssignments = (subtask.assignments || []).filter((assignment) => !assignment.revoked_at && assignment.subordinate);
                    const assignedNames = activeAssignments.map((assignment) => assignment.subordinate?.name).filter(Boolean).join(', ');

                    return <div key={subtask.id} className="flex flex-col gap-3 rounded-lg border border-gray-200 p-3 sm:flex-row sm:items-center sm:justify-between"><div className="min-w-0"><p className="break-words text-sm font-semibold text-gray-900">{subtask.title}</p><p className="mt-1 text-xs font-medium text-gray-500">{assignedNames ? `Assigned Subordinate: ${assignedNames}` : 'No Subordinate assigned'}</p><div className="mt-1 flex flex-wrap gap-2"><StatusBadge value={subtask.status} /><PriorityBadge value={subtask.priority} /></div></div><div className="flex shrink-0 flex-wrap gap-2"><Link href={route('subtasks.show', subtask.id)} className="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 shadow-sm hover:bg-gray-50">View Work Item</Link>{canAssignSubordinate && <Link href={route('subtasks.assign-subordinate.edit', subtask.id)} className="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 shadow-sm hover:bg-gray-50">Assign Subordinate</Link>}{canRevokeSubordinate && activeAssignments.map((assignment) => <Link key={assignment.id} href={route('subtasks.assign-subordinate.revoke', [subtask.id, assignment.subordinate?.id])} method="post" as="button" className="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 shadow-sm hover:bg-gray-50">Revoke Assignment</Link>)}</div></div>;
                })}{!task.subtasks?.length && <p className="rounded-lg border border-gray-200 p-3 text-sm text-gray-500">No work items found.</p>}</div></section>
            </DetailModal>
        </AuthenticatedLayout>
    );
}
