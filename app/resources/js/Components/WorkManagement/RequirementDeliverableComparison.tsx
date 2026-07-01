import { router } from '@inertiajs/react';
import { useState } from 'react';

type ComparisonItem = {
    requirement: string;
    status: string;
    matched_deliverable: string | null;
    notes: string;
};

type ComparisonResult = {
    status: string;
    completion_percentage: number;
    summary: string | null;
    items: ComparisonItem[];
    error_message: string | null;
    created_at: string | null;
};

const statusBadgeClass: Record<string, string> = {
    completed: 'bg-green-100 text-green-800',
    partially_completed: 'bg-yellow-100 text-yellow-800',
    missing: 'bg-red-100 text-red-800',
    unclear: 'bg-gray-100 text-gray-800',
    config_missing: 'bg-gray-100 text-gray-800',
    failed: 'bg-red-100 text-red-800',
    no_requirements: 'bg-blue-100 text-blue-800',
    no_deliverables: 'bg-blue-100 text-blue-800',
    extraction_failed: 'bg-orange-100 text-orange-800',
    pending: 'bg-gray-100 text-gray-800',
};

const statusLabel: Record<string, string> = {
    completed: 'Completed',
    partially_completed: 'Partially Completed',
    missing: 'Missing',
    unclear: 'Unclear',
    config_missing: 'Not Configured',
    failed: 'Failed',
    no_requirements: 'No Requirements',
    no_deliverables: 'No Deliverables',
    extraction_failed: 'Extraction Failed',
    pending: 'Pending',
};

export default function RequirementDeliverableComparison({
    isConfigured,
    result,
    runUrl,
    clearUrl,
}: {
    isConfigured: boolean;
    result: ComparisonResult | null;
    runUrl: string;
    clearUrl: string;
}) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function runComparison() {
        setLoading(true);
        setError(null);
        router.post(
            runUrl,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    // Inertia will reload the page with updated props
                },
                onError: (errors) => {
                    setError(Object.values(errors).join(' '));
                    setLoading(false);
                },
                onFinish: () => setLoading(false),
            }
        );
    }

    if (!isConfigured) {
        return (
            <section className="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4">
                <h2 className="text-base font-semibold text-gray-950">Requirements &amp; Deliverables Comparison</h2>
                <p className="mt-2 text-sm text-gray-500">
                    AI comparison not configured. Add AI credentials to <code className="rounded bg-gray-200 px-1 py-0.5 text-xs">.env</code> to enable.
                </p>
            </section>
        );
    }

    if (!result) {
        return (
            <section className="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4">
                <h2 className="text-base font-semibold text-gray-950">Requirements &amp; Deliverables Comparison</h2>
                <p className="mt-2 text-sm text-gray-500">No comparison has been run yet.</p>
                <button
                    onClick={runComparison}
                    disabled={loading}
                    className="mt-3 inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 disabled:opacity-60"
                >
                    {loading ? 'Running...' : 'Run Comparison'}
                </button>
            </section>
        );
    }

    const badgeClass = statusBadgeClass[result.status] ?? 'bg-gray-100 text-gray-800';
    const label = statusLabel[result.status] ?? result.status;

    return (
        <section className="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-gray-950">Requirements &amp; Deliverables Comparison</h2>
                    {result.created_at && <p className="mt-1 text-xs text-gray-400">Last run: {result.created_at}</p>}
                </div>
                <div className="flex items-center gap-2">
                    <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${badgeClass}`}>{label}</span>
                    <span className="text-sm font-semibold text-gray-900">{result.completion_percentage.toFixed(1)}%</span>
                </div>
            </div>

            {/* Progress bar */}
            <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-200">
                <div
                    className={`h-full rounded-full transition-all ${
                        result.completion_percentage >= 80
                            ? 'bg-green-500'
                            : result.completion_percentage >= 40
                              ? 'bg-yellow-500'
                              : 'bg-red-500'
                    }`}
                    style={{ width: `${Math.min(result.completion_percentage, 100)}%` }}
                />
            </div>

            {/* Summary */}
            {result.summary && <p className="mt-3 text-sm text-gray-700">{result.summary}</p>}

            {/* Error message */}
            {error && <p className="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}
            {result.error_message && (
                <p className="mt-3 rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-700">{result.error_message}</p>
            )}

            {/* Items */}
            {result.items.length > 0 && (
                <div className="mt-4 space-y-2">
                    {result.items.map((item, idx) => (
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

            {/* Actions */}
            <div className="mt-4 flex gap-2">
                <button
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
