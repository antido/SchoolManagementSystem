import { cn } from '@/Utils/format';

const sizes = {
    sm: 'size-8 text-xs',
    md: 'size-10 text-sm',
    lg: 'size-20 text-2xl',
};

/** A profile photo, or the person's initials when there is none. */
export default function Avatar({
    name,
    url,
    size = 'md',
    className = '',
}: {
    name: string;
    url?: string | null;
    size?: keyof typeof sizes;
    className?: string;
}) {
    const classes = cn(sizes[size], 'shrink-0 rounded-full', className);

    if (url) {
        return <img src={url} alt="" className={cn(classes, 'object-cover')} />;
    }

    const initials = name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');

    return (
        <span
            aria-hidden="true"
            className={cn(classes, 'inline-flex items-center justify-center bg-brand-soft font-semibold text-brand')}
        >
            {initials}
        </span>
    );
}
