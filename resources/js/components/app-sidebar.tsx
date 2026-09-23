import { Link, usePage } from '@inertiajs/react';
import { House, Mail } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { edit as editEmailSettings } from '@/routes/admin/email';
import type { NavItem } from '@/types';

// Grouped as in ARCHITECTURE.md §3. Each module adds its entry when it is built;
// empty groups are not rendered.
const navGroups: { label?: string; items: NavItem[] }[] = [
    {
        items: [{ title: 'Home', href: dashboard(), icon: House }],
    },
    { label: 'Operations', items: [] },
    { label: 'Monitoring', items: [] },
];

const adminGroup = {
    label: 'Administration',
    items: [{ title: 'Email', href: editEmailSettings(), icon: Mail }],
};

export function AppSidebar() {
    const { auth } = usePage().props;
    const groups = auth.user?.is_super_admin
        ? [...navGroups, adminGroup]
        : navGroups;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {groups
                    .filter((group) => group.items.length > 0)
                    .map((group, index) => (
                        <NavMain
                            key={group.label ?? index}
                            label={group.label}
                            items={group.items}
                        />
                    ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
