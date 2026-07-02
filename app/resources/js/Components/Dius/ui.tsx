import { Link, usePage } from '@inertiajs/react';
import { cva } from 'class-variance-authority';
import { ArrowRight, Bell, Search } from 'lucide-react';
import { ReactNode, useEffect, useState } from 'react';
import { cn, humanize, initials } from '@/lib/utils';
import { PageProps } from '@/types';

export const buttonClass = {
    primary: 'inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700',
    secondary: 'inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50',
    small: 'inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 shadow-sm hover:bg-gray-50',
    danger: 'inline-flex items-center justify-center rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-600',
};

const statusVariants = cva('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', {
    variants: {
        tone: {
            planned: 'bg-gray-100 text-gray-800',
            pending: 'bg-gray-100 text-gray-800',
            active: 'bg-blue-100 text-blue-800',
            in_progress: 'bg-blue-100 text-blue-800',
            ongoing: 'bg-blue-100 text-blue-800',
            submitted: 'bg-yellow-100 text-yellow-800',
            review: 'bg-yellow-100 text-yellow-800',
            completed: 'bg-green-100 text-green-800',
            approved: 'bg-green-100 text-green-800',
            revision_required: 'bg-red-100 text-red-800',
            cancelled: 'bg-red-100 text-red-800',
            archive_pending: 'bg-slate-200 text-slate-800',
            archived: 'bg-slate-200 text-slate-800',
        },
    },
    defaultVariants: { tone: 'pending' },
});

const priorityVariants = cva('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', {
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

export function StatusPill({ value }: { value?: string | null }) {
    return <span className={statusVariants({ tone: (value || 'pending') as never })}>{humanize(value)}</span>;
}

export function PriorityPill({ value }: { value?: string | null }) {
    return <span className={priorityVariants({ tone: (value || 'medium') as never })}>{humanize(value)}</span>;
}

export function Card({ children, className = '' }: { children: ReactNode; className?: string }) {
    return <section className={cn('rounded-xl border border-gray-200 bg-white shadow-sm', className)}>{children}</section>;
}

export function PageHeader({ title, subtitle, action }: { title: string; subtitle?: string; action?: ReactNode }) {
    return (
        <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 className="text-2xl font-bold tracking-tight text-gray-950">{title}</h1>
                {subtitle && <p className="mt-1 text-sm text-gray-500">{subtitle}</p>}
            </div>
            {action && <div className="flex shrink-0 items-center gap-2">{action}</div>}
        </div>
    );
}

export function Toolbar({ actionHref, actionLabel, filters = false }: { actionHref?: string; actionLabel?: string; filters?: boolean }) {
    return (
        <Card className="mb-5 p-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-1 flex-col gap-3 sm:flex-row">
                    <label className="relative flex-1">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input className="w-full rounded-lg border-gray-300 pl-9 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500" placeholder="Search..." />
                    </label>
                    <select className="rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500" defaultValue="">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                    {filters && (
                        <select className="rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500" defaultValue="">
                            <option value="">All Types</option>
                            <option value="upcoming">Upcoming</option>
                            <option value="overdue">Overdue</option>
                        </select>
                    )}
                </div>
                {actionHref && actionLabel && (
                    <Link href={actionHref} className={buttonClass.primary}>{actionLabel}</Link>
                )}
            </div>
        </Card>
    );
}

export function EmptyState({ label = 'No records found.' }: { label?: string }) {
    return <Card className="p-8 text-center text-sm font-medium text-gray-500">{label}</Card>;
}

export function DetailGrid({ items }: { items: Array<{ label: string; value: ReactNode }> }) {
    return (
        <Card className="p-5">
            <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {items.map((item) => (
                    <div key={item.label}>
                        <dt className="text-xs font-semibold uppercase tracking-wide text-gray-500">{item.label}</dt>
                        <dd className="mt-1 text-sm font-semibold text-gray-900">{item.value}</dd>
                    </div>
                ))}
            </dl>
        </Card>
    );
}

export function ActionLink({ href, children, primary = false }: { href: string; children: ReactNode; primary?: boolean }) {
    return <Link href={href} className={primary ? buttonClass.primary : buttonClass.small}>{children}</Link>;
}

export function UserAvatar({ name, photoUrl }: { name?: string | null; photoUrl?: string | null }) {
    const [imageFailed, setImageFailed] = useState(false);

    useEffect(() => {
        setImageFailed(false);
    }, [photoUrl]);

    if (photoUrl && !imageFailed) {
        return <img src={photoUrl} alt={name ?? 'User'} onError={() => setImageFailed(true)} className="h-8 w-8 rounded-full object-cover" />;
    }

    return <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gray-900 text-xs font-bold text-white">{initials(name)}</span>;
}

export function HeaderBell() {
    const { notifications } = usePage<PageProps>().props;
    const unreadCount = notifications?.unreadCount ?? 0;

    return (
        <Link href={route('notifications.index')} className="relative inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50">
            <Bell className="h-4 w-4" />
            {unreadCount > 0 && (
                <span className="absolute -right-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{unreadCount > 99 ? '99+' : unreadCount}</span>
            )}
        </Link>
    );
}

export function ModuleCard({ title, description, href, actionLabel }: { title: string; description: string; href?: string | null; actionLabel?: string | null }) {
    return (
        <Card className="flex min-h-40 flex-col justify-between p-5">
            <div>
                <h3 className="text-base font-bold text-gray-950">{title}</h3>
                <p className="mt-2 text-sm leading-6 text-gray-500">{description}</p>
            </div>
            {href && actionLabel && (
                <Link href={href} className="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-gray-950">
                    {actionLabel}<ArrowRight className="h-4 w-4" />
                </Link>
            )}
        </Card>
    );
}

