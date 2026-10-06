import { ReactNode } from 'react';

export interface DetailItem {
    label: string;
    value: ReactNode;
}

/**
 * Label and value pairs in a two-column grid, for profile and detail pages.
 * Empty values show a dash so the layout stays even.
 */
export default function DetailList({ items }: { items: DetailItem[] }) {
    return (
        <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2">
            {items.map((item) => (
                <div key={item.label} className="min-w-0">
                    <dt className="text-sm text-muted">{item.label}</dt>
                    <dd className="mt-0.5 font-medium break-words">
                        {item.value === null || item.value === undefined || item.value === '' ? (
                            <span className="text-muted">—</span>
                        ) : (
                            item.value
                        )}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
