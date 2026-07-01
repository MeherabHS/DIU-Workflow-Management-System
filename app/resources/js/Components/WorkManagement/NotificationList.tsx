import NotificationCard from '@/Components/WorkManagement/NotificationCard';
import { router } from '@inertiajs/react';
import { Paginator } from '@/types';
import { useState } from 'react';

type Notification = {
    id: number;
    type: string;
    title: string;
    body: string | null;
    action_url: string | null;
    read_at: string | null;
    created_at: string;
};

export default function NotificationList({
    notifications,
}: {
    notifications: Paginator<Notification>;
}) {
    const [processingAll, setProcessingAll] = useState(false);
    const [processingIds, setProcessingIds] = useState<Set<number>>(new Set());

    function handleMarkRead(id: number) {
        if (processingIds.has(id)) return;
        setProcessingIds((prev) => new Set(prev).add(id));
        router.post(route('notifications.read', id), {}, {
            preserveScroll: true,
            onFinish: () => {
                setProcessingIds((prev) => {
                    const next = new Set(prev);
                    next.delete(id);
                    return next;
                });
            },
        });
    }

    function handleMarkAllRead() {
        if (processingAll) return;
        setProcessingAll(true);
        router.post(route('notifications.read-all'), {}, {
            preserveScroll: true,
            onFinish: () => setProcessingAll(false),
        });
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <p className="text-sm text-gray-500">
                    Showing {notifications.data.length} of {notifications.total} notifications
                </p>
                <button
                    onClick={handleMarkAllRead}
                    disabled={processingAll}
                    className="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {processingAll ? 'Processing...' : 'Mark all as read'}
                </button>
            </div>
            <div className="space-y-3">
                {notifications.data.map((n) => (
                    <NotificationCard key={n.id} notification={n} onMarkRead={handleMarkRead} disabled={processingIds.has(n.id)} />
                ))}
            </div>
            {notifications.links && notifications.links.length > 3 && (
                <div className="flex flex-wrap items-center justify-center gap-1 pt-4">
                    {notifications.links.map((link, idx) => (
                        link.url ? (
                            <a
                                key={idx}
                                href={link.url}
                                className={`rounded-md px-3 py-1.5 text-sm font-semibold ${
                                    link.active
                                        ? 'bg-gray-900 text-white'
                                        : 'text-gray-700 hover:bg-gray-100'
                                }`}
                            >
                                {link.label}
                            </a>
                        ) : (
                            <span
                                key={idx}
                                className={`rounded-md px-3 py-1.5 text-sm font-semibold ${
                                    link.active
                                        ? 'bg-gray-900 text-white'
                                        : 'text-gray-400'
                                }`}
                            >
                                {link.label}
                            </span>
                        )
                    ))}
                </div>
            )}
        </div>
    );
}
