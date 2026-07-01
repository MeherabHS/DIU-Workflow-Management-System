import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { Department } from '@/types';

export default function Create({
    pageTitle = 'Create User',
    departments,
    roles,
}: {
    pageTitle?: string;
    departments: Department[];
    roles: string[];
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: roles[0] ?? 'Coordinator',
        department_id: '',
        designation: '',
        phone: '',
        is_active: true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.users.store'), { onSuccess: () => reset() });
    };

    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-2xl">
                <h1 className="mb-4 text-xl font-bold">{pageTitle}</h1>
                <form onSubmit={submit} className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div className="space-y-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium">Name *</label>
                            <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="w-full rounded-lg border px-3 py-2 text-sm" required />
                            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium">Email *</label>
                            <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="w-full rounded-lg border px-3 py-2 text-sm" required />
                            {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium">Password *</label>
                            <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} className="w-full rounded-lg border px-3 py-2 text-sm" required />
                            {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium">Confirm Password *</label>
                            <input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} className="w-full rounded-lg border px-3 py-2 text-sm" required />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium">Role *</label>
                            <select value={data.role} onChange={(e) => setData('role', e.target.value)} className="w-full rounded-lg border px-3 py-2 text-sm">
                                {roles.map((r) => <option key={r} value={r}>{r}</option>)}
                            </select>
                            {errors.role && <p className="mt-1 text-xs text-red-600">{errors.role}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium">Department</label>
                            <select value={data.department_id} onChange={(e) => setData('department_id', e.target.value)} className="w-full rounded-lg border px-3 py-2 text-sm">
                                <option value="">None</option>
                                {departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium">Designation</label>
                            <input type="text" value={data.designation} onChange={(e) => setData('designation', e.target.value)} className="w-full rounded-lg border px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium">Phone</label>
                            <input type="text" value={data.phone} onChange={(e) => setData('phone', e.target.value)} className="w-full rounded-lg border px-3 py-2 text-sm" />
                        </div>
                        <div className="flex items-center gap-2">
                            <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} id="is_active" />
                            <label htmlFor="is_active" className="text-sm">Active</label>
                        </div>
                    </div>
                    <div className="mt-6 flex gap-3">
                        <button type="submit" disabled={processing} className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Create User</button>
                        <a href={route('admin.users.index')} className="rounded-lg border px-4 py-2 text-sm font-semibold">Cancel</a>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
