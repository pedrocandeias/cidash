import { Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Columns3,
    House,
    ListTodo,
    Mail,
    Flag,
    Megaphone,
    Newspaper,
    Radio,
    Rss,
    UserCog,
    Users,
} from 'lucide-react';
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
import { index as adminSources } from '@/routes/admin/sources';
import { index as adminUsers } from '@/routes/admin/users';
import { index as campaigns } from '@/routes/campaigns';
import { index as content } from '@/routes/content';
import { index as events } from '@/routes/events';
import { index as news } from '@/routes/news';
import { index as notices } from '@/routes/notices';
import { index as people } from '@/routes/people';
import { index as press } from '@/routes/press';
import { index as tasks } from '@/routes/tasks';
import type { NavItem } from '@/types';

// Grouped as in ARCHITECTURE.md §3. Each module adds its entry when it is built;
// empty groups are not rendered.
const navGroups: { label?: string; items: NavItem[] }[] = [
    {
        items: [{ title: 'Home', href: dashboard(), icon: House }],
    },
    {
        label: 'Operations',
        items: [
            { title: 'Calendar', href: events(), icon: CalendarDays },
            { title: 'Tasks', href: tasks(), icon: ListTodo },
            { title: 'Content', href: content(), icon: Columns3 },
            { title: 'Campaigns', href: campaigns(), icon: Flag },
            { title: 'Press requests', href: press(), icon: Newspaper },
            { title: 'People', href: people(), icon: Users },
            { title: 'Notices', href: notices(), icon: Megaphone },
        ],
    },
    {
        label: 'Monitoring',
        items: [{ title: 'News coverage', href: news(), icon: Radio }],
    },
];

const adminGroup = {
    label: 'Administration',
    items: [
        { title: 'Users', href: adminUsers(), icon: UserCog },
        { title: 'Sources', href: adminSources(), icon: Rss },
        { title: 'Email', href: editEmailSettings(), icon: Mail },
    ],
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
