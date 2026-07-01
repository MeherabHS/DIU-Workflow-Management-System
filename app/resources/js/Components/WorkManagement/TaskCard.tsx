import { Link } from '@inertiajs/react';
import { Clock, Paperclip } from 'lucide-react';
import { BaseUser } from '@/types';
import { cn, dateText } from '@/lib/utils';
import PriorityBadge from './PriorityBadge';
import StatusBadge from './StatusBadge';
import AssignmentChips from './AssignmentChips';

type Action = { label: string; href: string; primary?: boolean; method?: 'get' | 'post' };

type CardProps = {
    title: string;
    description?: string | null;
    status?: string | null;
    priority?: string | null;
    dueDate?: string | null;
    users?: BaseUser[];
    fileCount?: number;
    contextLine?: string | null;
    actions?: Action[];
    className?: string;
};

function CardShell({ title, description, status, priority, dueDate, users = [], fileCount = 0, contextLine, actions = [], className }: CardProps) {
    return (
        <article className={cn('rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition-all hover:shadow-lg', className)}>
            <div className="mb-3 flex items-start justify-between gap-3">
                <h3 className="line-clamp-2 min-w-0 flex-1 text-lg font-semibold leading-6 text-gray-950">{title}</h3>
                {priority && <PriorityBadge value={priority} />}
            </div>

            <p className="mb-4 line-clamp-2 text-sm leading-6 text-gray-500">{description || 'No description provided.'}</p>
            {contextLine && <p className="mb-4 line-clamp-1 text-xs font-medium text-gray-500">{contextLine}</p>}

            <div className="mb-3 flex items-center justify-between gap-3">
                <div>{status && <StatusBadge value={status} />}</div>
                {dueDate && <span className="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-gray-500"><Clock className="h-3.5 w-3.5" />{dateText(dueDate)}</span>}
            </div>

            <div className="flex items-center justify-between gap-3">
                <AssignmentChips users={users} compact />
                <span className="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-gray-500"><Paperclip className="h-3.5 w-3.5" />{fileCount}</span>
            </div>

            {actions.length > 0 && (
                <div className="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-3">
                    {actions.map((action) => (
                        <Link key={`${action.label}-${action.href}`} href={action.href} method={action.method || 'get'} as={action.method === 'post' ? 'button' : 'a'} className={action.primary ? 'inline-flex items-center justify-center rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-gray-700' : 'inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 shadow-sm hover:bg-gray-50'}>
                            {action.label}
                        </Link>
                    ))}
                </div>
            )}
        </article>
    );
}

export default CardShell;
