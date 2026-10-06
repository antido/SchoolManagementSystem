import Icon, { IconName } from '@/Components/Icon';
import { ActivityType } from '@/Types/activities';
import { cn } from '@/Utils/format';

const icons: Record<ActivityType, IconName> = {
    assignment: 'clipboard',
    quiz: 'check',
    examination: 'exam',
    questionnaire: 'help',
    activity: 'book',
};

/** A tinted square with the icon for an activity's kind. */
export default function ActivityTypeIcon({ type, className = '' }: { type: ActivityType; className?: string }) {
    return (
        <span
            className={cn(
                'flex size-9 shrink-0 items-center justify-center rounded-lg',
                type === 'quiz' || type === 'examination' ? 'bg-warning-soft text-warning' : 'bg-brand-soft text-brand',
                className,
            )}
            aria-hidden="true"
        >
            <Icon name={icons[type]} className="size-4" />
        </span>
    );
}
