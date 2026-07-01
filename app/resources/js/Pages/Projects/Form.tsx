import { Card, PageHeader, buttonClass } from '@/Components/Dius/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Department, Project } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Paperclip } from 'lucide-react';
import { useRef } from 'react';

export default function Form({ project, departments = [], statuses = [], pageTitle, submitLabel, method, action }: { project: Project; departments: Department[]; statuses: string[]; pageTitle: string; submitLabel: string; method: 'post' | 'patch'; action: string }) {
    const inputRef = useRef<HTMLInputElement>(null);
    const { data, setData, post, patch, processing, errors } = useForm<{ title: string; description: string; department_id: string; status: string; priority: string; start_date: string; deadline: string; file: File | null }>({
        title: project.title || '',
        description: project.description || '',
        department_id: String(project.department?.id || ''),
        status: project.status || 'planned',
        priority: project.priority || 'medium',
        start_date: project.start_date?.slice(0, 10) || '',
        deadline: project.deadline?.slice(0, 10) || '',
        file: null,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        method === 'post' ? post(action, { forceFormData: true }) : patch(action);
    }

    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <PageHeader title={pageTitle} subtitle="Project ownership and delivery setup." />
            <Card className="p-5">
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="text-sm font-semibold">Title</label>
                        <input value={data.title} onChange={(event) => setData('title', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500" />
                        {errors.title && <p className="text-sm text-red-600">{errors.title}</p>}
                    </div>
                    <div>
                        <label className="text-sm font-semibold">Description</label>
                        <textarea value={data.description} onChange={(event) => setData('description', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500" rows={4} />
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="text-sm font-semibold">Department</label>
                            <select value={data.department_id} onChange={(event) => setData('department_id', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm"><option value="">Select department</option>{departments.map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}</select>
                        </div>
                        <div>
                            <label className="text-sm font-semibold">Status</label>
                            <select value={data.status} onChange={(event) => setData('status', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm">{statuses.map((status) => <option key={status} value={status}>{status}</option>)}</select>
                        </div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <label className="text-sm font-semibold">Priority</label>
                            <select value={data.priority} onChange={(event) => setData('priority', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm"><option>low</option><option>medium</option><option>high</option><option>urgent</option></select>
                        </div>
                        <div>
                            <label className="text-sm font-semibold">Start Date</label>
                            <input type="date" value={data.start_date} onChange={(event) => setData('start_date', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm" />
                        </div>
                        <div>
                            <label className="text-sm font-semibold">Deadline</label>
                            <input type="date" value={data.deadline} onChange={(event) => setData('deadline', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm" />
                        </div>
                    </div>
                    {method === 'post' && (
                        <div className="rounded-lg border border-gray-200 p-3">
                            <input ref={inputRef} type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.csv,.zip" className="hidden" onChange={(event) => setData('file', event.target.files?.[0] || null)} />
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p className="text-sm font-semibold text-gray-900">Required Documents / Attachments</p>
                                    <p className="text-xs text-gray-500">Optional setup file. Max 10 MB.</p>
                                </div>
                                <button type="button" onClick={() => inputRef.current?.click()} className="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50">
                                    <Paperclip className="h-4 w-4" />
                                    {data.file ? data.file.name : 'Add Attachment'}
                                </button>
                            </div>
                            {errors.file && <p className="mt-2 text-sm text-red-600">{errors.file}</p>}
                        </div>
                    )}
                    <button disabled={processing} className={`${buttonClass.primary} w-full`}>{submitLabel}</button>
                </form>
            </Card>
        </AuthenticatedLayout>
    );
}
