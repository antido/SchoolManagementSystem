import { PageProps } from '@/Types';
import { formatBytes } from '@/Utils/format';
import { usePage } from '@inertiajs/react';
import { ChangeEvent, InputHTMLAttributes } from 'react';

interface Props extends Omit<InputHTMLAttributes<HTMLInputElement>, 'value' | 'onChange' | 'type'> {
    files: File[];
    onChange: (files: File[]) => void;
}

/**
 * Pick several files. Chosen files are listed and can be taken off before
 * the form is sent. Allowed types and the size limit come from the server
 * (config/school.php), which checks them again on upload.
 */
export default function FileInput({ files, onChange, id, ...props }: Props) {
    const { uploads } = usePage<PageProps>().props.app;

    const add = (e: ChangeEvent<HTMLInputElement>) => {
        onChange([...files, ...Array.from(e.target.files ?? [])]);
        // Clear the input so the same file can be chosen again after removing it.
        e.target.value = '';
    };

    return (
        <div className="space-y-2">
            <input
                {...props}
                id={id}
                type="file"
                multiple
                accept={uploads.extensions.map((extension) => `.${extension}`).join(',')}
                onChange={add}
                className="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border file:border-line file:bg-surface file:px-3 file:py-2 file:text-sm file:font-semibold file:text-ink hover:file:bg-canvas"
            />
            <p className="text-xs text-muted">
                Up to {uploads.max_files} files, {formatBytes(uploads.max_kilobytes * 1024)} each.
            </p>
            {files.length > 0 && (
                <ul className="divide-y divide-line rounded-lg border border-line">
                    {files.map((file, index) => {
                        const tooBig = file.size > uploads.max_kilobytes * 1024;

                        return (
                            <li key={`${file.name}-${index}`} className="flex items-center gap-3 px-3 py-2 text-sm">
                                <span className="min-w-0 flex-1 break-all">
                                    {file.name}
                                    <span className={tooBig ? 'ml-2 text-xs font-medium text-danger' : 'ml-2 text-xs text-muted'}>
                                        {formatBytes(file.size)}
                                        {tooBig && ', too large'}
                                    </span>
                                </span>
                                <button
                                    type="button"
                                    onClick={() => onChange(files.filter((_, i) => i !== index))}
                                    className="rounded px-2 py-1 font-medium text-danger hover:bg-danger-soft"
                                >
                                    Remove
                                </button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
