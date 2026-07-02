import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useState, useEffect } from 'react';
import { UserAvatar } from '@/Components/Dius/ui';
import { Department, Paginator, User as UserType } from '@/types';

type User = UserType & {
    is_active: boolean;
    department?: Department | null;
    designation?: string | null;
    phone?: string | null;
    roles?: string[];
};

export default function Index({
    users,
    pageTitle = 'User Management',
    pageSubtitle = 'Create and manage user accounts and roles.',
    primaryAction,
    departments,
    roles,
    filters,
}: {
    users: Paginator<User>;
    pageTitle?: string;
    pageSubtitle?: string;
    primaryAction?: string | null;
    departments: Department[];
    roles: string[];
    filters?: { search?: string; role?: string; is_active?: string };
}) {
    const [search, setSearch] = useState(filters?.search ?? '');
    const [role, setRole] = useState(filters?.role ?? '');
    const [isActive, setIsActive] = useState(filters?.is_active ?? '');
    const [togglingUserId, setTogglingUserId] = useState<number | null>(null);

    const navigate = useCallback((params: Record<string, string | undefined>) => {
        router.get(route('admin.users.index'), params, { preserveState: true, replace: true });
    }, []);

    useEffect(() => {
        const timer = setTimeout(() => navigate({ search: search || undefined }), 300);
        return () => clearTimeout(timer);
    }, [search, navigate]);

    const handleRoleChange = (value: string) => {
        setRole(value);
        navigate({ role: value || undefined });
    };

    const handleStatusChange = (value: string) => {
        setIsActive(value);
        navigate({ is_active: value || undefined });
    };

    const toggleActive = (user: User) => {
        setTogglingUserId(user.id);
        router.post(route('admin.users.toggle-active', user.id), {}, {
            onFinish: () => setTogglingUserId(null),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <div className="mb-6">
                <h1 className="text-2xl font-bold tracking-tight text-gray-950">{pageTitle}</h1>
                <p className="mt-1 text-sm text-gray-500">{pageSubtitle}</p>
            </div>

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <input
                    type="text"
                    placeholder="Search users..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                />
                <select
                    value={role}
                    onChange={(e) => handleRoleChange(e.target.value)}
                    className="rounded-lg border border-gray-300 px-4 py-2 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                >
                    <option value="">All Roles</option>
                    {roles.map((r) => (
                        <option key={r} value={r}>{r}</option>
                    ))}
                </select>
                <select
                    value={isActive}
                    onChange={(e) => handleStatusChange(e.target.value)}
                    className="rounded-lg border border-gray-300 px-4 py-2 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                >
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                    <option value="pending">Pending (No Role)</option>
                </select>
                {primaryAction && (
                    <Link href={route('admin.users.create')} className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700">
                        {primaryAction}
                    </Link>
                )}
            </div>

            {!users.data.length ? (
                <div className="rounded-xl border border-gray-200 bg-white p-8 text-center text-sm font-medium text-gray-500">No users found.</div>
            ) : (
                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-gray-100">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Email</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Role</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {users.data.map((user) => (
                                <tr key={user.id} className={!user.is_active ? 'bg-gray-50' : ''}>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            <UserAvatar name={user.name} photoUrl={user.profile_photo_url} />
                                            <span className="text-sm font-medium text-gray-900">{user.name}</span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{user.email}</td>
                                    <td className="px-4 py-3 text-sm text-gray-600">
                                        {(() => {
                                            const r = user.roles as any;
                                            if (!r || (Array.isArray(r) && r.length === 0)) return <span className="text-amber-600 font-medium">Pending</span>;
                                            if (typeof r === 'string') return r;
                                            if (Array.isArray(r) && typeof r[0] === 'string') return r.join(', ');
                                            if (Array.isArray(r) && typeof r[0] === 'object' && r[0]?.name) return (r as { name: string }[]).map((role) => role.name).join(', ');
                                            if (typeof r === 'object' && !Array.isArray(r) && r?.name) return (r as { name: string }).name;
                                            return <span className="text-amber-600 font-medium">Pending</span>;
                                        })()}
                                    </td>
                                    <td className="px-4 py-3 text-sm">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                                            !user.roles?.length
                                                ? 'bg-amber-100 text-amber-800'
                                                : user.is_active
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-red-100 text-red-800'
                                        }`}>
                                            {!user.roles?.length ? 'Pending' : user.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right text-sm">
                                        <Link href={route('admin.users.show', user.id)} className="mr-2 text-blue-600 hover:underline">View</Link>
                                        <Link href={route('admin.users.edit', user.id)} className="mr-2 text-gray-600 hover:underline">Edit</Link>
                                        <button onClick={() => toggleActive(user)} disabled={togglingUserId === user.id} className="text-gray-600 hover:underline disabled:opacity-50 disabled:cursor-not-allowed">
                                            {togglingUserId === user.id ? 'Processing...' : user.is_active ? 'Deactivate' : 'Activate'}
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {users.links && users.links.length > 3 && (
                        <div className="flex justify-center gap-1 border-t border-gray-100 px-4 py-3">
                            {users.links.map((link, i) => (
                                link.url ? (
                                    <button
                                        key={i}
                                        onClick={() => router.get(link.url!, {}, { preserveState: true })}
                                        className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100'}`}
                                    >
                                        {link.label}
                                    </button>
                                ) : (
                                    <span key={i} className="rounded px-3 py-1 text-sm text-gray-400">
                                        {link.label}
                                    </span>
                                )
                            ))}
                        </div>
                    )}
                </div>
            )}
        </AuthenticatedLayout>
    );
}

