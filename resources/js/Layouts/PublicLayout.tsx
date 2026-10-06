import ApplicationLogo from '@/Components/ApplicationLogo';
import Button from '@/Components/Button';
import { PageProps } from '@/Types';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

/**
 * The frame for pages anyone can open, such as the home page.
 * `fullBleed` lets the content fill the whole area between header and footer.
 */
export default function PublicLayout({ children, fullBleed = false }: PropsWithChildren<{ fullBleed?: boolean }>) {
    const { app, auth } = usePage<PageProps>().props;

    return (
        <div className="flex min-h-screen flex-col">
            <header className="border-b border-line bg-surface">
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                    <Link href="/" className="flex min-w-0 items-center gap-2.5 rounded-lg">
                        <ApplicationLogo className="size-8" />
                        <span className="truncate font-display font-semibold">{app.name}</span>
                    </Link>

                    <div className="flex items-center gap-2">
                        <Button variant="ghost" href={route('faqs.index')}>
                            Help
                        </Button>
                        {auth.user ? (
                            <Button href={route('dashboard')}>Go to dashboard</Button>
                        ) : (
                            <Button href={route('login')}>Log in</Button>
                        )}
                    </div>
                </div>
            </header>

            <main id="main" className={fullBleed ? 'flex flex-1 flex-col' : 'mx-auto w-full max-w-6xl flex-1 px-4 py-10 sm:px-6'}>
                {children}
            </main>

            <footer className="border-t border-line bg-surface">
                <p className="mx-auto max-w-6xl px-4 py-5 text-sm text-muted sm:px-6">
                    &copy; {new Date().getFullYear()} {app.name}. All rights reserved.
                </p>
            </footer>
        </div>
    );
}
