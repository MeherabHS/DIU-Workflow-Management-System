export type WorkflowMessage = {
    id: number;
    body: string;
    message_type: string;
    message_type_label?: string;
    sender_name: string;
    sender_role?: string;
    sender_initials?: string;
    created_at_human?: string | null;
};

export default function MessageCard({ message }: { message: WorkflowMessage }) {
    return (
        <article className="rounded-xl border border-gray-200 bg-white p-4">
            <div className="flex items-start gap-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-900 text-xs font-semibold text-white">
                    {message.sender_initials || 'U'}
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="text-sm font-semibold text-gray-950">{message.sender_name}</p>
                        {message.sender_role && <span className="text-xs text-gray-500">{message.sender_role}</span>}
                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{message.message_type_label || message.message_type}</span>
                    </div>
                    <p className="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-gray-700">{message.body}</p>
                    {message.created_at_human && <p className="mt-2 text-xs text-gray-500">{message.created_at_human}</p>}
                </div>
            </div>
        </article>
    );
}
