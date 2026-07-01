import { ResponsiveContainer, PieChart, Pie, Cell, Tooltip, Legend } from 'recharts';

export default function TaskStatusDonut({ data }: { data: { name: string; value: number; color: string }[] }) {
    const total = data.reduce((sum, d) => sum + d.value, 0);
    if (total === 0) {
        return (
            <div className="flex h-64 items-center justify-center text-sm text-gray-400">No task data available.</div>
        );
    }

    return (
        <ResponsiveContainer width="100%" height={256}>
            <PieChart>
                <Pie
                    data={data}
                    cx="50%"
                    cy="50%"
                    innerRadius={60}
                    outerRadius={90}
                    paddingAngle={4}
                    dataKey="value"
                    nameKey="name"
                >
                    {data.map((entry, i) => (
                        <Cell key={i} fill={entry.color} />
                    ))}
                </Pie>
                <Tooltip formatter={(v) => (typeof v === 'number' ? v : 0)} />
                <Legend />
            </PieChart>
        </ResponsiveContainer>
    );
}
