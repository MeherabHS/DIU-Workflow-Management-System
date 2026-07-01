import { Card, PageHeader, buttonClass } from '@/Components/Dius/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { BaseUser, Project, Subtask, Task } from '@/types';
import { Head, useForm } from '@inertiajs/react';

export default function Form({ project, task, subtask, assignableSubordinates = [], statuses = [], pageTitle, submitLabel, method, action }: { project: Project; task: Task; subtask: Subtask; assignableSubordinates?: BaseUser[]; statuses: string[]; pageTitle: string; submitLabel: string; method: 'post' | 'patch'; action: string }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        title: subtask.title || '',
        description: subtask.description || '',
        status: subtask.status || 'pending',
        priority: subtask.priority || 'medium',
        deadline: subtask.deadline?.slice(0, 10) || '',
        subordinate_id: '',
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        method === 'post' ? post(action) : patch(action);
    }

    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <PageHeader title={pageTitle} subtitle={`${project.title} / ${task.title}`} />
            <Card className="p-5">
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="text-sm font-semibold text-gray-900">Work Item Title</label>
                        <input value={data.title} onChange={(event) => setData('title', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500" />
                        {errors.title && <p className="mt-1 text-sm text-red-600">{errors.title}</p>}
                    </div>
                    <div>
                        <label className="text-sm font-semibold text-gray-900">Description</label>
                        <textarea rows={4} value={data.description} onChange={(event) => setData('description', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500" />
                    </div>
                    {method === 'post' && assignableSubordinates.length > 0 && (
                        <div>
                            <label className="text-sm font-semibold text-gray-900">Assign Subordinate</label>
                            <select value={data.subordinate_id} onChange={(event) => setData('subordinate_id', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm">
                                <option value="">Assign later</option>
                                {assignableSubordinates.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                            </select>
                            {errors.subordinate_id && <p className="mt-1 text-sm text-red-600">{errors.subordinate_id}</p>}
                        </div>
                    )}
                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <label className="text-sm font-semibold text-gray-900">Status</label>
                            <select value={data.status} onChange={(event) => setData('status', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm">
                                {statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-sm font-semibold text-gray-900">Priority</label>
                            <select value={data.priority} onChange={(event) => setData('priority', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm">
                                <option>low</option><option>medium</option><option>high</option><option>urgent</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-sm font-semibold text-gray-900">Deadline</label>
                            <input type="date" value={data.deadline} onChange={(event) => setData('deadline', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm" />
                        </div>
                    </div>
                    <button disabled={processing} className={`${buttonClass.primary} w-full`}>{submitLabel}</button>
                </form>
            </Card>
        </AuthenticatedLayout>
    );
}
