import { ReactNode } from 'react';

type Props = {
    title: string;
    subtitle?: string | null;
    action?: ReactNode;
    children: ReactNode;
};

export default function WorkPageLayout({ title, subtitle, action, children }: Props) {
    return (
        <div className="space-y-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <h1 className="break-words text-2xl font-bold text-gray-950">{title}</h1>
                    {subtitle && <p className="mt-1 text-sm leading-6 text-gray-500">{subtitle}</p>}
                </div>
                {action && <div className="flex shrink-0 flex-wrap gap-2">{action}</div>}
            </div>
            {children}
        </div>
    );
}
