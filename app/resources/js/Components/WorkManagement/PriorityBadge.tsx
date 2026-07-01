import { cva } from 'class-variance-authority';
import { humanize } from '@/lib/utils';

const variants = cva('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', {
    variants: {
        tone: {
            low: 'bg-gray-100 text-gray-700',
            medium: 'bg-blue-100 text-blue-700',
            high: 'bg-orange-100 text-orange-700',
            urgent: 'bg-red-100 text-red-700',
        },
    },
    defaultVariants: { tone: 'medium' },
});

export default function PriorityBadge({ value }: { value?: string | null }) {
    return <span className={variants({ tone: (value || 'medium') as never })}>{humanize(value)}</span>;
}
