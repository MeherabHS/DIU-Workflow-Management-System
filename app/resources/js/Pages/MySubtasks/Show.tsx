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
import { Head, useForm } from '@inertiajs/react';

type ComparisonResult = {
    status: string;
    completion_percentage: number;
    summary: string | null;
    items: Array<{ requirement: string; status: string; matched_deliverable: string | null; notes: string }>;
    error_message: string | null;
    created_at: string | null;
};

export default function Show({ subtask, statuses = ['pending', 'in_progress', 'submitted'], action, closeHref, messages = [], canCreateMessage = false, messageStoreUrl, allowedMessageTypes = [], defaultMessageType = 'progress_note', files = [], canUploadFile = false, fileUploadUrl = null, allowedFileTypes, maxFileSizeMb = 10, fileSectionLabel = 'Evidence / Attachments', fileCategoryOptions = [], defaultFileCategory, fileUploadHelperText, comparisonResult = null, isComparisonConfigured = false, comparisonRunUrl = null, comparisonClearUrl = null }: { subtask: Subtask; statuses: string[]; action: string; closeHref?: string; messages?: any[]; files?: any[]; canUploadFile?: boolean; fileUploadUrl?: string | null; allowedFileTypes?: string; maxFileSizeMb?: number; fileSectionLabel?: string; fileCategoryOptions?: any[]; defaultFileCategory?: string; fileUploadHelperText?: string; canCreateMessage?: boolean; messageStoreUrl?: string | null; allowedMessageTypes?: any[]; defaultMessageType?: string; comparisonResult?: ComparisonResult | null; isComparisonConfigured?: boolean; comparisonRunUrl?: string | null; comparisonClearUrl?: string | null }) {
    const { data, setData, patch, processing, errors } = useForm({ status: subtask.status || 'pending', progress_note: subtask.progress_note || '' });

    function submit(event: React.FormEvent) { event.preventDefault(); patch(action); }

    const items = [
        { label: 'Project', value: subtask.project?.title || 'Not set' },
        { label: 'Task', value: subtask.task?.title || 'Not set' },
        { label: 'Status', value: <StatusBadge value={subtask.status} /> },
        { label: 'Priority', value: <PriorityBadge value={subtask.priority} /> },
        { label: 'Due Date', value: dateText(subtask.deadline) },
        { label: 'Assigned At', value: dateText(subtask.current_assigned_at) },
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Work Item Details" />
            <DetailModal title={subtask.title} description={subtask.description || 'Work Item Details'} onCloseHref={closeHref || route('my-work-items.index')}>
                <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">{items.map((item) => <div key={item.label}><p className="mb-1 text-xs font-medium text-gray-500">{item.label}</p><div className="text-sm font-semibold text-gray-950">{item.value}</div></div>)}</div>
                <FileList files={files} canUploadFile={canUploadFile} fileUploadUrl={fileUploadUrl} allowedFileTypes={allowedFileTypes} maxFileSizeMb={maxFileSizeMb} title={fileSectionLabel} fileCategoryOptions={fileCategoryOptions} defaultFileCategory={defaultFileCategory} fileUploadHelperText={fileUploadHelperText} />
                <RequirementDeliverableComparison isConfigured={isComparisonConfigured} result={comparisonResult} runUrl={comparisonRunUrl || ''} clearUrl={comparisonClearUrl || ''} />
                <ProgressComparison expected={['Review assignment', 'Update progress', 'Submit work']} completed={subtask.status === 'submitted' ? ['Review assignment', 'Update progress'] : []} />
                <MessageThread messages={messages} canCreateMessage={canCreateMessage} messageStoreUrl={messageStoreUrl} allowedMessageTypes={allowedMessageTypes} defaultMessageType={defaultMessageType} viewAllHref={route('subtasks.messages.index', subtask.id)} />
                <section className="mt-6 rounded-xl border border-gray-200 p-5"><h2 className="text-base font-semibold text-gray-950">Update Progress</h2><form onSubmit={submit} className="mt-4 space-y-4"><div><label className="text-sm font-semibold text-gray-900">Status</label><select value={data.status} onChange={(event) => setData('status', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">{statuses.map((status) => <option key={status} value={status}>{status}</option>)}</select>{errors.status && <p className="mt-1 text-sm text-red-600">{errors.status}</p>}</div><div><label className="text-sm font-semibold text-gray-900">Progress Note</label><textarea rows={5} value={data.progress_note} onChange={(event) => setData('progress_note', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500" />{errors.progress_note && <p className="mt-1 text-sm text-red-600">{errors.progress_note}</p>}</div><button disabled={processing} className="w-full rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700">Update Progress</button></form></section>
            </DetailModal>
        </AuthenticatedLayout>
    );
}



