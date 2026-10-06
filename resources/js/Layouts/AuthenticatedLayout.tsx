import ApplicationLogo from '@/Components/ApplicationLogo';
import Avatar from '@/Components/Avatar';
import Dropdown from '@/Components/Dropdown';
import Icon from '@/Components/Icon';
import NotificationBell from '@/Components/NotificationBell';
import NavLink from '@/Components/NavLink';
import Toasts from '@/Components/Toasts';
import { usePermissions } from '@/Hooks/usePermissions';
import { navigation } from '@/Lib/navigation';
import { PageProps } from '@/Types';
import { humanize } from '@/Utils/format';
import { Link, router, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useEffect, useState } from 'react';

/**
 * The frame around every signed-in page: a sidebar with the sections the
 * user may open, a top bar with the account menu, and the page itself.
 * On small screens the sidebar slides in from a menu button.
 */
export default function AuthenticatedLayout({
    topBar,
    children,
}: PropsWithChildren<{ topBar?: ReactNode }>) {
    const { auth, app } = usePage<PageProps>().props;
    const user = auth.user!;
    const { canAny, hasRole } = usePermissions();
    const [menuOpen, setMenuOpen] = useState(false);

    // Close the mobile menu after following a link.
    useEffect(() => router.on('navigate', () => setMenuOpen(false)), []);

    const groups = navigation
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) =>
                    (!item.permissions || canAny(item.permissions)) &&
                    (!item.roles || item.roles.some((role) => hasRole(role))),
            ),
        }))
        .filter((group) => group.items.length > 0);

    const sidebar = (
        <nav aria-label="Main" className="flex-1 space-y-6 overflow-y-auto px-3 py-5">
            {groups.map((group) => (
                <div key={group.label}>
                    <p className="px-3 text-xs font-semibold tracking-wide text-muted uppercase">
                        {group.label}
                    </p>
                    <ul className="mt-2 space-y-1">
                        {group.items.map((item) => (
                            <li key={item.route}>
                                <NavLink href={route(item.route)} active={route().current(item.match)}>
                                    <Icon name={item.icon} className="size-5 shrink-0" />
                                    {item.label}
                                </NavLink>
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </nav>
    );

    const brand = (
        <Link href={route('dashboard')} className="flex items-center gap-2.5 rounded-lg">
            <ApplicationLogo className="size-8" />
            <span className="font-display text-sm leading-tight font-semibold">{app.name}</span>
        </Link>
    );

    return (
        <div className="min-h-screen lg:pl-64">
            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-60 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2 focus:font-semibold"
            >
                Skip to content
            </a>

            {/* Sidebar on large screens. */}
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-line bg-surface lg:flex">
                <div className="flex h-16 items-center border-b border-line px-5">{brand}</div>
                {sidebar}
            </aside>

            {/* Sliding sidebar on small screens. */}
            {menuOpen && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div className="absolute inset-0 bg-ink/40" onClick={() => setMenuOpen(false)} />
                    <aside
                        id="mobile-sidebar"
                        className="absolute inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col bg-surface shadow-xl"
                    >
                        <div className="flex h-16 items-center justify-between border-b border-line px-5">
                            {brand}
                            <button
                                type="button"
                                onClick={() => setMenuOpen(false)}
                                className="rounded-lg p-2 text-muted hover:bg-canvas"
                            >
                                <span className="sr-only">Close menu</span>
                                <Icon name="close" />
                            </button>
                        </div>
                        {sidebar}
                    </aside>
                </div>
            )}

            <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-surface/95 px-4 backdrop-blur sm:px-6">
                <button
                    type="button"
                    onClick={() => setMenuOpen(true)}
                    aria-expanded={menuOpen}
                    aria-controls="mobile-sidebar"
                    className="rounded-lg p-2 text-ink hover:bg-canvas lg:hidden"
                >
                    <span className="sr-only">Open menu</span>
                    <Icon name="menu" />
                </button>

                <div className="min-w-0 flex-1">{topBar}</div>

                <NotificationBell />

                <Dropdown
                    label="Account menu"
                    trigger={
                        <>
                            <Avatar name={user.name} url={user.avatar_url} size="sm" />
                            <span className="hidden text-left sm:block">
                                <span className="block max-w-40 truncate text-sm font-semibold">
                                    {user.name}
                                </span>
                                <span className="block text-xs text-muted">
                                    {user.roles.map(humanize).join(', ')}
                                </span>
                            </span>
                        </>
                    }
                >
                    <Dropdown.Link href={route('profile.edit')}>My profile</Dropdown.Link>
                    <Dropdown.Divider />
                    <Dropdown.Link href={route('logout')} method="post" as="button">
                        Log out
                    </Dropdown.Link>
                </Dropdown>
            </header>

            <main id="main" className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:py-8">
                {children}
            </main>

            <Toasts />
        </div>
    );
}
