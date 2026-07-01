import { Bell, X } from 'lucide-react';

export default function NotificationPanel({ open = false, onClose }: { open?: boolean; onClose?: () => void }) {
    if (!open) return null;
    return (
        <aside className="fixed right-0 top-16 z-40 h-[calc(100vh-4rem)] w-96 overflow-y-auto border-l border-gray-200 bg-white shadow-xl">
            <div className="p-4">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-lg font-semibold text-gray-950">Notifications</h2>
                    <button onClick={onClose} className="rounded-lg p-2 hover:bg-gray-100"><X className="h-5 w-5" /></button>
                </div>
                <div className="space-y-2">
                    {['Follow-up required on active work', 'You have a recent assignment'].map((message) => (
                        <div key={message} className="rounded-lg border border-gray-200 bg-gray-50 p-3">
                            <div className="flex gap-2"><Bell className="mt-0.5 h-4 w-4 text-gray-900" /><p className="text-sm text-gray-700">{message}</p></div>
                        </div>
                    ))}
                </div>
            </div>
        </aside>
    );
}
