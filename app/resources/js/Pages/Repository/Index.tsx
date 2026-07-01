import SearchFilterBar from '@/Components/WorkManagement/SearchFilterBar';
import StatusBadge from '@/Components/WorkManagement/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { dateText } from '@/lib/utils';
import { Paginator, RepositoryEntry } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useCallback } from 'react';

export default function Index({
    entries,
    filters,
    statuses,
    types,
    pageTitle = 'Repository Tracker',
    pageSubtitle = 'Permanent institutional repository records and timeline tracking.',
    primaryAction,
}: {
    entries: Paginator<RepositoryEntry>;
    filters?: { search?: string; status?: string; type?: string };
    statuses?: { value: string; label: string }[];
    types?: string[];
    pageTitle?: string;
    pageSubtitle?: string;
    primaryAction?: string | null;
}) {
    const { url } = usePage();

    const navigate = useCallback((params: Record<string, string | undefined>) => {
        const currentUrl = new URL(url, window.location.origin);
        const existing: Record<string, string> = {};
        currentUrl.searchParams.forEach((v, k) => { existing[k] = v; });
        const merged = { ...existing, ...params };
        Object.keys(merged).forEach((k) => { if (!merged[k]) delete merged[k]; });
        router.get(route('repository.index'), merged, { preserveState: true, replace: true });
    }, [url]);

    const handleSearch = useCallback((value: string) => {
        navigate({ search: value || undefined });
    }, [navigate]);

    const handleFilterChange = useCallback((status: string) => {
        navigate({ status: status || undefined });
    }, [navigate]);

    const handleTypeChange = useCallback((type: string) => {
        navigate({ type: type || undefined });
    }, [navigate]);

    const currentFilters = filters ?? {};

    return (
        <AuthenticatedLayout>
            <Head title={pageTitle} />
            <div className="mb-6">
                <h1 className="text-2xl font-bold tracking-tight text-gray-950">{pageTitle}</h1>
                <p className="mt-1 text-sm text-gray-500">{pageSubtitle}</p>
            </div>
            <SearchFilterBar
                actionHref={primaryAction ? route('repository.create') : null}
                actionLabel="Create Repository Entry"
                extraFilters
                onSearch={handleSearch}
                onFilterChange={handleFilterChange}
                onTypeChange={handleTypeChange}
                initialSearch={currentFilters.search ?? ''}
                initialStatus={currentFilters.status ?? ''}
                initialType={currentFilters.type ?? ''}
                statusOptions={statuses}
                typeOptions={types}
            />
            {!entries.data.length ? <div className="rounded-xl border border-gray-200 bg-white p-8 text-center text-sm font-medium text-gray-500">No repository records found.</div> : (
                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div className="divide-y divide-gray-100">
                        {entries.data.map((entry) => (
                            <article key={entry.id} className="grid gap-4 p-5 lg:grid-cols-[1fr_auto] lg:items-center">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="text-lg font-semibold text-gray-950">{entry.title}</h2>
                                        <StatusBadge value={entry.status} />
                                    </div>
                                    <p className="mt-2 line-clamp-2 text-sm leading-6 text-gray-500">{entry.description || 'No description provided.'}</p>
                                    <div className="mt-2 flex flex-wrap items-center gap-2">
                                        <span><b className="text-gray-900">Type:</b> {entry.type || 'Not set'}</span>
                                        {entry.project && (
                                            <span>
                                                <b className="text-gray-900">Project:</b>{' '}
                                                <Link href={route('projects.show', entry.project.id)} className="text-blue-600 hover:underline">
                                                    {entry.project.title}
                                                </Link>
                                            </span>
                                        )}
                                    </div>
                                    <div className="mt-2 grid gap-3 text-sm text-gray-600 sm:grid-cols-2 lg:grid-cols-4">
                                        <span><b className="text-gray-900">Department/Client:</b> {entry.department?.name || entry.client_or_office || 'Not set'}</span>
                                        <span><b className="text-gray-900">Deadline:</b> {dateText(entry.deadline)}</span>
                                        <span><b className="text-gray-900">Responsible:</b> {entry.responsible_user?.name || entry.responsibleUser?.name || 'Not set'}</span>
                                        <span><b className="text-gray-900">Created By:</b> {entry.creator?.name || 'Not set'}</span>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2 lg:justify-end">
                                    <Link href={route('repository.show', entry.id)} className="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 shadow-sm hover:bg-gray-50">View</Link>
                                    <Link href={route('repository.edit', entry.id)} className="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 shadow-sm hover:bg-gray-50">Edit</Link>
                                </div>
                            </article>
                        ))}
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
