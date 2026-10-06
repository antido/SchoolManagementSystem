import { PageProps } from '@/Types';
import { usePage } from '@inertiajs/react';

/**
 * Check the signed-in user's permissions inside a component:
 *
 *     const { can } = usePermissions();
 *     {can('students.create') && <Button>Add student</Button>}
 *
 * This only decides what to SHOW. The server checks every request again,
 * so hiding a button is never the only protection.
 */
export function usePermissions() {
    const { auth } = usePage<PageProps>().props;

    const can = (permission: string): boolean =>
        auth.permissions.includes(permission);

    const canAny = (permissions: string[]): boolean =>
        permissions.some((permission) => can(permission));

    const hasRole = (role: string): boolean =>
        auth.user?.roles.includes(role) ?? false;

    return { can, canAny, hasRole };
}
