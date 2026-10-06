import ActivityTypeIcon from '@/Components/Activities/ActivityTypeIcon';
import DueDate from '@/Components/Activities/DueDate';
import Icon from '@/Components/Icon';
import StatusBadge from '@/Components/StatusBadge';
import { ActivitySummary } from '@/Types/activities';
import { Link } from '@inertiajs/react';

/**
 * Activities as a list of rows linking to each one. Used on the activities
 * page, class subject pages and dashboards.
 */
export default function ActivityList({
    activities,
    showClass = true,
}: {
    activities: ActivitySummary[];
    /** Show the subject and class under each title; turn off when they are the same for every row. */
    showClass?: boolean;
}) {
    return (
        <ul className="divide-y divide-line">
            {activities.map((activity) => (
                <li key={activity.id}>
                    <Link
                        href={route('activities.show', activity.id)}
                        className="flex items-start gap-3 px-5 py-3 hover:bg-canvas/60 sm:items-center"
                    >
                        <ActivityTypeIcon type={activity.type} />
                        <span className="min-w-0 flex-1">
                            <span className="block font-medium">{activity.title}</span>
                            <span className="mt-0.5 flex flex-wrap gap-x-3 text-xs">
                                <span className="text-muted">{activity.type_label}</span>
                                {showClass && (
                                    <span className="text-muted">
                                        {activity.class_subject.subject}, {activity.class_subject.school_class.name}
                                    </span>
                                )}
                                <DueDate activity={activity} />
                            </span>
                        </span>
                        <StatusBadge status={activity.availability} />
                        <Icon name="chevronRight" className="hidden size-4 text-muted sm:block" />
                    </Link>
                </li>
            ))}
        </ul>
    );
}
