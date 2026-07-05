import { router } from '@inertiajs/react';
import { FileText, Paperclip, Upload } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import FileCard, { WorkflowFile } from './FileCard';

type FileCategoryOption = {
    value: string;
    label: string;
};

type FileListProps = {
    files?: WorkflowFile[];
    canUploadFile?: boolean;
    fileUploadUrl?: string | null;
    allowedFileTypes?: string;
    maxFileSizeMb?: number;
    title?: string;
    fileCategoryOptions?: FileCategoryOption[];
    defaultFileCategory?: string;
    fileUploadHelperText?: string;
};

const fallbackFileCategoryOptions = [
    { value: 'attachment', label: 'Attachment' },
    { value: 'other', label: 'Other' },
];

export default function FileList({ files = [], canUploadFile = false, fileUploadUrl = null, allowedFileTypes, maxFileSizeMb = 100, title = 'Attachments', fileCategoryOptions = fallbackFileCategoryOptions, defaultFileCategory, fileUploadHelperText = 'AI comparison requires at least one Requirement file and one Deliverable/Evidence file.' }: FileListProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const categoryOptions = useMemo(() => fileCategoryOptions.length ? fileCategoryOptions : fallbackFileCategoryOptions, [fileCategoryOptions]);
    const initialCategory = defaultFileCategory && categoryOptions.some((option) => option.value === defaultFileCategory) ? defaultFileCategory : categoryOptions[0]?.value || 'attachment';
    const [fileCategory, setFileCategory] = useState(initialCategory);

    useEffect(() => {
        const nextCategory = defaultFileCategory && categoryOptions.some((option) => option.value === defaultFileCategory) ? defaultFileCategory : categoryOptions[0]?.value || 'attachment';
        setFileCategory((current) => categoryOptions.some((option) => option.value === current) ? current : nextCategory);
    }, [categoryOptions, defaultFileCategory]);

    function uploadFile(file: File | null | undefined) {
        if (!file || !fileUploadUrl) return;

        const formData = new FormData();
        formData.append('file', file);
        formData.append('file_category', fileCategory);

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
            <div className="mb-3 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <h2 className="inline-flex items-center gap-2 text-base font-semibold text-gray-950">
                    <Paperclip className="h-4 w-4" />
                    {title} ({files.length})
                </h2>
                {canUploadFile && fileUploadUrl && (
                    <>
                        <input ref={inputRef} type="file" accept={allowedFileTypes} className="hidden" onChange={(event) => uploadFile(event.target.files?.[0])} />
                        <div className="flex flex-col items-start gap-2 md:items-end">
                            <label className="flex items-center gap-2 text-xs font-medium text-gray-600">
                                File Category
                                <select
                                    value={fileCategory}
                                    onChange={(event) => setFileCategory(event.target.value)}
                                    className="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 focus:border-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                                >
                                    {categoryOptions.map((option) => (
                                        <option key={option.value} value={option.value}>{option.label}</option>
                                    ))}
                                </select>
                            </label>
                            <button type="button" disabled={uploading} onClick={() => inputRef.current?.click()} className="inline-flex items-center gap-1 text-sm font-semibold text-gray-900 hover:text-gray-600 disabled:cursor-not-allowed disabled:opacity-60">
                                <Upload className="h-3.5 w-3.5" />
                                {uploading ? 'Uploading' : 'Upload'}
                            </button>
                            <span className="max-w-xs text-left text-xs text-gray-500 md:text-right">{fileUploadHelperText} Maximum file size: {maxFileSizeMb}MB</span>
                        </div>
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
