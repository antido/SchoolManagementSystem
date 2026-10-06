import ApplicationLogo from '@/Components/ApplicationLogo';
import Toasts from '@/Components/Toasts';
import { PageProps } from '@/Types';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

/** The centred card used by the sign-in and password pages. */
export default function GuestLayout({ children }: PropsWithChildren) {
    const { app } = usePage<PageProps>().props;

    return (
        <div className="flex min-h-screen flex-col items-center px-4 py-10 sm:justify-center">
            <Link href="/" className="flex items-center gap-3 rounded-lg">
                <ApplicationLogo className="size-10" />
                <span className="font-display text-lg font-semibold">{app.name}</span>
            </Link>

            <main className="mt-6 w-full rounded-xl border border-line bg-surface p-6 shadow-sm sm:max-w-md sm:p-8">
                {children}
            </main>

            <Toasts />
        </div>
    );
}
