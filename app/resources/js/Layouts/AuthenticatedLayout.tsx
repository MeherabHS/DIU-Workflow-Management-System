import Header from '@/Components/WorkManagement/Header';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useEffect, useState } from 'react';

export default function Authenticated({ children }: PropsWithChildren<{ header?: ReactNode }>) {
    const { flash, loginWorkSummary } = usePage<PageProps>().props;
    const [showToast, setShowToast] = useState(!!loginWorkSummary);

    useEffect(() => {
        if (showToast) {
            const timer = setTimeout(() => setShowToast(false), 8000);
            return () => clearTimeout(timer);
        }
    }, [showToast]);

    return (
        <div className="min-h-screen bg-gray-50 text-gray-950">
            <Header title="DIUS Management Portal" />
            <main className="py-6">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    {flash?.status && <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{flash.status}</div>}
                    {children}
                </div>
            </main>

            {showToast && loginWorkSummary && (
                <div className="fixed bottom-6 right-6 z-50 max-w-sm animate-fade-in rounded-xl border border-blue-200 bg-blue-50 p-4 shadow-lg">
                    <div className="flex items-start gap-3">
                        <div className="flex-1 text-sm leading-relaxed text-blue-900">{loginWorkSummary}</div>
                        <button
                            onClick={() => setShowToast(false)}
                            className="shrink-0 rounded p-0.5 text-blue-400 hover:text-blue-700"
                            aria-label="Dismiss"
                        >
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
