import axios, { AxiosError } from 'axios';
import { useState } from 'react';

export type ComparisonItem = {
    requirement: string;
    status: string;
    matched_deliverable: string | null;
    notes: string;
};

export type ComparisonResult = {
    status: string;
    completion_percentage: number;
    summary: string | null;
    items: ComparisonItem[];
    expected_items?: string[];
    completed_items?: string[];
    partial_items?: string[];
    pending_items?: string[];
    recommendations?: string[];
    error_message: string | null;
    created_at: string | null;
};

type ComparisonRunResponse = Partial<ComparisonResult> & {
    isConfigured?: boolean;
    errors?: string[];
    message?: string;
};

const statusBadgeClass: Record<string, string> = {
    completed: 'bg-green-100 text-green-800',
    partially_completed: 'bg-yellow-100 text-yellow-800',
    partial: 'bg-yellow-100 text-yellow-800',
    missing: 'bg-red-100 text-red-800',
    unclear: 'bg-gray-100 text-gray-800',
    config_missing: 'bg-gray-100 text-gray-800',
    not_configured: 'bg-gray-100 text-gray-800',
    failed: 'bg-red-100 text-red-800',
    error: 'bg-red-100 text-red-800',
    no_requirements: 'bg-blue-100 text-blue-800',
    no_deliverables: 'bg-blue-100 text-blue-800',
    no_completion: 'bg-blue-100 text-blue-800',
    extraction_failed: 'bg-orange-100 text-orange-800',
    pending: 'bg-gray-100 text-gray-800',
};

const statusLabel: Record<string, string> = {
    completed: 'Completed',
    partially_completed: 'Partially Completed',
    partial: 'Partially Completed',
    missing: 'Missing',
    unclear: 'Unclear',
    config_missing: 'Not Configured',
    not_configured: 'Not Configured',
    failed: 'Failed',
    error: 'Error',
    no_requirements: 'No Requirements',
    no_deliverables: 'Awaiting Deliverables',
    no_completion: 'No Completion',
    extraction_failed: 'Extraction Failed',
    pending: 'Pending',
};

const toStringArray = (value: unknown): string[] => Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string' && item.trim() !== '') : [];

export const normalizeComparisonResult = (data: ComparisonRunResponse): ComparisonResult => ({
    status: data.status ?? 'error',
    completion_percentage: Number(data.completion_percentage ?? 0),
    summary: data.summary ?? data.message ?? null,
    items: Array.isArray(data.items) ? data.items : [],
    expected_items: toStringArray(data.expected_items),
    completed_items: toStringArray(data.completed_items),
    partial_items: toStringArray(data.partial_items),
    pending_items: toStringArray(data.pending_items),
    recommendations: toStringArray(data.recommendations),
    error_message: data.error_message ?? (Array.isArray(data.errors) ? data.errors.join(' ') : null),
    created_at: data.created_at ?? null,
});

const requestErrorMessage = (error: unknown): string => {
    if (!axios.isAxiosError(error)) {
        return 'Comparison failed. Please try again.';
    }

    const axiosError = error as AxiosError<{ message?: string; error?: string }>;
    const status = axiosError.response?.status;

    if (status === 401 || status === 403) {
        return 'You are not authorized to run this comparison.';
    }

    if (status === 429) {
        return 'Comparison is rate limited. Please wait a moment and try again.';
    }

    if (status && status >= 500) {
        console.error('AI comparison request failed.', { status });
        return 'Comparison failed on the server. Please try again later.';
    }

    return axiosError.response?.data?.message ?? axiosError.response?.data?.error ?? 'Comparison failed. Please try again.';
};

function ListSection({ title, items, tone = 'gray' }: { title: string; items?: string[]; tone?: 'green' | 'yellow' | 'red' | 'gray' }) {
    if (!items?.length) return null;

    const markerClass = {
        green: 'bg-green-500',
        yellow: 'bg-yellow-500',
        red: 'bg-red-500',
        gray: 'bg-gray-400',
    }[tone];

    return (
        <div className="rounded-lg border border-gray-200 bg-white p-3">
            <h3 className="text-xs font-semibold uppercase text-gray-500">{title}</h3>
            <ul className="mt-2 space-y-2">
                {items.map((item) => (
                    <li key={item} className="flex gap-2 text-sm text-gray-700"><span className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${markerClass}`} />{item}</li>
                ))}
            </ul>
        </div>
    );
}

export default function RequirementDeliverableComparison({
    isConfigured,
    result,
    runUrl,
    clearUrl: _clearUrl,
}: {
    isConfigured: boolean;
    result: ComparisonResult | null;
    runUrl: string;
    clearUrl: string;
}) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [currentResult, setCurrentResult] = useState<ComparisonResult | null>(result);
    const [isAiConfigured, setIsAiConfigured] = useState(isConfigured);

    async function runComparison() {
        if (!runUrl) {
            setError('Comparison is not available for this item.');
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const response = await axios.post<ComparisonRunResponse>(runUrl, {});
            const nextResult = normalizeComparisonResult(response.data);

            setIsAiConfigured(response.data.isConfigured ?? (nextResult.status !== 'config_missing' && nextResult.status !== 'not_configured'));
            setCurrentResult(nextResult);
        } catch (requestError) {
            setError(requestErrorMessage(requestError));
        } finally {
            setLoading(false);
        }
    }

    if (!isAiConfigured) {
        return (
            <section className="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4">
                <h2 className="text-base font-semibold text-gray-950">Requirements &amp; Deliverables Comparison</h2>
                <p className="mt-2 text-sm text-gray-500">
                    AI comparison not configured. Add AI credentials to <code className="rounded bg-gray-200 px-1 py-0.5 text-xs">.env</code> to enable.
                </p>
            </section>
        );
    }

    if (!currentResult) {
        return (
            <section className="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4" aria-busy={loading}>
                <h2 className="text-base font-semibold text-gray-950">Requirements &amp; Deliverables Comparison</h2>
                <p className="mt-2 text-sm text-gray-500">No comparison has been run yet.</p>
                {error && <p className="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}
                <button
                    type="button"
                    onClick={runComparison}
                    disabled={loading}
                    className="mt-3 inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 disabled:opacity-60"
                >
                    {loading ? 'Running...' : 'Run Comparison'}
                </button>
            </section>
        );
    }

    const badgeClass = statusBadgeClass[currentResult.status] ?? 'bg-gray-100 text-gray-800';
    const label = statusLabel[currentResult.status] ?? currentResult.status;

    return (
        <section className="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4" aria-busy={loading}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-gray-950">Requirements &amp; Deliverables Comparison</h2>
                    {currentResult.created_at && <p className="mt-1 text-xs text-gray-400">Last run: {currentResult.created_at}</p>}
                </div>
                <div className="flex items-center gap-2">
                    <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${badgeClass}`}>{label}</span>
                    <span className="text-sm font-semibold text-gray-900">{currentResult.completion_percentage.toFixed(1)}%</span>
                </div>
            </div>

            <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-200">
                <div
                    className={`h-full rounded-full transition-all ${
                        currentResult.completion_percentage >= 80
                            ? 'bg-green-500'
                            : currentResult.completion_percentage >= 40
                              ? 'bg-yellow-500'
                              : 'bg-red-500'
                    }`}
                    style={{ width: `${Math.min(currentResult.completion_percentage, 100)}%` }}
                />
            </div>

            <div className="mt-4 rounded-lg border border-gray-200 bg-white p-3">
                <h3 className="text-sm font-semibold text-gray-950">AI Summary</h3>
                <p className="mt-1 text-sm text-gray-700">{currentResult.summary || 'Run AI comparison after uploading requirement and deliverable/evidence files.'}</p>
            </div>

            {error && <p className="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}
            {currentResult.error_message && (
                <p className="mt-3 rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-700">{currentResult.error_message}</p>
            )}

            <div className="mt-4 grid gap-3 lg:grid-cols-2">
                <ListSection title="Completed Items" items={currentResult.completed_items} tone="green" />
                <ListSection title="Partially Completed Items" items={currentResult.partial_items} tone="yellow" />
                <ListSection title="Pending Items" items={currentResult.pending_items} tone="red" />
                <ListSection title="Recommendations" items={currentResult.recommendations} tone="gray" />
            </div>

            {currentResult.items.length > 0 && (
                <div className="mt-4 space-y-2">
                    {currentResult.items.map((item, idx) => (
                        <div key={idx} className="rounded-lg border border-gray-200 bg-white p-3">
                            <div className="flex items-start justify-between gap-2">
                                <div className="flex-1">
                                    <p className="text-sm font-medium text-gray-900">{item.requirement}</p>
                                    {item.matched_deliverable && (
                                        <p className="mt-1 text-xs text-gray-500">
                                            Matched: {item.matched_deliverable}
                                        </p>
                                    )}
                                    {item.notes && <p className="mt-1 text-xs text-gray-600">{item.notes}</p>}
                                </div>
                                <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold ${statusBadgeClass[item.status] ?? 'bg-gray-100 text-gray-800'}`}>
                                    {statusLabel[item.status] ?? item.status}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <div className="mt-4 flex gap-2">
                <button
                    type="button"
                    onClick={runComparison}
                    disabled={loading}
                    className="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-60"
                >
                    {loading ? 'Running...' : 'Re-run Comparison'}
                </button>
            </div>
        </section>
    );
}

