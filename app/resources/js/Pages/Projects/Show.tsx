import AssignmentChips from '@/Components/WorkManagement/AssignmentChips';
import FileList from '@/Components/WorkManagement/FileList';
import DetailModal from '@/Components/WorkManagement/DetailModal';
import MessageThread from '@/Components/WorkManagement/MessageThread';
import PriorityBadge from '@/Components/WorkManagement/PriorityBadge';
import RequirementDeliverableComparison from '@/Components/WorkManagement/RequirementDeliverableComparison';
import StatusBadge from '@/Components/WorkManagement/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { dateText } from '@/lib/utils';
import { BaseUser, Project } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';

type ComparisonResult = {
    status: string;
    completion_percentage: number;
    summary: string | null;
    items: Array<{ requirement: string; status: string; matched_deliverable: string | null; notes: string }>;
    error_message: string | null;
    created_at: string | null;
};

type Props = {
    project: Project;
    pageTitle?: string;
    canViewTasks?: boolean;
    canCreateTask?: boolean;
    canAssignCoordinator?: boolean;
    canUpdateProject?: boolean;
    canFinalizeProject?: boolean;
    canSubmitForReview?: boolean;
    submitForReviewUrl?: string | null;
    alreadyFinalized?: { id: number; route: string; finalized_at: string; finalized_by: string } | null;
    closeHref?: string;
    messages?: any[]; files?: any[]; canUploadFile?: boolean; fileUploadUrl?: string | null; allowedFileTypes?: string; maxFileSizeMb?: number; fileSectionLabel?: string; fileCategoryOptions?: any[]; defaultFileCategory?: string; fileUploadHelperText?: string;
    canCreateMessage?: boolean;
    messageStoreUrl?: string | null;
    allowedMessageTypes?: any[]; defaultMessageType?: string;
    comparisonResult?: ComparisonResult | null;
    isComparisonConfigured?: boolean;
    comparisonRunUrl?: string | null;
    comparisonClearUrl?: string | null;
};

export default function Show({ project, pageTitle = 'Project Details', canViewTasks = false, canCreateTask = false, canAssignCoordinator = false, canUpdateProject = false, canFinalizeProject = false, canSubmitForReview = false, submitForReviewUrl = null, alreadyFinalized = null, closeHref, messages = [], canCreateMessage = false, messageStoreUrl, allowedMessageTypes = [], defaultMessageType = 'message', files = [], canUploadFile = false, fileUploadUrl = null, allowedFileTypes, maxFileSizeMb = 10, fileSectionLabel = 'Attachments', fileCategoryOptions = [], defaultFileCategory, fileUploadHelperText, comparisonResult = null, isComparisonConfigured = false, comparisonRunUrl = null, comparisonClearUrl = null }: Props) {
    const { post: finalizePost, processing: finalizing } = useForm({});
    const { post: submitPost, processing: submittingForReview } = useForm({});

    function handleFinalize() {
        if (confirm('Finalize this project to the Repository? This action cannot be undone.')) {
            finalizePost(route('projects.finalize-to-repository', project.id));
        }
    }

    function handleSubmitForReview() {
        if (submitForReviewUrl) {
            submitPost(submitForReviewUrl);
        }
    }

    const coordinator = project.active_primary_assignment?.coordinator || project.activePrimaryAssignment?.coordinator || null;
    const users = coordinator ? [coordinator] as BaseUser[] : [];
    const items = [
        { label: 'Department', value: project.department?.name || 'Not set' },
        { label: 'Status', value: <StatusBadge value={project.status} /> },
        { label: 'Priority', value: <PriorityBadge value={project.priority} /> },
        { label: 'Start Date', value: dateText(project.start_date) },
        { label: 'Deadline', value: dateText(project.deadline) },
        ...(project.submitted_at ? [{ label: 'Submitted', value: dateText(project.submitted_at) }] : []),
        { label: 'Creator', value: project.creator?.name || 'Not set' },
        { label: 'Active Coordinator', value: coordinator?.name || 'Unassigned' },
    ];

    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <DetailModal title={project.title} description={project.description || 'Project Details'} onCloseHref={closeHref || route('dashboard')} actions={<>{canViewTasks && <Link href={route('project.tasks.index', project.id)} className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700">View Tasks</Link>}{canCreateTask && <Link href={route('project.tasks.create', project.id)} className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700">Create Task</Link>}{canUpdateProject && <Link href={route('projects.edit', project.id)} className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50">Edit</Link>}{canAssignCoordinator && <Link href={route('projects.assign-coordinator.edit', project.id)} className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50">Assign Coordinator</Link>}{canSubmitForReview && <button type="button" onClick={handleSubmitForReview} disabled={submittingForReview} className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-900 shadow-sm hover:bg-amber-100 disabled:opacity-50 disabled:cursor-not-allowed">{submittingForReview ? 'Submitting...' : 'Submit for Review'}</button>}{alreadyFinalized && <Link href={alreadyFinalized.route} className="rounded-lg border border-green-300 bg-green-50 px-3 py-2 text-sm font-semibold text-green-800 shadow-sm hover:bg-green-100">Finalized in Repository</Link>}{canFinalizeProject && !alreadyFinalized && <button type="button" onClick={handleFinalize} disabled={finalizing} className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">{finalizing ? 'Finalizing...' : 'Finalize to Repository'}</button>}</>}>
                <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                    {items.map((item) => <div key={item.label} className="min-w-0"><p className="text-xs font-medium uppercase tracking-wide text-gray-500">{item.label}</p><div className="mt-1 break-words text-sm font-semibold text-gray-950">{item.value}</div></div>)}
                </div>
                <section className="mb-6"><div className="mb-3 flex items-center justify-between"><h2 className="text-base font-semibold text-gray-950">Assigned To</h2>{canAssignCoordinator && <Link href={route('projects.assign-coordinator.edit', project.id)} className="text-sm font-semibold text-gray-900">Assign Coordinator</Link>}</div><AssignmentChips users={users} /></section>
                <FileList files={files} canUploadFile={canUploadFile} fileUploadUrl={fileUploadUrl} allowedFileTypes={allowedFileTypes} maxFileSizeMb={maxFileSizeMb} title={fileSectionLabel} fileCategoryOptions={fileCategoryOptions} defaultFileCategory={defaultFileCategory} fileUploadHelperText={fileUploadHelperText} />
                <RequirementDeliverableComparison isConfigured={isComparisonConfigured} result={comparisonResult} runUrl={comparisonRunUrl || ''} clearUrl={comparisonClearUrl || ''} />
                <MessageThread messages={messages} canCreateMessage={canCreateMessage} messageStoreUrl={messageStoreUrl} allowedMessageTypes={allowedMessageTypes} defaultMessageType={defaultMessageType} viewAllHref={route('projects.messages.index', project.id)} />
                <section className="mt-6"><h2 className="mb-3 text-base font-semibold text-gray-950">Assignment History</h2><div className="divide-y divide-gray-100 rounded-lg border border-gray-200">{(project.assignments || []).map((assignment) => <div key={assignment.id} className="grid gap-2 p-3 text-sm md:grid-cols-4"><span className="font-semibold">{assignment.coordinator?.name || 'Unknown'}</span><span>Assigned by {assignment.assigner?.name || 'Unknown'}</span><span>{dateText(assignment.assigned_at)}</span><span>{assignment.revoked_at ? `Revoked ${dateText(assignment.revoked_at)}` : 'Active'}</span></div>)}{!project.assignments?.length && <p className="p-3 text-sm text-gray-500">No assignment history found.</p>}</div></section>
            </DetailModal>
        </AuthenticatedLayout>
    );
}


