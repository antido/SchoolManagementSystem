import Icon, { IconName } from '@/Components/Icon';
import { ReactNode } from 'react';

/**
 * Shown when a list has nothing in it. Say what belongs here and, when the
 * user can add something, pass a button as `action`.
 */
export default function EmptyState({
    title,
    message,
    action,
    icon = 'clipboard',
}: {
    title: string;
    message?: string;
    action?: ReactNode;
    icon?: IconName;
}) {
    return (
        <div className="flex flex-col items-center px-6 py-12 text-center">
            <span className="flex size-12 items-center justify-center rounded-full bg-brand-soft text-brand">
                <Icon name={icon} className="size-6" />
            </span>
            <h3 className="mt-4 text-base font-semibold">{title}</h3>
            {message && <p className="mt-1 max-w-md text-sm text-muted">{message}</p>}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
