import { Card, PageHeader, buttonClass } from '@/Components/Dius/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Project, Task } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Paperclip } from 'lucide-react';
import { useRef } from 'react';

export default function Form({ project, task, pageTitle, submitLabel, method, action, canAttachOnCreate = false, allowedFileTypes, maxFileSizeMb = 10 }: { project: Project; task: Task; statuses: string[]; pageTitle: string; submitLabel: string; method: 'post' | 'patch'; action: string; canAttachOnCreate?: boolean; allowedFileTypes?: string; maxFileSizeMb?: number }) {
    const inputRef = useRef<HTMLInputElement>(null);
    const { data, setData, post, patch, processing, errors } = useForm<{ title: string; description: string; status: string; priority: string; deadline: string; file: File | null }>({
        title: task.title || '',
        description: task.description || '',
        status: task.status || 'pending',
        priority: task.priority || 'medium',
        deadline: task.deadline?.slice(0, 10) || '',
        file: null,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        method === 'post' ? post(action, { forceFormData: true }) : patch(action);
    }

    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <PageHeader title={pageTitle} subtitle={`Project: ${project.title}`} />
            <Card className="p-5">
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="text-sm font-semibold text-gray-900">Task Title</label>
                        <input value={data.title} onChange={(event) => setData('title', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500" />
                        {errors.title && <p className="mt-1 text-sm text-red-600">{errors.title}</p>}
                    </div>
                    <div>
                        <label className="text-sm font-semibold text-gray-900">Description</label>
                        <textarea rows={4} value={data.description} onChange={(event) => setData('description', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500" />
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="text-sm font-semibold text-gray-900">Priority</label>
                            <select value={data.priority} onChange={(event) => setData('priority', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm">
                                <option>low</option><option>medium</option><option>high</option><option>urgent</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-sm font-semibold text-gray-900">Due Date</label>
                            <input type="date" value={data.deadline} onChange={(event) => setData('deadline', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm" />
                        </div>
                    </div>
                    {method === 'post' && canAttachOnCreate && (
                        <div className="rounded-lg border border-gray-200 p-3">
                            <input ref={inputRef} type="file" accept={allowedFileTypes} className="hidden" onChange={(event) => setData('file', event.target.files?.[0] || null)} />
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p className="text-sm font-semibold text-gray-900">Required Project Report / Attachment</p>
                                    <p className="text-xs text-gray-500">Maximum file size: {maxFileSizeMb}MB</p>
                                </div>
                                <button type="button" onClick={() => inputRef.current?.click()} className="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50">
                                    <Paperclip className="h-4 w-4" />
                                    {data.file ? data.file.name : 'Add Attachment'}
                                </button>
                            </div>
                            {errors.file && <p className="mt-2 text-sm text-red-600">{errors.file}</p>}
                        </div>
                    )}
                    <input type="hidden" value={data.status} />
                    <button disabled={processing} className={`${buttonClass.primary} w-full`}>{submitLabel}</button>
                </form>
            </Card>
        </AuthenticatedLayout>
    );
}

