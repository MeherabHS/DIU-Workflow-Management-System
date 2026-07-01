import { Card, PageHeader, UserAvatar, buttonClass } from '@/Components/Dius/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { BaseUser, Subtask } from '@/types';
import { Head, useForm } from '@inertiajs/react';

export default function AssignSubordinate({ subtask, subordinates = [], action }: { subtask: Subtask; subordinates: BaseUser[]; action: string }) {
    const { data, setData, post, processing, errors } = useForm({ subordinate_id: '' });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post(action);
    }

    return (
        <AuthenticatedLayout>
            <Head title="Assign Subordinate" />
            <PageHeader title="Assign Subordinate" subtitle={`Work Item: ${subtask.title}`} />
            <Card className="p-5">
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="text-sm font-semibold text-gray-900">Subordinate</label>
                        <select value={data.subordinate_id} onChange={(event) => setData('subordinate_id', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm">
                            <option value="">Select subordinate</option>
                            {subordinates.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                        </select>
                        {errors.subordinate_id && <p className="mt-1 text-sm text-red-600">{errors.subordinate_id}</p>}
                    </div>
                    <div className="rounded-xl border border-gray-200 bg-gray-50 p-3">
                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Available Subordinates</p>
                        <div className="space-y-2">
                            {subordinates.slice(0, 5).map((user) => (
                                <div key={user.id} className="flex items-center gap-3 rounded-lg bg-white p-2 shadow-sm">
                                    <UserAvatar name={user.name} />
                                    <div className="text-sm font-semibold text-gray-900">{user.name}</div>
                                </div>
                            ))}
                        </div>
                    </div>
                    <button disabled={processing} className={buttonClass.primary}>Assign Subordinate</button>
                </form>
            </Card>
        </AuthenticatedLayout>
    );
}
