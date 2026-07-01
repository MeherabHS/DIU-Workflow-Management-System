
type NotificationType =
    | 'coordinator_assigned'
    | 'coordinator_revoked'
    | 'subordinate_assigned'
    | 'subordinate_revoked'
    | 'message_created'
    | 'file_uploaded'
    | 'progress_updated'
    | 'deadline_reminder'
    | 'overdue_alert';

const typeBadgeClass: Record<string, string> = {
    coordinator_assigned: 'bg-blue-100 text-blue-800',
    coordinator_revoked: 'bg-gray-200 text-gray-800',
    subordinate_assigned: 'bg-blue-100 text-blue-800',
    subordinate_revoked: 'bg-gray-200 text-gray-800',
    message_created: 'bg-green-100 text-green-800',
    file_uploaded: 'bg-purple-100 text-purple-800',
    progress_updated: 'bg-yellow-100 text-yellow-800',
    deadline_reminder: 'bg-orange-100 text-orange-800',
    overdue_alert: 'bg-red-100 text-red-800',
};

const typeLabel: Record<string, string> = {
    coordinator_assigned: 'Assignment',
    coordinator_revoked: 'Revoked',
    subordinate_assigned: 'Assignment',
    subordinate_revoked: 'Revoked',
    message_created: 'Message',
    file_uploaded: 'File',
    progress_updated: 'Progress',
    deadline_reminder: 'Deadline',
    overdue_alert: 'Overdue',
};

function timeAgo(dateString: string): string {
    const now = new Date();
    const date = new Date(dateString);
    const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (seconds < 60) return 'just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`;
    return date.toLocaleDateString();
}

export default function NotificationCard({
    notification,
    onMarkRead,
    disabled = false,
}: {
    notification: {
        id: number;
        type: string;
        title: string;
        body: string | null;
        action_url: string | null;
        read_at: string | null;
        created_at: string;
    };
    onMarkRead: (id: number) => void;
    disabled?: boolean;
}) {
    const isRead = !!notification.read_at;
    const badgeClass = typeBadgeClass[notification.type] ?? 'bg-gray-100 text-gray-800';
    const label = typeLabel[notification.type] ?? notification.type;

    return (
        <div
            className={`rounded-lg border p-4 ${isRead ? 'border-gray-200 bg-gray-50' : 'border-blue-200 bg-blue-50'}`}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex-1">
                    <div className="mb-1 flex items-center gap-2">
                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${badgeClass}`}>{label}</span>
                        {!isRead && <span className="h-2 w-2 rounded-full bg-blue-600" />}
                    </div>
                    <h3 className="text-sm font-semibold text-gray-900">{notification.title}</h3>
                    {notification.body && <p className="mt-1 text-sm text-gray-600">{notification.body}</p>}
                    <p className="mt-2 text-xs text-gray-400">{timeAgo(notification.created_at)}</p>
                </div>
                <div className="flex shrink-0 flex-col gap-2">
                    {notification.action_url ? (
                        <a
                            href={notification.action_url}
                            className={`inline-flex items-center justify-center rounded-md px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:opacity-90 ${
                                isRead ? 'bg-gray-600' : 'bg-blue-600'
                            }`}
                        >
                            View
                        </a>
                    ) : (
                        <span className="inline-flex items-center justify-center rounded-md border border-gray-200 bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-400">
                            View
                        </span>
                    )}
                    {!isRead && (
                        <button
                            onClick={() => onMarkRead(notification.id)}
                            disabled={disabled}
                            className="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {disabled ? 'Processing...' : 'Mark read'}
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
