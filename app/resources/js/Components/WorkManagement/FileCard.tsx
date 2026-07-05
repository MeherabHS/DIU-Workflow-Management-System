import { router } from '@inertiajs/react';
import { Download, FileText, Trash2 } from 'lucide-react';

export type WorkflowFile = {
    id: number;
    original_name: string;
    size_human: string;
    mime_type?: string | null;
    file_category?: string | null;
    uploaded_by_name: string;
    uploaded_at?: string | null;
    download_url: string;
    can_delete?: boolean;
    delete_url?: string | null;
};

function metaText(file: WorkflowFile) {
    return [file.size_human, file.uploaded_by_name ? `Uploaded by ${file.uploaded_by_name}` : null].filter(Boolean).join(' - ');
}

function categoryLabel(category?: string | null) {
    const labels: Record<string, string> = {
        requirement: 'Requirement',
        follow_up: 'Follow-up',
        deliverable: 'Deliverable',
        evidence: 'Evidence',
        other: 'Other',
        attachment: 'Attachment',
        reference: 'Reference',
        repository_document: 'Repository',
        feedback_attachment: 'Feedback',
    };

    return labels[category || 'attachment'] ?? 'Attachment';
}

export default function FileCard({ file }: { file: WorkflowFile }) {
    function destroy() {
        if (!file.delete_url) return;
        router.delete(file.delete_url, { preserveScroll: true });
    }

    return (
        <div className="flex items-center gap-3 rounded-lg border border-gray-200 p-3 hover:bg-gray-50">
            <FileText className="h-8 w-8 shrink-0 text-gray-900" />
            <div className="min-w-0 flex-1">
                <div className="flex min-w-0 flex-wrap items-center gap-2">
                    <p className="truncate text-sm font-medium text-gray-900" title={file.original_name}>{file.original_name}</p>
                    <span className="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700">{categoryLabel(file.file_category)}</span>
                </div>
                <p className="truncate text-xs text-gray-500">{metaText(file)}</p>
            </div>
            <div className="flex shrink-0 items-center gap-1">
                <a href={file.download_url} className="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-700 hover:bg-gray-100" title="Download">
                    <Download className="h-4 w-4" />
                </a>
                {file.can_delete && file.delete_url && (
                    <button type="button" onClick={destroy} className="inline-flex h-8 w-8 items-center justify-center rounded-md text-red-700 hover:bg-red-50" title="Delete">
                        <Trash2 className="h-4 w-4" />
                    </button>
                )}
            </div>
        </div>
    );
}


