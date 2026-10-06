import Icon from '@/Components/Icon';
import { cn } from '@/Utils/format';

/** The award's picture, or a ribbon when it has none. */
export default function AwardBadge({ imageUrl, className = 'size-12' }: { imageUrl: string | null; className?: string }) {
    if (imageUrl) {
        return <img src={imageUrl} alt="" className={cn('shrink-0 rounded-lg border border-line object-cover', className)} />;
    }

    return (
        <span className={cn('flex shrink-0 items-center justify-center rounded-lg bg-pencil/15 text-pencil', className)} aria-hidden="true">
            <Icon name="award" className="size-1/2" />
        </span>
    );
}
