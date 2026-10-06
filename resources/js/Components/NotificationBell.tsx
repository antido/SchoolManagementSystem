import Icon, { IconName } from '@/Components/Icon';
import { getJson } from '@/Lib/http';
import { PageProps } from '@/Types';
import { AppNotification } from '@/Types/notifications';
import { cn } from '@/Utils/format';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { Link, router, usePage } from '@inertiajs/react';
import { watchNotifications } from '@/Lib/realtime';
import { useEffect, useState } from 'react';

/**
 * The bell in the top bar: the unread count, and the latest notifications
 * when opened. The list is fetched each time it opens, so it is current.
 */
export default function NotificationBell() {
    const { auth, app } = usePage<PageProps>().props;
    const [items, setItems] = useState<AppNotification[] | null>(null);
    const [failed, setFailed] = useState(false);
    const [announcement, setAnnouncement] = useState('');
    const unread = auth.unread_notifications;
    const userId = auth.user!.id;

    // Keep the count current without a page refresh: realtime when available,
    // otherwise a quiet check every minute. The count always comes from the server.
    useEffect(
        () =>
            watchNotifications(userId, app.realtime, (title) => {
                router.reload({ only: ['auth'] });
                if (title) {
                    setAnnouncement(`New notification: ${title}`);
                }
            }),
        [userId, app.realtime],
    );

    const load = () => {
        setFailed(false);
        getJson<{ notifications: AppNotification[] }>(route('notifications.recent'))
            .then((data) => setItems(data.notifications))
            .catch(() => setFailed(true));
    };

    return (
        <Popover className="relative">
            <p className="sr-only" aria-live="polite">
                {announcement}
            </p>
            <PopoverButton
                onClick={load}
                className="relative rounded-lg p-2 text-ink hover:bg-canvas focus-visible:ring-2 focus-visible:ring-pencil focus-visible:outline-none"
            >
                <span className="sr-only">Notifications{unread > 0 ? `, ${unread} unread` : ''}</span>
                <Icon name="bell" />
                {unread > 0 && (
                    <span
                        aria-hidden="true"
                        className="absolute -top-0.5 -right-0.5 flex min-w-5 items-center justify-center rounded-full bg-danger px-1 text-xs leading-5 font-semibold text-white"
                    >
                        {unread > 99 ? '99+' : unread}
                    </span>
                )}
            </PopoverButton>

            <PopoverPanel
                anchor={{ to: 'bottom end', gap: 8 }}
                className="z-40 w-[min(24rem,calc(100vw-2rem))] rounded-xl border border-line bg-surface shadow-lg"
            >
                {({ close }) => (
                    <>
                        <div className="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                            <p className="font-semibold">Notifications</p>
                            {unread > 0 && (
                                <button
                                    type="button"
                                    onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true, onSuccess: load })}
                                    className="text-sm font-medium text-brand hover:underline"
                                >
                                    Mark all as read
                                </button>
                            )}
                        </div>

                        <div className="max-h-96 overflow-y-auto">
                            {failed ? (
                                <p className="px-4 py-6 text-center text-sm text-danger">Notifications could not be loaded.</p>
                            ) : items === null ? (
                                <p className="px-4 py-6 text-center text-sm text-muted">Loading…</p>
                            ) : items.length === 0 ? (
                                <p className="px-4 py-6 text-center text-sm text-muted">You have no notifications.</p>
                            ) : (
                                <ul className="divide-y divide-line">
                                    {items.map((item) => (
                                        <li key={item.id}>
                                            <Link
                                                href={item.url}
                                                onClick={() => close()}
                                                className={cn('flex gap-3 px-4 py-3 hover:bg-canvas', !item.read && 'bg-brand-soft/50')}
                                            >
                                                <Icon name={item.icon as IconName} className="mt-0.5 size-4 shrink-0 text-muted" />
                                                <span className="min-w-0 flex-1">
                                                    <span className={cn('block text-sm', !item.read && 'font-semibold')}>{item.title}</span>
                                                    <span className="block text-xs text-muted">{item.when}</span>
                                                </span>
                                                {!item.read && <span className="mt-1.5 size-2 shrink-0 rounded-full bg-brand" aria-label="Unread" />}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        <Link
                            href={route('notifications.index')}
                            onClick={() => close()}
                            className="block border-t border-line px-4 py-3 text-center text-sm font-medium text-brand hover:bg-canvas"
                        >
                            See all notifications
                        </Link>
                    </>
                )}
            </PopoverPanel>
        </Popover>
    );
}
