import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, ModuleCard, PageHeader } from '@/Components/Dius/ui';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';

type DashboardModule = { title: string; description: string; href?: string | null; actionLabel?: string | null };

export default function Dashboard({ dashboardLinks = [], dashboardModules = [], assignedRoles = [], visibilityText = 'Assigned roles:' }: PageProps<{ dashboardLinks: Array<{ route: string; label: string }>; dashboardModules: DashboardModule[]; assignedRoles: string[]; visibilityText: string }>) {
    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />
            <PageHeader title="Dashboard" subtitle="Project, task, repository, and team workflow" />
            <Card className="mb-5 p-5">
                <p className="text-sm font-semibold text-gray-900">{visibilityText}</p>
                <p className="mt-1 text-sm text-gray-500">{assignedRoles.length ? assignedRoles.join(', ') : 'No role dashboards assigned'}</p>
                <div className="mt-4 flex flex-wrap gap-2">
                    {dashboardLinks.map((link) => <Link key={link.label} href={route(link.route)} className="inline-flex items-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700">{link.label}</Link>)}
                </div>
            </Card>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {dashboardModules.map((module) => (
                    <ModuleCard key={module.title} title={module.title} description={module.description} href={module.href || undefined} actionLabel={module.actionLabel || undefined} />
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
