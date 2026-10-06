import { cn } from '@/Utils/format';
import { InertiaLinkProps, Link } from '@inertiajs/react';

/** A link in the sidebar. `active` marks the current section. */
export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}: InertiaLinkProps & { active?: boolean }) {
    return (
        <Link
            {...props}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'flex min-h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors',
                active
                    ? 'bg-brand text-white'
                    : 'text-muted hover:bg-brand-soft hover:text-brand',
                className,
            )}
        >
            {children}
        </Link>
    );
}
