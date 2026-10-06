import Icon from '@/Components/Icon';
import { Link } from '@inertiajs/react';
import { Fragment } from 'react';

export interface Crumb {
    label: string;
    /** Leave out for the current page. */
    href?: string;
}

/** "Students > Ana Cruz > Edit", the trail back up from the current page. */
export default function Breadcrumbs({ items }: { items: Crumb[] }) {
    return (
        <nav aria-label="Breadcrumb" className="mb-2">
            <ol className="flex flex-wrap items-center gap-1 text-sm text-muted">
                {items.map((item, index) => (
                    <Fragment key={`${item.label}-${index}`}>
                        {index > 0 && (
                            <li aria-hidden="true">
                                <Icon name="chevronRight" className="size-3.5" />
                            </li>
                        )}
                        <li>
                            {item.href ? (
                                <Link href={item.href} className="hover:text-brand hover:underline">
                                    {item.label}
                                </Link>
                            ) : (
                                <span aria-current="page" className="text-ink">
                                    {item.label}
                                </span>
                            )}
                        </li>
                    </Fragment>
                ))}
            </ol>
        </nav>
    );
}
