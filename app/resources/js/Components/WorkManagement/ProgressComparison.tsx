import { CheckCircle2 } from 'lucide-react';

export default function ProgressComparison({ expected = [], completed = [] }: { expected?: string[]; completed?: string[] }) {
    const expectedItems = expected.length ? expected : ['Project setup', 'Task execution', 'Final review'];
    const completedItems = completed.length ? completed : [];
    const progress = expectedItems.length ? Math.round((completedItems.length / expectedItems.length) * 100) : 0;

    return (
        <section className="mt-6">
            <h2 className="mb-3 text-base font-semibold text-gray-950">Progress Report</h2>
            <div className="grid gap-4 md:grid-cols-2">
                <div className="rounded-lg border border-gray-200 p-4">
                    <h3 className="mb-2 text-sm font-medium text-gray-500">Expected Tasks</h3>
                    <ul className="space-y-2">
                        {expectedItems.map((item) => <li key={item} className="flex items-center gap-2 text-sm text-gray-700"><span className="h-2 w-2 rounded-full bg-gray-400" />{item}</li>)}
                    </ul>
                </div>
                <div className="rounded-lg border border-gray-200 p-4">
                    <h3 className="mb-2 text-sm font-medium text-green-600">Completed Tasks</h3>
                    {completedItems.length ? <ul className="space-y-2">{completedItems.map((item) => <li key={item} className="flex items-center gap-2 text-sm text-gray-700"><CheckCircle2 className="h-4 w-4 text-green-600" />{item}</li>)}</ul> : <p className="text-sm text-gray-500">No completed comparison data yet.</p>}
                    <div className="mt-4 border-t border-gray-200 pt-4 text-sm text-gray-500">Progress: <span className="font-semibold text-gray-950">{progress}%</span></div>
                </div>
            </div>
        </section>
    );
}
