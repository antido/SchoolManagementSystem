/**
 * Finding out about new notifications while a page is open.
 *
 * With realtime on (and Reverb's key set at build time), the page listens on
 * the person's private channel and hears about each notification as it is
 * created. Otherwise, or if the connection cannot be made, it checks every
 * minute while the tab is visible. Either way the notifications themselves
 * come from the database, so nothing is lost if a message is missed: after a
 * reconnect the page simply asks again.
 */

import { xsrfToken } from '@/Lib/http';

const POLL_EVERY_MS = 60_000;

type Listener = (title: string | null) => void;

export function watchNotifications(userId: number, realtime: boolean, onChange: Listener): () => void {
    let stopped = false;
    let stopRealtime: (() => void) | null = null;
    let timer: number | undefined;

    const poll = () => {
        window.clearInterval(timer);
        timer = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                onChange(null);
            }
        }, POLL_EVERY_MS);
    };

    const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;

    if (realtime && key) {
        connect(userId, key, onChange)
            .then((stop) => {
                if (stopped) {
                    stop();
                } else {
                    stopRealtime = stop;
                }
            })
            // No realtime server: fall back to checking now and then.
            .catch(poll);
    } else {
        poll();
    }

    // Coming back to the tab is a good moment to catch up.
    const onVisible = () => document.visibilityState === 'visible' && onChange(null);
    document.addEventListener('visibilitychange', onVisible);

    return () => {
        stopped = true;
        stopRealtime?.();
        window.clearInterval(timer);
        document.removeEventListener('visibilitychange', onVisible);
    };
}

/**
 * Listen on the person's private channel. The Echo and Pusher libraries are
 * only downloaded when realtime is actually used.
 */
async function connect(userId: number, key: string, onChange: Listener): Promise<() => void> {
    const [{ default: Echo }, { default: Pusher }] = await Promise.all([import('laravel-echo'), import('pusher-js')]);

    const echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        // Private channels are authorised by Laravel, which checks this token.
        auth: { headers: { 'X-XSRF-TOKEN': xsrfToken() } },
        Pusher,
    });

    echo.private(`App.Models.User.${userId}`).notification((notification: { title?: string }) => onChange(notification.title ?? null));

    // After the connection drops and comes back, catch up on anything missed.
    let wasConnected = false;
    echo.connector.pusher.connection.bind('state_change', ({ current }: { current: string }) => {
        if (current === 'connected') {
            if (wasConnected) {
                onChange(null);
            }
            wasConnected = true;
        }
    });

    return () => echo.disconnect();
}
