import { Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState, useEffect, useCallback } from 'react';

export default function SearchFilterBar({
    actionHref,
    actionLabel = 'New Task',
    extraFilters = false,
    onSearch,
    onFilterChange,
    onTypeChange,
    initialSearch = '',
    initialStatus = '',
    initialType = '',
    statusOptions,
    typeOptions,
}: {
    actionHref?: string | null;
    actionLabel?: string;
    extraFilters?: boolean;
    onSearch?: (value: string) => void;
    onFilterChange?: (status: string) => void;
    onTypeChange?: (type: string) => void;
    initialSearch?: string;
    initialStatus?: string;
    initialType?: string;
    statusOptions?: { value: string; label: string }[];
    typeOptions?: string[];
}) {
    const [search, setSearch] = useState(initialSearch);
    const [status, setStatus] = useState(initialStatus);
    const [type, setType] = useState(initialType);

    const debouncedSearch = useCallback(
        (value: string) => {
            const timer = setTimeout(() => onSearch?.(value), 300);
            return () => clearTimeout(timer);
        },
        [onSearch]
    );

    useEffect(() => {
        debouncedSearch(search);
    }, [search, debouncedSearch]);

    // Sync prop changes into local state (e.g. when URL changes externally)
    useEffect(() => { setSearch(initialSearch); }, [initialSearch]);
    useEffect(() => { setStatus(initialStatus); }, [initialStatus]);
    useEffect(() => { setType(initialType); }, [initialType]);

    const handleStatusChange = (value: string) => {
        setStatus(value);
        onFilterChange?.(value);
    };

    const handleTypeChange = (value: string) => {
        setType(value);
        onTypeChange?.(value);
    };

    // Default project status options
    const defaultStatusOptions: { value: string; label: string }[] = [
        { value: '', label: 'All Status' },
        { value: 'planned', label: 'Planned' },
        { value: 'active', label: 'Ongoing' },
        { value: 'submitted', label: 'Submitted' },
        { value: 'completed', label: 'Completed' },
        { value: 'archived', label: 'Archived' },
        { value: 'cancelled', label: 'Cancelled' },
    ];

    const statuses = statusOptions ?? defaultStatusOptions;

    return (
        <div className="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div className="flex flex-1 flex-col gap-2 sm:flex-row">
                <label className="relative flex-1">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-4 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                    />
                </label>
                <select
                    value={status}
                    onChange={(e) => handleStatusChange(e.target.value)}
                    className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                >
                    {statuses.map((opt) => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                </select>
                {extraFilters && (
                    <select
                        value={type}
                        onChange={(e) => handleTypeChange(e.target.value)}
                        className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"
                    >
                        <option value="">All Types</option>
                        <option value="not_set">Not set</option>
                        {(typeOptions ?? []).filter((t) => t !== 'not_set').map((t) => (
                            <option key={t} value={t}>{t}</option>
                        ))}
                    </select>
                )}
            </div>
            {actionHref && actionLabel && (
                <Link href={actionHref} className="inline-flex items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700">
                    <Plus className="h-4 w-4" />
                    {actionLabel}
                </Link>
            )}
        </div>
    );
}
