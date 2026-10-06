import Icon from '@/Components/Icon';
import { Attachment } from '@/Types/activities';
import { cn, formatBytes } from '@/Utils/format';

/**
 * Files as download links. With `onToggleRemove`, each file gets a
 * Remove/Keep button and files in `removing` are shown struck through.
 */
export default function AttachmentList({
    attachments,
    removing = [],
    onToggleRemove,
}: {
    attachments: Attachment[];
    removing?: number[];
    onToggleRemove?: (id: number) => void;
}) {
    if (attachments.length === 0) {
        return null;
    }

    return (
        <ul className="divide-y divide-line rounded-lg border border-line">
            {attachments.map((attachment) => {
                const removed = removing.includes(attachment.id);

                return (
                    <li key={attachment.id} className="flex items-center gap-3 px-3 py-2">
                        <Icon name="report" className="size-4 shrink-0 text-muted" />
                        <span className={cn('min-w-0 flex-1', removed && 'text-muted line-through')}>
                            {/* A plain link: downloads are not Inertia visits. */}
                            <a href={attachment.url} className="link break-all">
                                {attachment.name}
                            </a>
                            <span className="ml-2 text-xs text-muted">{formatBytes(attachment.size)}</span>
                        </span>
                        {onToggleRemove && (
                            <button
                                type="button"
                                onClick={() => onToggleRemove(attachment.id)}
                                className="rounded px-2 py-1 text-sm font-medium text-danger hover:bg-danger-soft"
                            >
                                {removed ? 'Keep' : 'Remove'}
                            </button>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
