import { Upload } from 'lucide-react';
import { useRef } from 'react';

export default function FileUpload({ onSelect, disabled = false }: { onSelect: (file: File | null | undefined) => void; disabled?: boolean }) {
    const inputRef = useRef<HTMLInputElement>(null);

    return (
        <>
            <input ref={inputRef} type="file" className="hidden" onChange={(event) => onSelect(event.target.files?.[0])} />
            <button type="button" disabled={disabled} onClick={() => inputRef.current?.click()} className="inline-flex items-center gap-1 text-sm font-semibold text-gray-900 hover:text-gray-600 disabled:cursor-not-allowed disabled:opacity-60">
                <Upload className="h-3.5 w-3.5" />
                Upload
            </button>
        </>
    );
}
