import { Link } from '@inertiajs/react';
import MessageCard, { WorkflowMessage } from './MessageCard';
import MessageComposer, { MessageTypeOption } from './MessageComposer';

export default function MessageThread({ messages = [], canCreateMessage = false, messageStoreUrl, allowedMessageTypes = [], defaultMessageType = 'message', viewAllHref }: { messages?: WorkflowMessage[]; canCreateMessage?: boolean; messageStoreUrl?: string | null; allowedMessageTypes?: MessageTypeOption[]; defaultMessageType?: string; viewAllHref?: string }) {
    const visibleMessages = messages.slice(-5);

    return (
        <section className="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-gray-950">Feedback / Follow-up</h2>
                    <p className="mt-1 text-sm text-gray-500">Context-specific feedback, progress notes, and clarifications.</p>
                </div>
                {viewAllHref && messages.length > 5 && <Link href={viewAllHref} className="text-sm font-semibold text-gray-900">View All Feedback</Link>}
            </div>

            <div className="mt-4 space-y-3">
                {visibleMessages.map((message) => <MessageCard key={message.id} message={message} />)}
                {!visibleMessages.length && <div className="rounded-xl border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-500">No feedback or follow-up messages yet.</div>}
            </div>

            {canCreateMessage && messageStoreUrl && <MessageComposer action={messageStoreUrl} allowedMessageTypes={allowedMessageTypes} defaultMessageType={defaultMessageType} />}
        </section>
    );
}