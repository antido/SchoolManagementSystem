import Breadcrumbs, { Crumb } from '@/Components/Breadcrumbs';
import { ReactNode } from 'react';

/**
 * The top of every page: breadcrumbs, the title, an optional intro and
 * action buttons on the right.
 */
export default function PageHeader({
    title,
    intro,
    actions,
    breadcrumbs,
}: {
    title: ReactNode;
    intro?: ReactNode;
    actions?: ReactNode;
    breadcrumbs?: Crumb[];
}) {
    return (
        <div className="mb-6">
            {breadcrumbs && <Breadcrumbs items={breadcrumbs} />}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div className="min-w-0">
                    <h1 className="text-2xl font-semibold tracking-tight break-words sm:text-3xl">
                        {title}
                    </h1>
                    {intro && <p className="mt-1 max-w-2xl text-muted">{intro}</p>}
                </div>
                {actions && <div className="flex shrink-0 flex-wrap gap-2">{actions}</div>}
            </div>
        </div>
    );
}
