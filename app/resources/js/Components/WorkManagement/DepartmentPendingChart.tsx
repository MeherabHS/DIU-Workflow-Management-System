import { ResponsiveContainer, BarChart, Bar, XAxis, YAxis, Tooltip, CartesianGrid } from 'recharts';

export default function DepartmentPendingChart({ data }: { data: { department: string; count: number }[] }) {
    if (!data.length) {
        return (
            <div className="flex h-64 items-center justify-center text-sm text-gray-400">No pending tasks by department.</div>
        );
    }

    return (
        <ResponsiveContainer width="100%" height={256}>
            <BarChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                <XAxis dataKey="department" tick={{ fontSize: 11 }} interval={0} angle={-30} textAnchor="end" height={50} />
                <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
                <Tooltip />
                <Bar dataKey="count" fill="#f59e0b" radius={[4, 4, 0, 0]} />
            </BarChart>
        </ResponsiveContainer>
    );
}
