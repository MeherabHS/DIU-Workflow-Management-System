import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Department } from '@/types';

type User = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    department?: Department | null;
    designation?: string | null;
    phone?: string | null;
    roles?: string[];
    created_projects_count?: number;
    assigned_projects_count?: number;
};

export default function Show({
    user,
    pageTitle = 'User Details',
}: {
    user: User;
    pageTitle?: string;
}) {
    const resetPassword = () => {
        router.post(route('admin.users.reset-password', user.id));
    };

    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-2xl">
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold">{pageTitle}</h1>
                        <p className="text-sm text-gray-500">{user.email}</p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('admin.users.edit', user.id)} className="rounded-lg border px-3 py-1.5 text-sm font-semibold">Edit</Link>
                        <button onClick={resetPassword} className="rounded-lg border px-3 py-1.5 text-sm font-semibold">Reset Password</button>
                        <Link href={route('admin.users.index')} className="rounded-lg border px-3 py-1.5 text-sm font-semibold">Back</Link>
                    </div>
                </div>
                <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <dl className="grid gap-4 sm:grid-cols-2">
                        <div><dt className="text-sm font-medium text-gray-500">Name</dt><dd className="text-sm">{user.name}</dd></div>
                        <div><dt className="text-sm font-medium text-gray-500">Email</dt><dd className="text-sm">{user.email}</dd></div>
                        <div><dt className="text-sm font-medium text-gray-500">Role</dt><dd className="text-sm">{user.roles?.join(', ') ?? '—'}</dd></div>
                        <div><dt className="text-sm font-medium text-gray-500">Status</dt><dd><span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${user.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>{user.is_active ? 'Active' : 'Inactive'}</span></dd></div>
                        <div><dt className="text-sm font-medium text-gray-500">Department</dt><dd className="text-sm">{user.department?.name ?? '—'}</dd></div>
                        <div><dt className="text-sm font-medium text-gray-500">Designation</dt><dd className="text-sm">{user.designation ?? '—'}</dd></div>
                        <div><dt className="text-sm font-medium text-gray-500">Phone</dt><dd className="text-sm">{user.phone ?? '—'}</dd></div>
                        <div><dt className="text-sm font-medium text-gray-500">Created Projects</dt><dd className="text-sm">{user.created_projects_count ?? 0}</dd></div>
                    </dl>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
