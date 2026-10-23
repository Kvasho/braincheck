import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { Permission } from '@/types/authorization';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    /** Translation key. */
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** Permission needed to see the item (UI only; the route enforces it). */
    permission?: Permission;
    /** Shared `navBadges` counter shown next to the item. */
    badge?: NavBadge;
};

/** Keys of the shared `navBadges` prop (HandleInertiaRequests::navBadges()). */
export type NavBadge = 'teacherRequests';

export type NavGroup = {
    /** Translation key. */
    title: string;
    items: NavItem[];
};
