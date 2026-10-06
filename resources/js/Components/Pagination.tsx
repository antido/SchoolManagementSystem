import { Paginator } from '@/Types';
import { Link } from '@inertiajs/react';

/**
 * "Showing 1 to 15 of 42" with Previous and Next links, for any Laravel
 * paginator. Filters in the address are kept because the controllers call
 * ->withQueryString().
 */
export default function Pagination<T>({ paginator }: { paginator: Paginator<T> }) {
    if (paginator.total === 0) {
        return null;
    }

    const link =
        'inline-flex min-h-9 items-center rounded-lg border border-line bg-surface px-3 text-sm font-semibold hover:border-brand hover:text-brand';
    const disabled =
        'inline-flex min-h-9 items-center rounded-lg border border-line px-3 text-sm font-semibold text-muted/60';

    return (
        <nav
            aria-label="Pagination"
            className="flex flex-col gap-3 border-t border-line px-5 py-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <p className="text-sm text-muted">
                Showing {paginator.from} to {paginator.to} of {paginator.total}
            </p>

            {paginator.last_page > 1 && (
                <div className="flex gap-2">
                    {paginator.prev_page_url ? (
                        <Link href={paginator.prev_page_url} className={link} preserveState>
                            Previous
                        </Link>
                    ) : (
                        <span className={disabled} aria-disabled="true">
                            Previous
                        </span>
                    )}
                    {paginator.next_page_url ? (
                        <Link href={paginator.next_page_url} className={link} preserveState>
                            Next
                        </Link>
                    ) : (
                        <span className={disabled} aria-disabled="true">
                            Next
                        </span>
                    )}
                </div>
            )}
        </nav>
    );
}
