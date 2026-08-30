import { usePage } from '@inertiajs/react';
import type { Auth } from '@/types/auth';

/**
 * Hook to check the authenticated user's permissions and roles.
 * Permissions are shared globally via Inertia in HandleInertiaRequests.
 */
export function usePermissions() {
    const { auth } = usePage().props as { auth: Auth };
    const permissions = new Set(auth?.user?.permissions ?? []);
    const roles = new Set(auth?.user?.roles ?? []);

    /**
     * Check if the user has the given permission.
     * The server-provided permission list is the source of truth.
     */
    const can = (permission: string | null | undefined): boolean => {
        if (
            permission === null ||
            permission === undefined ||
            permission === ''
        ) {
            return true;
        }

        return permissions.has(permission);
    };

    /**
     * Check if the user has ANY of the given permissions.
     */
    const canAny = (perms: string[]): boolean => {
        if (perms.length === 0) {
            return true;
        }

        return perms.some((p) => can(p));
    };

    /**
     * Check if the user has a specific role.
     */
    const hasRole = (role: string): boolean => roles.has(role);

    return { can, canAny, hasRole, permissions, roles };
}
