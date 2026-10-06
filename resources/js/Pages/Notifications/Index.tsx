import Button from '@/Components/Button';
import Card from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import Icon, { IconName } from '@/Components/Icon';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import { useRequest } from '@/Hooks/useRequest';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Paginator } from '@/Types';
import { AppNotification } from '@/Types/notifications';
import { cn, formatDateTime } from '@/Utils/format';
import { Head, Link } from '@inertiajs/react';

/** Every notification, newest first, with read and unread filters. */
export default function Index({
    notifications,
    filter,
    can,
}: {
    notifications: Paginator<AppNotification>;
    filter: 'all' | 'unread';
    can: { announce: boolean };
}) {
    const { busy, send } = useRequest();
    const hasUnread = notifications.data.some((item) => !item.read);

    const tab = (value: 'all' | 'unread', label: string) => (
        <Link
            href={route('notifications.index', value === 'unread' ? { filter: 'unread' } : {})}
            aria-current={filter === value ? 'page' : undefined}
            className={cn(
                'rounded-lg px-3 py-1.5 text-sm font-medium',
                filter === value ? 'bg-brand text-white' : 'text-muted hover:bg-canvas hover:text-ink',
            )}
        >
            {label}
        </Link>
    );

    return (
        <AuthenticatedLayout>
            <Head title="Notifications" />
            <PageHeader
                title="Notifications"
                actions={
                    <>
                        {can.announce && <Button href={route('notifications.announce')}>Send announcement</Button>}
                        {hasUnread && (
                            <Button variant="secondary" disabled={busy} onClick={() => send('post', route('notifications.read-all'))}>
                                Mark all as read
                            </Button>
                        )}
                    </>
                }
            />

            <Card flush>
                <nav aria-label="Filter notifications" className="flex gap-2 border-b border-line p-3">
                    {tab('all', 'All')}
                    {tab('unread', 'Unread')}
                </nav>

                {notifications.data.length === 0 ? (
                    <EmptyState
                        icon="bell"
                        title={filter === 'unread' ? 'Nothing unread' : 'No notifications yet'}
                        message="New assignments, grades, results and awards appear here."
                    />
                ) : (
                    <ul className="divide-y divide-line">
                        {notifications.data.map((item) => (
                            <li key={item.id} className={cn('flex gap-3 px-5 py-4', !item.read && 'bg-brand-soft/40')}>
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-canvas text-muted">
                                    <Icon name={item.icon as IconName} className="size-4" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <Link href={item.url} className={cn('hover:text-brand hover:underline', !item.read && 'font-semibold')}>
                                        {item.title}
                                    </Link>
                                    {item.body && <p className="mt-0.5 text-sm whitespace-pre-line text-muted">{item.body}</p>}
                                    <p className="mt-1 text-xs text-muted" title={formatDateTime(item.created_at)}>
                                        {item.when}
                                    </p>
                                </div>
                                {!item.read && (
                                    <Button
                                        small
                                        variant="ghost"
                                        disabled={busy}
                                        onClick={() => send('patch', route('notifications.read', item.id))}
                                    >
                                        Mark as read<span className="sr-only">: {item.title}</span>
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                <Pagination paginator={notifications} />
            </Card>
        </AuthenticatedLayout>
    );
}
