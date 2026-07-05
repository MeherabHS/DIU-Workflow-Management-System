import { CheckCircle2 } from 'lucide-react';
import { ComparisonResult } from './RequirementDeliverableComparison';

type ProgressComparisonProps = {
    expected?: string[];
    completed?: string[];
    result?: ComparisonResult | null;
};

function statusText(status?: string | null) {
    const labels: Record<string, string> = {
        no_requirements: 'No Requirements',
        no_deliverables: 'Awaiting Deliverables',
        no_completion: 'Awaiting Deliverables',
        partially_completed: 'Partially Completed',
        partial: 'Partially Completed',
        completed: 'Completed',
        missing: 'Needs Review',
        unclear: 'Needs Review',
        failed: 'Needs Review',
        extraction_failed: 'Needs Review',
    };

    return labels[status || ''] ?? 'Needs Review';
}

function ListPanel({ title, items, empty, tone = 'gray' }: { title: string; items: string[]; empty: string; tone?: 'green' | 'yellow' | 'red' | 'gray' }) {
    const markerClass = {
        green: 'bg-green-500',
        yellow: 'bg-yellow-500',
        red: 'bg-red-500',
        gray: 'bg-gray-400',
    }[tone];

    return (
        <div className="rounded-lg border border-gray-200 p-4">
            <h3 className="mb-2 text-sm font-medium text-gray-700">{title}</h3>
            {items.length ? (
                <ul className="space-y-2">
                    {items.map((item) => <li key={item} className="flex items-start gap-2 text-sm text-gray-700"><span className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${markerClass}`} />{item}</li>)}
                </ul>
            ) : (
                <p className="text-sm text-gray-500">{empty}</p>
            )}
        </div>
    );
}

function itemsByStatus(result: ComparisonResult, statuses: string[]) {
    return result.items
        .filter((item) => statuses.includes(item.status))
        .map((item) => item.requirement || item.matched_deliverable || '')
        .filter(Boolean);
}

export default function ProgressComparison({ expected = [], completed = [], result = null }: ProgressComparisonProps) {
    if (result) {
        const expectedItems = result.expected_items?.length ? result.expected_items : result.items.map((item) => item.requirement).filter(Boolean);
        const completedItems = result.completed_items?.length ? result.completed_items : itemsByStatus(result, ['completed']);
        const partialItems = result.partial_items?.length ? result.partial_items : itemsByStatus(result, ['partially_completed', 'partial']);
        const pendingItems = result.pending_items?.length ? result.pending_items : itemsByStatus(result, ['missing', 'unclear']);
        const reviewItems = [...partialItems, ...pendingItems];
        const progress = Number(result.completion_percentage ?? 0);

        return (
            <section className="mt-6">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-base font-semibold text-gray-950">Progress Report</h2>
                    <div className="flex items-center gap-2 text-sm text-gray-600">
                        <span>{statusText(result.status)}</span>
                        <span className="font-semibold text-gray-950">{progress.toFixed(1)}%</span>
                    </div>
                </div>
                <div className="mb-4 h-2 w-full overflow-hidden rounded-full bg-gray-200">
                    <div className="h-full rounded-full bg-gray-900 transition-all" style={{ width: `${Math.min(progress, 100)}%` }} />
                </div>
                <div className="grid gap-4 lg:grid-cols-3">
                    <ListPanel title="Expected / Required Work" items={expectedItems} empty="No requirement items were extracted yet." />
                    <ListPanel title="Completed / Evidence Found" items={completedItems} empty="No completed evidence has been identified yet." tone="green" />
                    <ListPanel title="Pending / Needs Review" items={reviewItems} empty="No pending items were identified." tone="red" />
                </div>
            </section>
        );
    }

    if (!expected.length && !completed.length) {
        return (
            <section className="mt-6">
                <h2 className="mb-3 text-base font-semibold text-gray-950">Progress Report</h2>
                <div className="rounded-lg border border-gray-200 p-4 text-sm text-gray-500">Run AI comparison after uploading requirement and deliverable/evidence files.</div>
            </section>
        );
    }

    const progress = expected.length ? Math.round((completed.length / expected.length) * 100) : 0;

    return (
        <section className="mt-6">
            <h2 className="mb-3 text-base font-semibold text-gray-950">Progress Report</h2>
            <div className="grid gap-4 md:grid-cols-2">
                <div className="rounded-lg border border-gray-200 p-4">
                    <h3 className="mb-2 text-sm font-medium text-gray-500">Expected Tasks</h3>
                    <ul className="space-y-2">
                        {expected.map((item) => <li key={item} className="flex items-center gap-2 text-sm text-gray-700"><span className="h-2 w-2 rounded-full bg-gray-400" />{item}</li>)}
                    </ul>
                </div>
                <div className="rounded-lg border border-gray-200 p-4">
                    <h3 className="mb-2 text-sm font-medium text-green-600">Completed Tasks</h3>
                    {completed.length ? <ul className="space-y-2">{completed.map((item) => <li key={item} className="flex items-center gap-2 text-sm text-gray-700"><CheckCircle2 className="h-4 w-4 text-green-600" />{item}</li>)}</ul> : <p className="text-sm text-gray-500">No completed comparison data yet.</p>}
                    <div className="mt-4 border-t border-gray-200 pt-4 text-sm text-gray-500">Progress: <span className="font-semibold text-gray-950">{progress}%</span></div>
                </div>
            </div>
        </section>
    );
}
