import { cn } from '@/Utils/format';
import { PropsWithChildren } from 'react';

export type BadgeTone = 'neutral' | 'brand' | 'success' | 'warning' | 'danger';

const tones: Record<BadgeTone, string> = {
    neutral: 'bg-canvas text-muted border-line',
    brand: 'bg-brand-soft text-brand border-brand/20',
    success: 'bg-success-soft text-success border-success/20',
    warning: 'bg-warning-soft text-warning border-warning/30',
    danger: 'bg-danger-soft text-danger border-danger/20',
};

/** A small label for a status such as "Active" or "Late". */
export default function Badge({
    tone = 'neutral',
    className = '',
    children,
}: PropsWithChildren<{ tone?: BadgeTone; className?: string }>) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap',
                tones[tone],
                className,
            )}
        >
            {children}
        </span>
    );
}
