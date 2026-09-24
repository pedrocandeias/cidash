import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Users } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslation } from '@/lib/i18n';
import { switchMethod } from '@/routes/workspaces';

/**
 * Shows the current team; people in several teams (and the super admin) switch here.
 */
export function WorkspaceSwitcher() {
    const { t } = useTranslation();
    const { workspace, workspaces } = usePage().props;

    if (!workspace) {
        return null;
    }

    const current = (
        <>
            <Users className="size-4" />
            <span className="truncate">{workspace.name}</span>
        </>
    );

    if (workspaces.length <= 1) {
        return (
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton className="pointer-events-none text-sidebar-foreground/75">
                        {current}
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        );
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton aria-label={t('Change team')}>
                            {current}
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="min-w-56">
                        <DropdownMenuLabel>{t('Teams')}</DropdownMenuLabel>
                        {workspaces.map((option) => (
                            <DropdownMenuItem
                                key={option.id}
                                onSelect={() =>
                                    option.id !== workspace.id &&
                                    router.post(switchMethod(option.id).url)
                                }
                            >
                                <span className="flex-1 truncate">
                                    {option.name}
                                </span>
                                {option.id === workspace.id && (
                                    <Check className="size-4" />
                                )}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
