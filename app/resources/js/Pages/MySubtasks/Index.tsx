import TaskCard from '@/Components/WorkManagement/TaskCard';
import WorkPageLayout from '@/Components/WorkManagement/WorkPageLayout';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { dateText } from '@/lib/utils';
import { Paginator, Subtask } from '@/types';
import { Head } from '@inertiajs/react';

export default function Index({ subtasks, pageTitle = 'My Work Items', pageSubtitle = 'Work items currently assigned to you.' }: { subtasks: Paginator<Subtask>; pageTitle?: string; pageSubtitle?: string }) {
    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <WorkPageLayout title={pageTitle} subtitle={pageSubtitle}>
                {!subtasks.data.length ? <div className="rounded-xl border border-gray-200 bg-white p-8 text-center text-sm font-medium text-gray-500">No assigned work items found.</div> : (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {subtasks.data.map((subtask) => (
                            <TaskCard
                                key={subtask.id}
                                title={subtask.title}
                                description={subtask.description || 'Assigned work item'}
                                status={subtask.status}
                                priority={subtask.priority}
                                dueDate={subtask.deadline}
                                users={[]}
                                fileCount={0}
                                contextLine={`Project: ${subtask.project?.title || 'Not set'}  Task: ${subtask.task?.title || 'Not set'}${subtask.current_assigned_at ? `  Assigned: ${dateText(subtask.current_assigned_at)}` : ''}`}
                                actions={[{ label: 'View Work Item', href: route('my-work-items.show', subtask.id), primary: true }]}
                            />
                        ))}
                    </div>
                )}
            </WorkPageLayout>
        </AuthenticatedLayout>
    );
}
