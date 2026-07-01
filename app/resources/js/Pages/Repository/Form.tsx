import { Card, PageHeader, buttonClass } from '@/Components/Dius/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { BaseUser, Department, RepositoryEntry } from '@/types';
import { Head, useForm } from '@inertiajs/react';

export default function Form({ entry, departments = [], responsibleUsers = [], statuses = [], pageTitle, submitLabel, method, action }: { entry: RepositoryEntry; departments: Department[]; responsibleUsers: BaseUser[]; statuses: string[]; pageTitle: string; submitLabel: string; method: 'post' | 'patch'; action: string }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        title: entry.title || '',
        type: entry.type || '',
        department_id: String(entry.department?.id || ''),
        client_or_office: entry.client_or_office || '',
        responsible_user_id: String(entry.responsible_user?.id || entry.responsibleUser?.id || ''),
        status: entry.status || 'planned',
        deadline: entry.deadline?.slice(0, 10) || '',
        value_amount: entry.value_amount || '',
        value_currency: entry.value_currency || 'BDT',
        description: entry.description || '',
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        method === 'post' ? post(action) : patch(action);
    }

    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <PageHeader title={pageTitle} subtitle="Repository record ownership and timeline setup." />
            <Card className="p-5">
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="text-sm font-semibold text-gray-900">Title</label>
                        <input value={data.title} onChange={(event) => setData('title', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500" />
                        {errors.title && <p className="mt-1 text-sm text-red-600">{errors.title}</p>}
                    </div>
                    <div>
                        <label className="text-sm font-semibold text-gray-900">Description</label>
                        <textarea rows={4} value={data.description} onChange={(event) => setData('description', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm" />
                    </div>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div><label className="text-sm font-semibold">Type</label><input value={data.type} onChange={(event) => setData('type', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm" /></div>
                        <div><label className="text-sm font-semibold">Department</label><select value={data.department_id} onChange={(event) => setData('department_id', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm"><option value="">Select department</option>{departments.map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}</select></div>
                        <div><label className="text-sm font-semibold">Client/Office</label><input value={data.client_or_office} onChange={(event) => setData('client_or_office', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm" /></div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div><label className="text-sm font-semibold">Responsible Person</label><select value={data.responsible_user_id} onChange={(event) => setData('responsible_user_id', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm"><option value="">Select user</option>{responsibleUsers.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}</select></div>
                        <div><label className="text-sm font-semibold">Status</label><select value={data.status} onChange={(event) => setData('status', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm">{statuses.map((status) => <option key={status} value={status}>{status}</option>)}</select></div>
                        <div><label className="text-sm font-semibold">Deadline</label><input type="date" value={data.deadline} onChange={(event) => setData('deadline', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm" /></div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div><label className="text-sm font-semibold">Value Amount</label><input value={data.value_amount} onChange={(event) => setData('value_amount', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm" /></div>
                        <div><label className="text-sm font-semibold">Currency</label><input value={data.value_currency} onChange={(event) => setData('value_currency', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm" /></div>
                    </div>
                    <button disabled={processing} className={`${buttonClass.primary} w-full`}>{submitLabel}</button>
                </form>
            </Card>
        </AuthenticatedLayout>
    );
}
