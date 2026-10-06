import { router, VisitOptions } from '@inertiajs/core';
import { useState } from 'react';

type Method = 'post' | 'put' | 'patch' | 'delete';

/**
 * Send a request from a button and know when it is in flight, so the
 * button can be disabled until the server answers:
 *
 *     const { busy, send } = useRequest();
 *     <Button disabled={busy} onClick={() => send('patch', url, { status: 'active' })}>
 */
export function useRequest() {
    const [busy, setBusy] = useState(false);

    const send = (
        method: Method,
        url: string,
        data: Record<string, unknown> = {},
        options: Omit<VisitOptions, 'method' | 'data'> = {},
    ) => {
        router.visit(url, {
            method,
            data: data as VisitOptions['data'],
            preserveScroll: true,
            ...options,
            onStart: (visit) => {
                setBusy(true);
                options.onStart?.(visit);
            },
            onFinish: (visit) => {
                setBusy(false);
                options.onFinish?.(visit);
            },
        });
    };

    return { busy, send };
}
