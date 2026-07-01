import { router } from '@inertiajs/react';
import { FileText, Paperclip, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import FileCard, { WorkflowFile } from './FileCard';

type FileListProps = {
    files?: WorkflowFile[];
    canUploadFile?: boolean;
    fileUploadUrl?: string | null;
    allowedFileTypes?: string;
    maxFileSizeMb?: number;
    title?: string;
};

export default function FileList({ files = [], canUploadFile = false, fileUploadUrl = null, allowedFileTypes, title = 'Attachments' }: FileListProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    function uploadFile(file: File | null | undefined) {
        if (!file || !fileUploadUrl) return;

        const formData = new FormData();
        formData.append('file', file);

        setUploading(true);
        router.post(fileUploadUrl, formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setUploading(false);
                if (inputRef.current) inputRef.current.value = '';
            },
        });
    }

    return (
        <section className="mt-6">
            <div className="mb-3 flex items-center justify-between gap-3">
                <h2 className="inline-flex items-center gap-2 text-base font-semibold text-gray-950">
                    <Paperclip className="h-4 w-4" />
                    {title} ({files.length})
                </h2>
                {canUploadFile && fileUploadUrl && (
                    <>
                        <input ref={inputRef} type="file" accept={allowedFileTypes} className="hidden" onChange={(event) => uploadFile(event.target.files?.[0])} />
                        <button type="button" disabled={uploading} onClick={() => inputRef.current?.click()} className="inline-flex items-center gap-1 text-sm font-semibold text-gray-900 hover:text-gray-600 disabled:cursor-not-allowed disabled:opacity-60">
                            <Upload className="h-3.5 w-3.5" />
                            {uploading ? 'Uploading' : 'Upload'}
                        </button>
                    </>
                )}
            </div>
            <div className="grid gap-2 md:grid-cols-2">
                {files.length > 0 ? (
                    files.map((file) => <FileCard key={file.id} file={file} />)
                ) : (
                    <div className="flex items-center gap-3 rounded-lg border border-gray-200 p-3 text-sm text-gray-500">
                        <FileText className="h-8 w-8 shrink-0 text-gray-900" />
                        <div>
                            <p className="font-medium text-gray-700">No attachments yet</p>
                            <p className="text-xs text-gray-500">0 files</p>
                        </div>
                    </div>
                )}
            </div>
        </section>
    );
}
