import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { InertiaLinkProps, Link } from '@inertiajs/react';
import { PropsWithChildren, ReactNode } from 'react';

/**
 * An accessible dropdown menu (arrow keys, Escape and focus handling come
 * from Headless UI).
 */
function Dropdown({
    trigger,
    label,
    children,
}: PropsWithChildren<{ trigger: ReactNode; label: string }>) {
    return (
        <Menu as="div" className="relative">
            <MenuButton
                aria-label={label}
                className="flex min-h-10 items-center gap-2 rounded-lg px-2 text-sm font-medium text-ink hover:bg-brand-soft"
            >
                {trigger}
            </MenuButton>

            <MenuItems
                anchor="bottom end"
                className="z-50 mt-2 w-56 rounded-xl border border-line bg-surface p-1 shadow-lg focus:outline-none"
            >
                {children}
            </MenuItems>
        </Menu>
    );
}

const itemClasses =
    'block w-full rounded-lg px-3 py-2 text-start text-sm text-ink data-[focus]:bg-brand-soft data-[focus]:text-brand';

function DropdownLink({ children, ...props }: InertiaLinkProps) {
    return (
        <MenuItem>
            <Link {...props} className={itemClasses}>
                {children}
            </Link>
        </MenuItem>
    );
}

function DropdownDivider() {
    return <div className="my-1 border-t border-line" role="separator" />;
}

Dropdown.Link = DropdownLink;
Dropdown.Divider = DropdownDivider;

export default Dropdown;
