import { FileText, Upload } from 'lucide-react';

export default function AttachmentList({ files = [], uploadLabel = 'Upload' }: { files?: Array<{ id?: number | string; name: string; size?: string; uploadedBy?: string }>; uploadLabel?: string }) {
    const displayFiles = files.length ? files : [{ id: 'placeholder', name: 'No attachments yet', size: '0 files', uploadedBy: 'Placeholder only' }];

    return (
        <section className="mt-6">
            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-base font-semibold text-gray-950">Attachments ({files.length})</h2>
                <button type="button" className="inline-flex items-center gap-1 text-sm font-semibold text-gray-900"><Upload className="h-3.5 w-3.5" />{uploadLabel}</button>
            </div>
            <div className="grid gap-2 md:grid-cols-2">
                {displayFiles.map((file) => (
                    <div key={file.id || file.name} className="flex items-center gap-3 rounded-lg border border-gray-200 p-3">
                        <FileText className="h-8 w-8 text-gray-900" />
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-semibold text-gray-900">{file.name}</p>
                            <p className="text-xs text-gray-500">{file.size || 'Unknown size'} {file.uploadedBy ? `- ${file.uploadedBy}` : ''}</p>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}
