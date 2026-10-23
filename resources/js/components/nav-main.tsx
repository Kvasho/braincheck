import { Link, usePage } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslation } from '@/hooks/use-translation';
import type { NavGroup } from '@/types';

export function NavMain({ groups }: { groups: NavGroup[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { translate, t } = useTranslation();
    const { navBadges } = usePage().props;

    return (
        <>
            {groups.map((group) => (
                <SidebarGroup key={group.title} className="px-2 py-0">
                    <SidebarGroupLabel>
                        {translate(group.title)}
                    </SidebarGroupLabel>
                    <SidebarMenu>
                        {group.items.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentOrParentUrl(item.href)}
                                    tooltip={{
                                        children: translate(item.title),
                                    }}
                                >
                                    <Link href={item.href} prefetch>
                                        {item.icon && <item.icon />}
                                        <span>{translate(item.title)}</span>
                                    </Link>
                                </SidebarMenuButton>
                                {item.badge !== undefined &&
                                    (navBadges[item.badge] ?? 0) > 0 && (
                                        <SidebarMenuBadge
                                            aria-label={t(':count pending', {
                                                count:
                                                    navBadges[item.badge] ?? 0,
                                            })}
                                            className="tabular"
                                        >
                                            {navBadges[item.badge]}
                                        </SidebarMenuBadge>
                                    )}
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
