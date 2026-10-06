import { ActivitySummary } from '@/Types/activities';
import { cn, formatDateTime } from '@/Utils/format';

/**
 * "Due Oct 9, 2026, 5:00 PM", highlighted when the due date has passed on
 * an activity that is still open.
 */
export default function DueDate({ activity, className = '' }: { activity: ActivitySummary; className?: string }) {
    if (activity.availability === 'scheduled' && activity.start_at) {
        return <span className={cn('text-muted', className)}>Opens {formatDateTime(activity.start_at)}</span>;
    }

    if (!activity.due_at) {
        return <span className={cn('text-muted', className)}>No due date</span>;
    }

    return (
        <span className={cn(activity.availability === 'past_due' ? 'font-medium text-danger' : 'text-muted', className)}>
            Due {formatDateTime(activity.due_at)}
        </span>
    );
}
