import SearchFilterBar from '@/Components/WorkManagement/SearchFilterBar';
import TaskCard from '@/Components/WorkManagement/TaskCard';
import WorkPageLayout from '@/Components/WorkManagement/WorkPageLayout';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { BaseUser, Paginator, Project, Task } from '@/types';
import { Head } from '@inertiajs/react';

type Props = {
    project: Project;
    tasks: Paginator<Task>;
    canCreateTask?: boolean;
};

export default function Index({ project, tasks, canCreateTask = false }: Props) {
    return (
        <AuthenticatedLayout>
            <Head title="Project Tasks" />
            <WorkPageLayout title="Project Tasks" subtitle={`Tasks under ${project.title}.`}>
                <SearchFilterBar actionHref={canCreateTask ? route('project.tasks.create', project.id) : undefined} actionLabel={canCreateTask ? 'Create Task' : undefined} />
                {!tasks.data.length ? <div className="rounded-xl border border-gray-200 bg-white p-8 text-center text-sm font-medium text-gray-500">No tasks found.</div> : (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {tasks.data.map((task) => {
                            const assigned = (task.assigned_user || task.assignedUser) ? [task.assigned_user || task.assignedUser] as BaseUser[] : [];
                            return (
                                <TaskCard
                                    key={task.id}
                                    title={task.title}
                                    description={task.description}
                                    status={task.status}
                                    priority={task.priority}
                                    dueDate={task.deadline}
                                    users={assigned}
                                    fileCount={task.subtasks_count || 0}
                                    actions={[
                                        { label: 'View', href: route('tasks.show', task.id) },
                                    ]}
                                />
                            );
                        })}
                    </div>
                )}
            </WorkPageLayout>
        </AuthenticatedLayout>
    );
}
