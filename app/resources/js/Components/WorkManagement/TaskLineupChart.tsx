import { ResponsiveContainer, BarChart, Bar, XAxis, YAxis, Tooltip, CartesianGrid } from 'recharts';

export default function TaskLineupChart({ data }: { data: { month: string; completed: number }[] }) {
    if (!data.length || data.every((d) => d.completed === 0)) {
        return (
            <div className="flex h-64 items-center justify-center text-sm text-gray-400">No completion data for the last three months.</div>
        );
    }

    return (
        <ResponsiveContainer width="100%" height={256}>
            <BarChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                <XAxis dataKey="month" tick={{ fontSize: 11 }} />
                <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
                <Tooltip />
                <Bar dataKey="completed" fill="#3b82f6" radius={[4, 4, 0, 0]} />
            </BarChart>
        </ResponsiveContainer>
    );
}
