import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ModuleCard, PageHeader, buttonClass } from '@/Components/Dius/ui';
import { Head, Link } from '@inertiajs/react';

type Module = { title: string; description: string; href?: string | null; actionLabel?: string | null };

export default function RoleDashboard({ title, description, modules = [], primaryAction }: { title: string; description: string; modules: Module[]; primaryAction?: { label: string; href: string } | null }) {
    return (
        <AuthenticatedLayout>
            <Head title={title} />
            <PageHeader title={title} subtitle={description} action={primaryAction ? <Link href={primaryAction.href} className={buttonClass.primary}>{primaryAction.label}</Link> : null} />
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {modules.map((module) => <ModuleCard key={module.title} {...module} />)}
            </div>
        </AuthenticatedLayout>
    );
}
