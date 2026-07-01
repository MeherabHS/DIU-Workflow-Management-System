import { useForm, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useRef, useState } from 'react';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import { Camera, Trash2 } from 'lucide-react';

export default function UpdateProfilePhotoForm({ className = '' }: { className?: string }) {
    const { auth } = usePage<PageProps<{ mustVerifyEmail: boolean; status?: string }>>().props;
    const user = auth.user!;
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(user.profile_photo_url || null);

    const { data, setData, post, delete: destroy, processing, errors, reset } = useForm({
        photo: null as File | null,
    });

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] || null;
        if (file) {
            setData('photo', file);
            setPreview(URL.createObjectURL(file));
        }
    };

    const handleUpload = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.photo) return;

        post(route('profile.photo.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    };

    const handleRemove = () => {
        destroy(route('profile.photo.remove'), {
            preserveScroll: true,
            onSuccess: () => {
                setPreview(null);
            },
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">Profile Photo</h2>
                <p className="mt-1 text-sm text-gray-600">Upload a new profile photo. Max 2MB. Allowed: JPG, PNG, WebP.</p>
            </header>

            <div className="mt-6 flex items-center gap-6">
                <div className="relative">
                    {preview ? (
                        <img src={preview} alt={user.name ?? 'User'} className="h-20 w-20 rounded-full object-cover ring-2 ring-gray-200" />
                    ) : (
                        <div className="flex h-20 w-20 items-center justify-center rounded-full bg-gray-900 text-xl font-bold text-white">
                            {user.name?.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) ?? '?'}
                        </div>
                    )}
                </div>

                <div className="flex-1">
                    <form onSubmit={handleUpload} className="flex flex-wrap items-center gap-3">
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={handleFileChange}
                            className="hidden"
                        />
                        <button
                            type="button"
                            onClick={() => fileInputRef.current?.click()}
                            className="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                        >
                            <Camera className="h-4 w-4" />
                            Choose Photo
                        </button>
                        {data.photo && (
                            <PrimaryButton disabled={processing}>Upload</PrimaryButton>
                        )}
                        {preview && (
                            <button
                                type="button"
                                onClick={handleRemove}
                                className="inline-flex items-center gap-2 rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 shadow-sm hover:bg-red-50"
                            >
                                <Trash2 className="h-4 w-4" />
                                Remove
                            </button>
                        )}
                    </form>
                    <InputError message={errors.photo} className="mt-2" />
                </div>
            </div>
        </section>
    );
}
