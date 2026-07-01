import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { BarChart3, FileText, Users, Award, CheckCircle, Archive, Activity, Download } from 'lucide-react';

type ReportItem = { key: string; title: string; description: string };

export default function Index({
    reports,
    canExport,
    pageTitle = 'Reports & Export',
    pageSubtitle = 'Generate reports and export data for projects, tasks, and workflow activities.',
}: {
    reports: ReportItem[];
    canExport: boolean;
    pageTitle?: string;
    pageSubtitle?: string;
}) {
    const iconMap: Record<string, React.ComponentType<{ className?: string }>> = {
        'project-progress': BarChart3,
        'task-status': FileText,
        'coordinator-performance': Users,
        'subordinate-completion': Award,
        'repository-preservation': Archive,
        'audit-activity': Activity,
    };

    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <div className="mb-6">
                <h1 className="text-xl font-semibold text-gray-900">{pageTitle}</h1>
                <p className="mt-0.5 text-sm text-gray-500">{pageSubtitle}</p>
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {reports.map((report) => {
                    const Icon = iconMap[report.key] ?? FileText;
                    return (
                        <Link key={report.key} href={route(`reports.${report.key}`)} className="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:border-[#2563eb] hover:shadow-md transition-all">
                            <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-[#2e3a8c]">
                                <Icon className="h-5 w-5 text-white" />
                            </div>
                            <h3 className="text-sm font-semibold text-gray-900 group-hover:text-[#2563eb]">{report.title}</h3>
                            <p className="mt-1 text-xs text-gray-500">{report.description}</p>
                            {canExport && (
                                <div className="mt-3 flex items-center gap-1 text-xs text-[#2563eb]">
                                    <Download className="h-3 w-3" />
                                    <span>View & Export</span>
                                </div>
                            )}
                        </Link>
                    );
                })}
            </div>
        </AuthenticatedLayout>
    );
}
