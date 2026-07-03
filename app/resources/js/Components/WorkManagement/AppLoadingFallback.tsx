import { type ReactNode } from 'react';

type AppLoadingFallbackProps = {
    title?: string;
    message?: string;
    compact?: boolean;
};

export default function AppLoadingFallback({
    title = 'Setting up your workspace...',
    message = 'Please wait while DIUS Management Portal prepares your session.',
    compact = false,
}: AppLoadingFallbackProps) {
    if (compact) {
        return (
            <div className="fixed inset-x-0 top-0 z-[70] pointer-events-none">
                <div className="h-1 w-full overflow-hidden bg-blue-100">
                    <div className="h-full w-1/3 animate-[dius-loading-bar_1.1s_ease-in-out_infinite] bg-blue-700" />
                </div>
            </div>
        );
    }

    return (
        <FullPageShell>
            <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-blue-700 ring-1 ring-blue-100">
                <Spinner />
            </div>
            <div className="mt-5 text-center">
                <h1 className="text-lg font-semibold text-slate-950">{title}</h1>
                <p className="mt-2 text-sm leading-6 text-slate-600">{message}</p>
                <p className="mt-3 text-xs font-medium text-blue-800">Starting the workspace... Free prototype services may take a few seconds to wake up.</p>
            </div>
        </FullPageShell>
    );
}

export function FullPageShell({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10 text-slate-950">
            <div className="w-full max-w-md rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
                <div className="mb-6 flex items-center justify-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-md bg-blue-950 text-sm font-bold text-white">D</div>
                    <div className="text-left">
                        <p className="text-sm font-semibold text-slate-950">DIUS Management Portal</p>
                        <p className="text-xs text-slate-500">Workspace management</p>
                    </div>
                </div>
                {children}
            </div>
        </div>
    );
}

export function Spinner() {
    return <div className="h-7 w-7 animate-spin rounded-full border-2 border-blue-200 border-t-blue-700" aria-hidden="true" />;
}