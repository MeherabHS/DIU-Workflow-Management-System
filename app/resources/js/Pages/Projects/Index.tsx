import ProjectCard from '@/Components/WorkManagement/ProjectCard';
import SearchFilterBar from '@/Components/WorkManagement/SearchFilterBar';
import WorkPageLayout from '@/Components/WorkManagement/WorkPageLayout';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { BaseUser, Paginator, Project } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useCallback } from 'react';

function coordinator(project: Project) {
    return project.active_primary_assignment?.coordinator || project.activePrimaryAssignment?.coordinator || null;
}

export function ProjectCards({ projects, assignedOnly = false }: { projects: Project[]; assignedOnly?: boolean }) {
    if (!projects.length) {
        return <div className="rounded-xl border border-gray-200 bg-white p-8 text-center text-sm font-medium text-gray-500">{assignedOnly ? 'No assigned projects found.' : 'No projects found.'}</div>;
    }

    return (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            {projects.map((project) => {
                const activeCoordinator = coordinator(project);
                const users = [activeCoordinator].filter(Boolean) as BaseUser[];
                return (
                    <ProjectCard
                        key={project.id}
                        title={project.title}
                        description={project.description}
                        status={project.status}
                        priority={project.priority}
                        dueDate={project.deadline}
                        users={users}
                        fileCount={0}
                        actions={[
                            { label: 'View', href: route('projects.show', project.id) },
                            ...(!assignedOnly ? [
                                { label: 'Edit', href: route('projects.edit', project.id) },
                                { label: 'Assign Coordinator', href: route('projects.assign-coordinator.edit', project.id) },
                                ...(activeCoordinator ? [{ label: 'Revoke Coordinator', href: route('projects.assign-coordinator.revoke', project.id), method: 'post' as const }] : []),
                            ] : []),
                            { label: 'View Tasks', href: route('project.tasks.index', project.id), primary: true },
                        ]}
                    />
                );
            })}
        </div>
    );
}

export default function Index({ projects, pageTitle = 'Projects', pageSubtitle = 'Create projects, assign coordinators, and track ownership.' }: { projects: Paginator<Project>; pageTitle?: string; pageSubtitle?: string }) {
    const { url } = usePage();

    const navigate = useCallback((params: Record<string, string | undefined>) => {
        // Merge with existing query params so search + filter work together
        const currentUrl = new URL(url, window.location.origin);
        const existing: Record<string, string | undefined> = {};
        currentUrl.searchParams.forEach((v, k) => { existing[k] = v; });
        const merged = { ...existing, ...params };
        // Remove undefined values
        Object.keys(merged).forEach((k) => { if (merged[k] === undefined) delete merged[k]; });
        router.get(route('projects.index'), merged, { preserveState: true, replace: true });
    }, [url]);

    const handleSearch = useCallback((value: string) => {
        navigate({ search: value || undefined });
    }, [navigate]);

    const handleFilterChange = useCallback((status: string) => {
        navigate({ status: status || undefined });
    }, [navigate]);

    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <WorkPageLayout title={pageTitle} subtitle={pageSubtitle}>
                <SearchFilterBar
                    actionHref={route('projects.create')}
                    actionLabel="Create Project"
                    onSearch={handleSearch}
                    onFilterChange={handleFilterChange}
                />
                <ProjectCards projects={projects.data} />
            </WorkPageLayout>
        </AuthenticatedLayout>
    );
}
