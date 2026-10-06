import Badge, { BadgeTone } from '@/Components/Badge';
import { humanize } from '@/Utils/format';

/**
 * The colour used for each status value anywhere in the app, so that
 * "active" is always green and "inactive" always grey.
 */
const tones: Record<string, BadgeTone> = {
    active: 'success',
    published: 'success',
    present: 'success',
    graded: 'success',
    passed: 'success',
    completed: 'success',
    enrolled: 'success',
    resolved: 'success',
    draft: 'neutral',
    inactive: 'neutral',
    archived: 'neutral',
    closed: 'neutral',
    ended: 'neutral',
    withdrawn: 'neutral',
    dropped: 'neutral',
    dismissed: 'neutral',
    graduated: 'brand',
    transferred: 'brand',
    submitted: 'brand',
    in_progress: 'brand',
    scheduled: 'brand',
    open: 'success',
    past_due: 'warning',
    on_leave: 'warning',
    late: 'warning',
    excused: 'warning',
    pending: 'warning',
    suspended: 'danger',
    absent: 'danger',
    failed: 'danger',
    missing: 'danger',
    expired: 'danger',
};

export default function StatusBadge({ status }: { status: string }) {
    return <Badge tone={tones[status] ?? 'neutral'}>{humanize(status)}</Badge>;
}
