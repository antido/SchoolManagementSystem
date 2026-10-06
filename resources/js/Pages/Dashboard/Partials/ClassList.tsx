import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import Icon from '@/Components/Icon';
import { Link } from '@inertiajs/react';

export interface DashboardClass {
    id: number;
    name: string;
    grade_level: string;
    students?: number;
}

/** "My classes" on the teacher and student dashboards. */
export default function ClassList({ classes, emptyMessage }: { classes: DashboardClass[]; emptyMessage: string }) {
    return (
        <Card title="My classes" flush>
            {classes.length === 0 ? (
                <EmptyState icon="class" title="No classes this year" message={emptyMessage} />
            ) : (
                <ul className="divide-y divide-line">
                    {classes.map((schoolClass) => (
                        <li key={schoolClass.id}>
                            <Link
                                href={route('classes.show', schoolClass.id)}
                                className="flex items-center gap-3 px-5 py-3 hover:bg-canvas/60"
                            >
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand">
                                    <Icon name="class" className="size-4" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-medium">{schoolClass.name}</span>
                                    <span className="block text-xs text-muted">{schoolClass.grade_level}</span>
                                </span>
                                {schoolClass.students !== undefined && (
                                    <span className="text-sm text-muted">
                                        {schoolClass.students} {schoolClass.students === 1 ? 'student' : 'students'}
                                    </span>
                                )}
                                <Icon name="chevronRight" className="size-4 text-muted" />
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}
