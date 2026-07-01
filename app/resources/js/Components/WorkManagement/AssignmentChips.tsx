import { BaseUser } from '@/types';
import { initials } from '@/lib/utils';

export default function AssignmentChips({ users = [], compact = false }: { users?: Array<BaseUser & { role?: string | null }>; compact?: boolean }) {
    if (!users.length) {
        return <span className="text-xs font-medium text-gray-400">Unassigned</span>;
    }

    if (compact) {
        return (
            <div className="flex -space-x-2">
                {users.slice(0, 3).map((user) => (
                    <span key={user.id} title={user.name} className="inline-flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-gray-900 text-[10px] font-bold text-white">
                        {initials(user.name)}
                    </span>
                ))}
                {users.length > 3 && <span className="inline-flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-gray-100 text-[10px] font-bold text-gray-700">+{users.length - 3}</span>}
            </div>
        );
    }

    return (
        <div className="flex flex-wrap gap-2">
            {users.map((user) => (
                <span key={user.id} className="inline-flex items-center gap-2 rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-800">
                    <span className="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-900 text-[10px] font-bold text-white">{initials(user.name)}</span>
                    {user.name}
                    {user.role && <span className="text-xs capitalize text-gray-500">({user.role})</span>}
                </span>
            ))}
        </div>
    );
}
