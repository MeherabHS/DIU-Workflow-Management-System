import { Link } from '@inertiajs/react';
import StatusBadge from '@/Components/WorkManagement/StatusBadge';

type TaskItem = {
    id: number;
    title: string;
    status: string | null;
    deadline: string | null;
    project?: string | null;
    department?: string | null;
    type: 'work_item' | 'task';
};

export default function RemainingTasksList({ tasks }: { tasks: TaskItem[] }) {
    if (!tasks.length) {
        return (
            <div className="flex h-32 items-center justify-center text-sm text-gray-400">No remaining tasks.</div>
        );
    }

    return (
        <div className="divide-y divide-gray-100">
            {tasks.map((task) => {
                const detailRoute = task.type === 'work_item'
                    ? route('subtasks.mine.show', task.id)
                    : route('tasks.show', task.id);
                return (
                    <div key={task.id} className="flex items-center justify-between gap-3 py-3">
                        <div className="min-w-0 flex-1">
                            <Link href={detailRoute} className="text-sm font-medium text-blue-600 hover:underline truncate block">
                                {task.title}
                            </Link>
                            <p className="text-xs text-gray-500">
                                {task.project ? `${task.project}` : ''}
                                {task.department ? ` · ${task.department}` : ''}
                                {task.deadline ? ` · Due ${task.deadline}` : ''}
                            </p>
                        </div>
                        <StatusBadge value={task.status} />
                    </div>
                );
            })}
        </div>
    );
}
