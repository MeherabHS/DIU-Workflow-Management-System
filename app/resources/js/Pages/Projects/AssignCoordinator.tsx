import { Card, PageHeader, buttonClass } from '@/Components/Dius/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { BaseUser, Project } from '@/types';
import { Head, useForm } from '@inertiajs/react';

export default function AssignCoordinator({ project, coordinators = [], action }: { project: Project; coordinators: BaseUser[]; action: string }) {
    const { data, setData, post, processing, errors } = useForm({ coordinator_id: '' });
    function submit(e: React.FormEvent) { e.preventDefault(); post(action); }
    return <AuthenticatedLayout><Head title="Assign Coordinator" /><PageHeader title="Assign Coordinator" subtitle={project.title} />
        <Card className="p-5"><form onSubmit={submit} className="space-y-4"><div><label className="text-sm font-semibold">Coordinator</label><select value={data.coordinator_id} onChange={(e)=>setData('coordinator_id', e.target.value)} className="mt-1 w-full rounded-lg border-gray-300 shadow-sm"><option value="">Select coordinator</option>{coordinators.map((user)=><option key={user.id} value={user.id}>{user.name}</option>)}</select>{errors.coordinator_id && <p className="text-sm text-red-600">{errors.coordinator_id}</p>}</div><button disabled={processing} className={buttonClass.primary}>Assign Coordinator</button></form></Card>
    </AuthenticatedLayout>;
}
