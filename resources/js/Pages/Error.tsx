import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head } from '@inertiajs/react';

const messages: Record<number, { title: string; text: string }> = {
    403: {
        title: 'You do not have access to this page',
        text: 'Your account is not allowed to open it. If you think that is a mistake, contact the school administrator.',
    },
    404: {
        title: 'Page not found',
        text: 'The address may be wrong, or the page may have been removed.',
    },
    419: {
        title: 'This page has expired',
        text: 'The form was open for a long time. Go back, reload the page and try again.',
    },
    429: {
        title: 'Too many requests',
        text: 'You are doing that too often. Wait a minute, then try again.',
    },
    500: {
        title: 'Something went wrong on our side',
        text: 'The problem has been recorded. Please try again in a moment.',
    },
    503: {
        title: 'Down for maintenance',
        text: 'The system is being updated and will be back shortly.',
    },
};

/**
 * The page shown for errors. It does not use the main layout or any shared
 * data, because some errors happen before that data exists, and it never
 * shows technical details.
 */
export default function Error({ status }: { status: number }) {
    const { title, text } = messages[status] ?? messages[500];

    return (
        <div className="flex min-h-screen flex-col items-center justify-center px-4 py-10 text-center">
            <Head title={title} />

            <a href="/" aria-label="Home" className="rounded-lg">
                <ApplicationLogo className="size-12" />
            </a>

            <main className="mt-6 max-w-md">
                <p className="text-sm font-semibold text-muted">Error {status}</p>
                <h1 className="mt-1 text-2xl font-semibold">{title}</h1>
                <p className="mt-3 text-muted">{text}</p>
                <a
                    href="/"
                    className="mt-8 inline-flex min-h-10 items-center rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-deep"
                >
                    Go to the home page
                </a>
            </main>
        </div>
    );
}
