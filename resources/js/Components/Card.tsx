import { cn } from '@/Utils/format';
import { PropsWithChildren, ReactNode } from 'react';

interface Props {
    title?: ReactNode;
    /** Buttons or links shown at the right of the title. */
    actions?: ReactNode;
    /** Remove the inner padding, for cards that hold a table. */
    flush?: boolean;
    className?: string;
}

/** A white panel with a border, optionally with a title row. */
export default function Card({
    title,
    actions,
    flush = false,
    className = '',
    children,
}: PropsWithChildren<Props>) {
    return (
        <section className={cn('rounded-xl border border-line bg-surface', className)}>
            {(title || actions) && (
                <header className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    {title && <h2 className="text-base font-semibold">{title}</h2>}
                    {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
                </header>
            )}
            <div className={flush ? '' : 'p-5'}>{children}</div>
        </section>
    );
}
