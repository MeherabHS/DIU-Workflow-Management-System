import { FullPageShell, Spinner } from '@/Components/WorkManagement/AppLoadingFallback';
import { Component, type ErrorInfo, type ReactNode } from 'react';

type AppErrorBoundaryProps = {
    children: ReactNode;
    homeHref: string;
    homeLabel: string;
};

type AppErrorBoundaryState = {
    hasError: boolean;
};

export default class AppErrorBoundary extends Component<AppErrorBoundaryProps, AppErrorBoundaryState> {
    state: AppErrorBoundaryState = { hasError: false };

    static getDerivedStateFromError(): AppErrorBoundaryState {
        return { hasError: true };
    }

    componentDidCatch(error: Error, info: ErrorInfo): void {
        if (import.meta.env.DEV) {
            console.error('DIUS frontend error boundary caught an error.', error, info);
        }
    }

    render() {
        if (!this.state.hasError) {
            return this.props.children;
        }

        return (
            <FullPageShell>
                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-blue-700 ring-1 ring-blue-100">
                    <Spinner />
                </div>
                <div className="mt-5 text-center">
                    <h1 className="text-lg font-semibold text-slate-950">Something needs a quick refresh.</h1>
                    <p className="mt-2 text-sm leading-6 text-slate-600">We&apos;re wiring things together. Please refresh the page or try again in a moment.</p>
                </div>
                <div className="mt-6 grid gap-3 sm:grid-cols-2">
                    <button
                        type="button"
                        onClick={() => window.location.reload()}
                        className="inline-flex items-center justify-center rounded-md bg-blue-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:ring-offset-2"
                    >
                        Refresh Page
                    </button>
                    <a
                        href={this.props.homeHref}
                        className="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:ring-offset-2"
                    >
                        {this.props.homeLabel}
                    </a>
                </div>
            </FullPageShell>
        );
    }
}