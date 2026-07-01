import NotificationList from '@/Components/WorkManagement/NotificationList';
import { PageHeader } from '@/Components/Dius/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageProps, Paginator } from '@/types';

type Notification = {
    id: number;
    type: string;
    title: string;
    body: string | null;
    action_url: string | null;
    read_at: string | null;
    created_at: string;
};

export default function Index({
    notifications,
    unreadCount,
}: PageProps<{
    notifications: Paginator<Notification>;
    unreadCount: number;
}>) {
    return (
        <AuthenticatedLayout>
            <Head title="Notifications" />
            <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <PageHeader
                    title="Notifications"
                    subtitle={
                        unreadCount > 0
                            ? `${unreadCount} unread notification${unreadCount > 1 ? 's' : ''}`
                            : 'All caught up!'
                    }
                />
                {notifications.data.length === 0 ? (
                    <div className="rounded-xl border border-gray-200 bg-white p-8 text-center text-sm font-medium text-gray-500 shadow-sm">
                        No notifications yet.
                    </div>
                ) : (
                    <NotificationList notifications={notifications} />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
