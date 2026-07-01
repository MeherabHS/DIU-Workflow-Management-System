import { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { X } from 'lucide-react';

export default function DetailModal({ title, description, onCloseHref, actions, children }: { title: string; description?: string | null; onCloseHref?: string; actions?: ReactNode; children: ReactNode }) {
    return (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-3 py-6 sm:px-4 sm:py-8">
            <motion.section initial={{ opacity: 0, scale: 0.96, y: 12 }} animate={{ opacity: 1, scale: 1, y: 0 }} transition={{ duration: 0.18 }} className="mx-auto w-full max-w-4xl rounded-xl bg-white shadow-2xl">
                <div className="p-4 sm:p-6">
                    <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0 flex-1">
                            <h1 className="break-words text-xl font-bold leading-7 text-gray-950 sm:text-2xl sm:leading-8">{title}</h1>
                            {description && <p className="mt-2 break-words text-sm leading-6 text-gray-500">{description}</p>}
                        </div>
                        <div className="flex shrink-0 flex-wrap items-center justify-end gap-2 sm:max-w-sm">
                            {actions}
                            {onCloseHref && (
                                <Link href={onCloseHref} aria-label="Close" className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100">
                                    <X className="h-5 w-5" />
                                </Link>
                            )}
                        </div>
                    </div>
                    <div className="min-w-0">{children}</div>
                </div>
            </motion.section>
        </div>
    );
}
