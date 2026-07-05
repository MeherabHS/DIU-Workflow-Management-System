import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DashboardKpiCard from '@/Components/WorkManagement/DashboardKpiCard';
import TaskStatusDonut from '@/Components/WorkManagement/TaskStatusDonut';
import TaskLineupChart from '@/Components/WorkManagement/TaskLineupChart';
import { ModuleCard } from '@/Components/Dius/ui';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Kpi = { label: string; value: number; color: string };
type StatusFilter = 'all' | 'in_progress' | 'completed' | 'due' | 'overdue';
type ProjectStatus = { id: number; title: string; status: string | null; priority?: string | null; coordinator?: string | null; department?: string | null; deadline?: string | null; is_overdue?: boolean };
type StatusData = { name: string; value: number; color: string };
type MonthData = { month: string; completed: number };
type ModuleItem = { title: string; description: string; href?: string | null; actionLabel?: string | null };

const filterLabel: Record<StatusFilter, string> = {
    all: 'All Projects',
    in_progress: 'In Progress',
    completed: 'Completed',
    due: 'Due',
    overdue: 'Overdue',
};

const filterByKpiLabel: Record<string, StatusFilter> = {
    'In Progress': 'in_progress',
    Completed: 'completed',
    Due: 'due',
    Overdue: 'overdue',
    'Total Projects': 'all',
};

const statusColor = (status: string | null): string => {
    switch (normalizeStatus(status)) {
        case 'completed': return 'bg-emerald-100 text-emerald-700';
        case 'in_progress': return 'bg-blue-100 text-blue-700';
        case 'submitted': return 'bg-amber-100 text-amber-700';
        case 'planned': return 'bg-gray-100 text-gray-600';
        case 'archived': return 'bg-gray-100 text-gray-600';
        case 'cancelled': return 'bg-red-100 text-red-700';
        default: return 'bg-gray-100 text-gray-600';
    }
};

const priorityColor = (priority: string | null | undefined): string => {
    switch (priority) {
        case 'urgent': return 'bg-red-100 text-red-700';
        case 'high': return 'bg-orange-100 text-orange-700';
        case 'medium': return 'bg-yellow-100 text-yellow-700';
        case 'low': return 'bg-gray-100 text-gray-600';
        default: return 'bg-gray-100 text-gray-600';
    }
};

const normalizeStatus = (status: string | null): string => {
    const value = (status ?? '').trim();

    if (!value) return 'planned';
    if (value === 'active') return 'in_progress';
    if (value === 'archive_pending') return 'completed';

    return value;
};

const todayDateString = (): string => {
    const date = new Date();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
};

const isClosedProject = (project: ProjectStatus): boolean => ['completed', 'archived', 'cancelled'].includes(normalizeStatus(project.status));

const matchesFilter = (project: ProjectStatus, filter: StatusFilter, today: string): boolean => {
    const status = normalizeStatus(project.status);

    switch (filter) {
        case 'in_progress':
            return ['in_progress', 'submitted'].includes(status);
        case 'completed':
            return status === 'completed';
        case 'due':
            return Boolean(project.deadline) && !isClosedProject(project) && project.deadline! >= today;
        case 'overdue':
            return Boolean(project.deadline) && !isClosedProject(project) && project.deadline! < today;
        case 'all':
        default:
            return true;
    }
};

const humanizeStatus = (status: string | null): string => {
    const normalizedStatus = normalizeStatus(status);

    if (!normalizedStatus) return 'Pending';

    return normalizedStatus.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
};
export default function Admin({
    kpis,
    projectStatuses,
    dashboardProjectStatuses,
    statusData,
    completionByMonth,
    modules,
    pageTitle = 'Workflow Improvement and Tracking Dashboard of Project',
    pageSubtitle = 'This page shows graphical presentation of project workflow status including in-progress, completed, due, and overdue records.',
}: {
    kpis: Kpi[];
    projectStatuses: ProjectStatus[];
    dashboardProjectStatuses?: ProjectStatus[];
    statusData: StatusData[];
    completionByMonth: MonthData[];
    modules: ModuleItem[];
    pageTitle?: string;
    pageSubtitle?: string;
}) {
    const [selectedStatusFilter, setSelectedStatusFilter] = useState<StatusFilter>('all');
    const statusRows = dashboardProjectStatuses ?? projectStatuses;
    const today = useMemo(() => todayDateString(), []);
    const filteredProjectStatuses = useMemo(
        () => statusRows.filter((project) => matchesFilter(project, selectedStatusFilter, today)),
        [selectedStatusFilter, statusRows, today],
    );

    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <div className="mb-6">
                <h1 className="text-xl font-semibold text-gray-900">{pageTitle}</h1>
                <p className="mt-0.5 text-sm text-gray-500">{pageSubtitle}</p>
            </div>

            {/* KPI Cards */}
            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                {kpis.map((kpi) => {
                    const filter = filterByKpiLabel[kpi.label] ?? 'all';

                    return (
                        <DashboardKpiCard
                            key={kpi.label}
                            label={kpi.label}
                            value={kpi.value}
                            color={kpi.color}
                            active={selectedStatusFilter === filter}
                            onClick={() => setSelectedStatusFilter(filter)}
                        />
                    );
                })}
            </div>

            {/* Row 1: Project Statuses + Project Status Overview */}
            <div className="mb-6 grid gap-4 lg:grid-cols-5">
                {/* Project Statuses */}
                <div className="rounded-xl border border-gray-200 bg-white shadow-sm lg:col-span-3">
                    <div className="border-b border-gray-100 px-5 py-4">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 className="text-sm font-semibold text-gray-900">Project Statuses</h3>
                                <p className="text-xs text-gray-500">Priority projects - click to view details</p>
                            </div>
                            <p className="text-xs font-medium text-gray-500">Showing: {filterLabel[selectedStatusFilter]}</p>
                        </div>
                    </div>
                    {!filteredProjectStatuses.length ? (
                        <div className="py-12 text-center text-sm text-gray-400">No projects found for this filter.</div>
                    ) : (
                        <div className="divide-y divide-gray-100">
                            {filteredProjectStatuses.slice(0, 8).map((p) => (
                                <Link key={p.id} href={route('projects.show', p.id)} className="block px-5 py-3 hover:bg-gray-50">
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-blue-600">{p.title}</p>
                                            <p className="mt-0.5 flex flex-wrap gap-2 text-xs text-gray-500">
                                                {p.department && <span>{p.department}</span>}
                                                {p.coordinator && <span>- {p.coordinator}</span>}
                                                {p.deadline && <span>- Due {p.deadline}</span>}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
                                            {p.priority && (
                                                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${priorityColor(p.priority)}`}>
                                                    {p.priority.charAt(0).toUpperCase() + p.priority.slice(1)}
                                                </span>
                                            )}
                                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${statusColor(p.status)}`}>
                                                {humanizeStatus(p.status)}
                                            </span>
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>

                {/* Project Status Overview */}
                <div className="rounded-xl border border-gray-200 bg-white shadow-sm lg:col-span-2">
                    <div className="border-b border-gray-100 px-5 py-4">
                        <h3 className="text-sm font-semibold text-gray-900">Project Status Overview</h3>
                        <p className="text-xs text-gray-500">Current completion rate</p>
                    </div>
                    <div className="flex flex-col items-center p-5">
                        <TaskStatusDonut data={statusData} />
                        <div className="mt-4 grid w-full grid-cols-2 gap-3">
                            <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-center">
                                <p className="text-lg font-bold text-emerald-700">{statusData.find(d => d.name === 'Completed')?.value ?? 0}</p>
                                <p className="text-xs text-emerald-600">Completed</p>
                            </div>
                            <div className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-center">
                                <p className="text-lg font-bold text-gray-700">{statusData.find(d => d.name === 'In Progress')?.value ?? 0}</p>
                                <p className="text-xs text-gray-500">In Progress</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Row 2: Completion Analytics */}
            {completionByMonth.some((m) => m.completed > 0) && (
                <div className="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h3 className="mb-1 text-sm font-semibold text-gray-900">Completion Analytics</h3>
                    <p className="text-xs text-gray-500">Last 3 months performance</p>
                    <div className="mt-3"><TaskLineupChart data={completionByMonth} /></div>
                </div>
            )}

            {/* Module Cards */}
            {modules.length > 0 && (
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {modules.map((mod) => (
                        <ModuleCard key={mod.title} {...mod} />
                    ))}
                </div>
            )}
        </AuthenticatedLayout>
    );
}
