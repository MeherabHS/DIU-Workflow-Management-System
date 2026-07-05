import AssignmentChips from '@/Components/WorkManagement/AssignmentChips';
import FileList from '@/Components/WorkManagement/FileList';
import DetailModal from '@/Components/WorkManagement/DetailModal';
import MessageThread from '@/Components/WorkManagement/MessageThread';
import PriorityBadge from '@/Components/WorkManagement/PriorityBadge';
import ProgressComparison from '@/Components/WorkManagement/ProgressComparison';
import RequirementDeliverableComparison from '@/Components/WorkManagement/RequirementDeliverableComparison';
import StatusBadge from '@/Components/WorkManagement/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { dateText } from '@/lib/utils';
import { Subtask } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

type ComparisonResult = {
    status: string;
    completion_percentage: number;
    summary: string | null;
    items: Array<{ requirement: string; status: string; matched_deliverable: string | null; notes: string }>;
    error_message: string | null;
    created_at: string | null;
};

type Props = { subtask: Subtask; canAssignSubordinate?: boolean; canRevokeSubordinate?: boolean; canUpdateSubtask?: boolean; messages?: any[]; files?: any[]; canUploadFile?: boolean; fileUploadUrl?: string | null; allowedFileTypes?: string; maxFileSizeMb?: number; fileSectionLabel?: string; fileCategoryOptions?: any[]; defaultFileCategory?: string; fileUploadHelperText?: string; canCreateMessage?: boolean; messageStoreUrl?: string | null; allowedMessageTypes?: any[]; defaultMessageType?: string; comparisonResult?: ComparisonResult | null; isComparisonConfigured?: boolean; comparisonRunUrl?: string | null; comparisonClearUrl?: string | null };

export default function Show({ subtask, canAssignSubordinate = false, canRevokeSubordinate = false, canUpdateSubtask = false, messages = [], canCreateMessage = false, messageStoreUrl, allowedMessageTypes = [], defaultMessageType = 'message', files = [], canUploadFile = false, fileUploadUrl = null, allowedFileTypes, maxFileSizeMb = 10, fileSectionLabel = 'Evidence / Attachments', fileCategoryOptions = [], defaultFileCategory, fileUploadHelperText, comparisonResult = null, isComparisonConfigured = false, comparisonRunUrl = null, comparisonClearUrl = null }: Props) {
    const activeAssignments = (subtask.assignments || []).filter((assignment) => !assignment.revoked_at);
    const assignedUsers = activeAssignments.map((assignment) => assignment.subordinate).filter(Boolean) as Array<{ id: number; name: string; email?: string }>;
    const items = [
        { label: 'Project', value: subtask.project?.title || 'Not set' },
        { label: 'Task', value: subtask.task?.title || 'Not set' },
        { label: 'Status', value: <StatusBadge value={subtask.status} /> },
        { label: 'Priority', value: <PriorityBadge value={subtask.priority} /> },
        { label: 'Due Date', value: dateText(subtask.deadline) },
        { label: 'Created By', value: subtask.creator?.name || 'Not set' },
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Work Item Details" />
            <DetailModal title={subtask.title} description={subtask.description || 'Work Item Details'} onCloseHref={subtask.task ? route('tasks.show', subtask.task.id) : route('dashboard')} actions={<>{canAssignSubordinate && <Link href={route('subtasks.assign-subordinate.edit', subtask.id)} className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700">Assign Subordinate</Link>}{canUpdateSubtask && <Link href={route('subtasks.edit', subtask.id)} className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50">Edit Work Item</Link>}</>}>
                <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">{items.map((item) => <div key={item.label} className="min-w-0"><p className="text-xs font-medium uppercase tracking-wide text-gray-500">{item.label}</p><div className="mt-1 break-words text-sm font-semibold text-gray-950">{item.value}</div></div>)}</div>
                <section className="mb-6"><div className="mb-3 flex items-center justify-between"><h2 className="text-base font-semibold text-gray-950">Assigned To</h2>{canAssignSubordinate && <Link href={route('subtasks.assign-subordinate.edit', subtask.id)} className="text-sm font-semibold text-gray-900">Assign Subordinate</Link>}</div><AssignmentChips users={assignedUsers} /></section>
                <FileList files={files} canUploadFile={canUploadFile} fileUploadUrl={fileUploadUrl} allowedFileTypes={allowedFileTypes} maxFileSizeMb={maxFileSizeMb} title={fileSectionLabel} fileCategoryOptions={fileCategoryOptions} defaultFileCategory={defaultFileCategory} fileUploadHelperText={fileUploadHelperText} />
                <RequirementDeliverableComparison isConfigured={isComparisonConfigured} result={comparisonResult} runUrl={comparisonRunUrl || ''} clearUrl={comparisonClearUrl || ''} />
                <ProgressComparison expected={['Assignment', 'Progress update', 'Submission']} completed={subtask.status === 'completed' || subtask.status === 'approved' ? ['Assignment', 'Progress update', 'Submission'] : []} />
                <MessageThread messages={messages} canCreateMessage={canCreateMessage} messageStoreUrl={messageStoreUrl} allowedMessageTypes={allowedMessageTypes} defaultMessageType={defaultMessageType} viewAllHref={route('subtasks.messages.index', subtask.id)} />
                <section className="mt-6"><h2 className="mb-3 text-base font-semibold text-gray-950">Assignment History</h2><div className="divide-y divide-gray-100 rounded-lg border border-gray-200">{(subtask.assignments || []).map((assignment) => <div key={assignment.id} className="grid gap-2 p-3 text-sm md:grid-cols-4"><span className="font-semibold">{assignment.subordinate?.name || 'Unknown'}</span><span>Assigned by {assignment.assigner?.name || 'Unknown'}</span><span>{dateText(assignment.assigned_at)}</span><span>{assignment.revoked_at ? `Revoked ${dateText(assignment.revoked_at)}` : 'Active'}</span></div>)}{!subtask.assignments?.length && <p className="p-3 text-sm text-gray-500">No assignment history found.</p>}</div></section>
                {canRevokeSubordinate && activeAssignments.length > 0 && <div className="mt-6 flex flex-wrap gap-2">{activeAssignments.map((assignment) => assignment.subordinate && <button key={assignment.id} type="button" onClick={() => router.post(route('subtasks.assign-subordinate.revoke', [subtask.id, assignment.subordinate?.id]))} className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-600">Revoke Assignment</button>)}</div>}
            </DetailModal>
        </AuthenticatedLayout>
    );
}



