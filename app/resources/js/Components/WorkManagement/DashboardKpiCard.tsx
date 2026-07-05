type DashboardKpiCardProps = {
    label: string;
    value: number;
    color?: string;
    active?: boolean;
    onClick?: () => void;
};

export default function DashboardKpiCard({ label, value, color = 'blue', active = false, onClick }: DashboardKpiCardProps) {
    const colorMap: Record<string, string> = {
        green: 'border-green-200 bg-green-50',
        blue: 'border-blue-200 bg-blue-50',
        amber: 'border-amber-200 bg-amber-50',
        red: 'border-red-200 bg-red-50',
        purple: 'border-purple-200 bg-purple-50',
        gray: 'border-gray-200 bg-gray-50',
    };
    const valueColor: Record<string, string> = {
        green: 'text-green-700',
        blue: 'text-blue-700',
        amber: 'text-amber-700',
        red: 'text-red-700',
        purple: 'text-purple-700',
        gray: 'text-gray-700',
    };
    const bg = colorMap[color] ?? colorMap.blue;
    const vc = valueColor[color] ?? valueColor.blue;
    const className = `rounded-xl border ${bg} p-4 text-left transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${active ? 'ring-2 ring-blue-500 ring-offset-2' : ''} ${onClick ? 'cursor-pointer hover:shadow-sm' : ''}`;

    if (onClick) {
        return (
            <button type="button" className={className} onClick={onClick} aria-pressed={active}>
                <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</p>
                <p className={`mt-1 text-3xl font-bold ${vc}`}>{value}</p>
            </button>
        );
    }

    return (
        <div className={className}>
            <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</p>
            <p className={`mt-1 text-3xl font-bold ${vc}`}>{value}</p>
        </div>
    );
}
