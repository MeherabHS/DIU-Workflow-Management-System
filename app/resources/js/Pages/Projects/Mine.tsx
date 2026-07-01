import WorkPageLayout from '@/Components/WorkManagement/WorkPageLayout';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Paginator, Project } from '@/types';
import { Head } from '@inertiajs/react';
import { ProjectCards } from './Index';

export default function Mine({ projects, pageTitle = 'My Assigned Projects', pageSubtitle = 'Projects currently assigned to you for coordination.' }: { projects: Paginator<Project>; pageTitle?: string; pageSubtitle?: string }) {
    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <WorkPageLayout title={pageTitle} subtitle={pageSubtitle}>
                <ProjectCards projects={projects.data} assignedOnly />
            </WorkPageLayout>
        </AuthenticatedLayout>
    );
}
