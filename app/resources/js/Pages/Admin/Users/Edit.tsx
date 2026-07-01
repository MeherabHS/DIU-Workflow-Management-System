import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { Department } from '@/types';
import { useRef, useState } from 'react';
import { Camera, Trash2 } from 'lucide-react';

type User = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    profile_photo_url?: string | null;
    department_id?: number | null;
    department?: Department | null;
    designation?: string | null;
    phone?: string | null;
    roles?: string[];
};

export default function Edit({
    user,
    pageTitle = 'Edit User',
    departments,
    roles,
}: {
    user: User;
    pageTitle?: string;
    departments: Department[];
    roles: string[];
}) {
    const { data, setData, patch, processing, errors } = useForm({
        name: user.name,
        email: user.email,
        password: '',
        password_confirmation: '',
        role: user.roles?.[0] ?? roles[0],
        department_id: user.department_id ?? '',
        designation: user.designation ?? '',
        phone: user.phone ?? '',
        is_active: user.is_active,
    });

    const fileInputRef = useRef<HTMLInputElement>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(user.profile_photo_url || null);
    const [photoUploading, setPhotoUploading] = useState(false);

    const handlePhotoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        setPhotoUploading(true);
        const formData = new FormData();
        formData.append('photo', file);

        router.post(route('admin.users.photo.update', user.id), formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setPhotoUploading(false);
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
            onSuccess: (page) => {
                // Update preview with new URL from server response
                const updatedUser = (page.props as any).user as User;
                if (updatedUser?.profile_photo_url) {
                    setPhotoPreview(updatedUser.profile_photo_url);
                }
            },
        });
    };

    const handlePhotoRemove = () => {
        setPhotoUploading(true);
        router.delete(route('admin.users.photo.remove', user.id), {
            preserveScroll: true,
            onFinish: () => {
                setPhotoUploading(false);
                setPhotoPreview(null);
            },
        });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const payload = { ...data };
        if (!payload.password) {
            delete (payload as Record<string, unknown>).password;
            delete (payload as Record<string, unknown>).password_confirmation;
        }
        patch(route('admin.users.update', user.id));
    };

    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-2xl">
                <h1 className="mb-4 text-xl font-bold">{pageTitle}</h1>

                {/* Profile Photo Section */}
                <div className="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 className="mb-3 text-sm font-semibold text-gray-700">Profile Photo</h2>
                    <div className="flex items-center gap-4">
                        {photoPreview ? (
                            <img src={photoPreview} alt={user.name} className="h-16 w-16 rounded-full object-cover" />
                        ) : (
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-gray-900 text-lg font-bold text-white">
                                {user.name?.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) ?? '?'}
                            </div>
                        )}
                        <div className="flex flex-wrap gap-2">
                            <input ref={fileInputRef} type="file" accept="image/jpeg,image/png,image/webp" onChange={handlePhotoChange} className="hidden" />
                            <button type="button" onClick={() => fileInputRef.current?.click()} disabled={photoUploading} className="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50">
                                <Camera className="h-4 w-4" /> {photoUploading ? 'Uploading...' : 'Upload Photo'}
                            </button>
                            {photoPreview && (
                                <button type="button" onClick={handlePhotoRemove} disabled={photoUploading} className="inline-flex items-center gap-2 rounded-lg border border-red-300 bg-white px-3 py-1.5 text-sm font-semibold text-red-700 shadow-sm hover:bg-red-50 disabled:opacity-50">
                                    <Trash2 className="h-4 w-4" /> Remove
                                </button>
                            )}
                        </div>
                    </div>
                </div>

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
                            <label className="mb-1 block text-sm font-medium">Password <span className="text-gray-400">(leave blank to keep current)</span></label>
                            <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} className="w-full rounded-lg border px-3 py-2 text-sm" />
                            {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium">Confirm Password</label>
                            <input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} className="w-full rounded-lg border px-3 py-2 text-sm" />
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
                            <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} id="is_active_edit" />
                            <label htmlFor="is_active_edit" className="text-sm">Active</label>
                        </div>
                    </div>
                    <div className="mt-6 flex gap-3">
                        <button type="submit" disabled={processing} className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Update User</button>
                        <a href={route('admin.users.show', user.id)} className="rounded-lg border px-4 py-2 text-sm font-semibold">Cancel</a>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
