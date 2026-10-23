import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import type { Permission, Role } from '@/types/authorization';

/**
 * UI-ONLY permission checks: they decide what to show, never what is allowed.
 * The backend enforces every route and action (role/permission middleware and
 * policies); hiding a button here is cosmetic, not a security boundary.
 *
 * Prefer can(): check permissions, not roles. hasRole() exists only for
 * display (e.g. the role badge), never for access decisions.
 */
export function usePermissions() {
    const { auth } = usePage().props;
    const permissions = auth.permissions;
    const role = auth.user?.role ?? null;

    const can = useCallback(
        (permission: Permission): boolean => permissions.includes(permission),
        [permissions],
    );

    const hasRole = useCallback(
        (expected: Role): boolean => role === expected,
        [role],
    );

    return { can, hasRole, role } as const;
}
