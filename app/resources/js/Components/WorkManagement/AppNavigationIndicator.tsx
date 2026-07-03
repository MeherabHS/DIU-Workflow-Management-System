import AppLoadingFallback from '@/Components/WorkManagement/AppLoadingFallback';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export default function AppNavigationIndicator() {
    const [isLoading, setIsLoading] = useState(false);
    const [showPanel, setShowPanel] = useState(false);
    const panelTimer = useRef<number | null>(null);

    useEffect(() => {
        const start = () => {
            setIsLoading(true);
            if (panelTimer.current) {
                window.clearTimeout(panelTimer.current);
            }
            panelTimer.current = window.setTimeout(() => setShowPanel(true), 450);
        };

        const finish = () => {
            setIsLoading(false);
            setShowPanel(false);
            if (panelTimer.current) {
                window.clearTimeout(panelTimer.current);
                panelTimer.current = null;
            }
        };

        const removeStart = router.on('start', start);
        const removeFinish = router.on('finish', finish);

        return () => {
            removeStart();
            removeFinish();
            if (panelTimer.current) {
                window.clearTimeout(panelTimer.current);
            }
        };
    }, []);

    if (!isLoading) {
        return null;
    }

    return (
        <>
            <AppLoadingFallback compact />
            {showPanel && (
                <div className="pointer-events-none fixed inset-x-0 top-5 z-[70] flex justify-center px-4">
                    <div className="flex items-center gap-3 rounded-lg border border-blue-100 bg-white px-4 py-3 text-sm font-medium text-slate-700 shadow-sm">
                        <div className="h-4 w-4 animate-spin rounded-full border-2 border-blue-100 border-t-blue-700" aria-hidden="true" />
                        <span>Setting up your workspace...</span>
                    </div>
                </div>
            )}
        </>
    );
}