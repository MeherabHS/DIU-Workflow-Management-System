import MessageThread from '@/Components/WorkManagement/MessageThread';
import WorkPageLayout from '@/Components/WorkManagement/WorkPageLayout';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function TaskMessages(props: any) {
    return (
        <AuthenticatedLayout>
            <Head title={props.pageTitle} />
            <WorkPageLayout title={props.pageTitle} subtitle={props.contextTitle}>
                <MessageThread {...props} />
            </WorkPageLayout>
        </AuthenticatedLayout>
    );
}
