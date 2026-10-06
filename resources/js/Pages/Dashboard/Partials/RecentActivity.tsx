import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import { Link } from '@inertiajs/react';

export interface ActivityItem {
    label: string;
    detail: string;
    when: string;
    href: string | null;
}

/** The latest additions to the system, newest first. */
export default function RecentActivity({ items }: { items: ActivityItem[] }) {
    return (
        <Card title="Recent activity" flush>
            {items.length === 0 ? (
                <EmptyState icon="log" title="Nothing yet" message="New students, teachers, classes and enrollments appear here." />
            ) : (
                <ul className="divide-y divide-line">
                    {items.map((item, index) => (
                        <li key={index} className="px-5 py-3">
                            {item.href ? (
                                <Link href={item.href} className="font-medium hover:text-brand hover:underline">
                                    {item.label}
                                </Link>
                            ) : (
                                <span className="font-medium">{item.label}</span>
                            )}
                            <p className="text-xs text-muted">
                                {item.detail}, {item.when}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}
