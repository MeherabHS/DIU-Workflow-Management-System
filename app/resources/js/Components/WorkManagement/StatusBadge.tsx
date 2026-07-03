import { cva } from 'class-variance-authority';
import { humanize } from '@/lib/utils';

const variants = cva('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', {
    variants: {
        tone: {
            planned: 'bg-gray-100 text-gray-800',
            pending: 'bg-gray-100 text-gray-800',
            in_progress: 'bg-blue-100 text-blue-800',
            ongoing: 'bg-blue-100 text-blue-800',
            submitted: 'bg-yellow-100 text-yellow-800',
            review: 'bg-yellow-100 text-yellow-800',
            completed: 'bg-green-100 text-green-800',
            approved: 'bg-green-100 text-green-800',
            revision_required: 'bg-red-100 text-red-800',
            cancelled: 'bg-red-100 text-red-800',
            archived: 'bg-slate-200 text-slate-800',
        },
    },
    defaultVariants: { tone: 'pending' },
});

export default function StatusBadge({ value }: { value?: string | null }) {
    return <span className={variants({ tone: (value || 'pending') as never })}>{humanize(value)}</span>;
}
