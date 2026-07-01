import { useForm } from '@inertiajs/react';

export type MessageTypeOption = { value: string; label: string };

export default function MessageComposer({ action, allowedMessageTypes = [], defaultMessageType = 'message' }: { action: string; allowedMessageTypes: MessageTypeOption[]; defaultMessageType?: string }) {
    const defaultType = allowedMessageTypes.some((type) => type.value === defaultMessageType)
        ? defaultMessageType
        : allowedMessageTypes[0]?.value || 'message';
    const { data, setData, post, processing, errors, reset } = useForm({ message_type: defaultType, body: '' });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post(action, { preserveScroll: true, onSuccess: () => reset('body') });
    }

    return (
        <form onSubmit={submit} className="mt-4 space-y-3 rounded-xl border border-gray-200 bg-white p-4">
            <div className="grid gap-3 sm:grid-cols-3">
                <div>
                    <label className="text-sm font-semibold text-gray-900">Message Type</label>
                    <select value={data.message_type} onChange={(event) => setData('message_type', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        {allowedMessageTypes.map((type) => <option key={type.value} value={type.value}>{type.label}</option>)}
                    </select>
                    {errors.message_type && <p className="mt-1 text-sm text-red-600">{errors.message_type}</p>}
                </div>
                <div className="sm:col-span-2">
                    <label className="text-sm font-semibold text-gray-900">Message</label>
                    <textarea rows={3} value={data.body} onChange={(event) => setData('body', event.target.value)} className="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500" />
                    {errors.body && <p className="mt-1 text-sm text-red-600">{errors.body}</p>}
                </div>
            </div>
            <button disabled={processing} className="inline-flex w-full items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-700 disabled:opacity-60 sm:w-auto">
                Send Message
            </button>
        </form>
    );
}