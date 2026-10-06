import Icon from '@/Components/Icon';
import { PageProps } from '@/Types';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Toast {
    id: number;
    tone: 'success' | 'error';
    message: string;
}

/**
 * Shows the success or error message the server flashed after an action,
 * as a small notice in the corner. Success notices disappear after five
 * seconds; errors stay until dismissed. Screen readers announce both.
 */
export default function Toasts() {
    const { flash } = usePage<PageProps>().props;
    const [toasts, setToasts] = useState<Toast[]>([]);

    useEffect(() => {
        const incoming: Toast[] = [];

        if (flash.success) {
            incoming.push({ id: Date.now(), tone: 'success', message: flash.success });
        }
        if (flash.error) {
            incoming.push({ id: Date.now() + 1, tone: 'error', message: flash.error });
        }
        if (incoming.length > 0) {
            setToasts((current) => [...current, ...incoming]);
        }
    }, [flash]);

    useEffect(() => {
        const successes = toasts.filter((toast) => toast.tone === 'success');

        if (successes.length === 0) {
            return;
        }

        const timer = setTimeout(() => {
            setToasts((current) => current.filter((toast) => toast.id !== successes[0].id));
        }, 5000);

        return () => clearTimeout(timer);
    }, [toasts]);

    const dismiss = (id: number) =>
        setToasts((current) => current.filter((toast) => toast.id !== id));

    return (
        <div
            role="status"
            aria-live="polite"
            className="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-end gap-2 sm:inset-x-auto sm:right-6"
        >
            {toasts.map((toast) => (
                <div
                    key={toast.id}
                    className={`pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border px-4 py-3 text-sm font-medium shadow-lg ${
                        toast.tone === 'success'
                            ? 'border-success/30 bg-success-soft text-success'
                            : 'border-danger/30 bg-danger-soft text-danger'
                    }`}
                >
                    <Icon name={toast.tone === 'success' ? 'check' : 'close'} className="mt-0.5 size-4 shrink-0" />
                    <p className="flex-1">{toast.message}</p>
                    <button
                        type="button"
                        onClick={() => dismiss(toast.id)}
                        className="-m-1 rounded p-1 hover:bg-black/5"
                    >
                        <span className="sr-only">Dismiss</span>
                        <Icon name="close" className="size-4" />
                    </button>
                </div>
            ))}
        </div>
    );
}
