import { Link, router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { formatDateTime, useTranslation } from '@/lib/i18n';
import { open, readAll } from '@/routes/notifications';

export function NotificationsMenu() {
    const { notifications } = usePage().props;
    const { t, locale } = useTranslation();

    if (!notifications) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={t('Notifications')}
                >
                    <Bell />
                    {notifications.unread > 0 && (
                        <span className="absolute top-1 right-1 min-w-4 rounded-full bg-critical px-1 text-[10px] leading-4 font-medium text-on-critical">
                            {notifications.unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel className="flex items-center justify-between">
                    {t('Notifications')}
                    {notifications.unread > 0 && (
                        <button
                            type="button"
                            className="text-xs font-normal text-muted-foreground hover:text-foreground"
                            onClick={() =>
                                router.post(
                                    readAll().url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('Mark all as read')}
                        </button>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {notifications.items.length === 0 && (
                    <p className="px-2 py-3 text-sm text-muted-foreground">
                        {t('No notifications.')}
                    </p>
                )}
                {notifications.items.map((item) => (
                    <DropdownMenuItem key={item.id} asChild>
                        <Link
                            href={open(item.id)}
                            className={cn(
                                'flex flex-col items-start gap-0.5',
                                item.read && 'opacity-60',
                            )}
                        >
                            <span className="text-xs text-muted-foreground">
                                {t(item.data.message)}
                                {item.data.by && ` · ${item.data.by}`}
                            </span>
                            <span className="text-sm font-medium">
                                {item.data.title}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {formatDateTime(item.created_at, locale)}
                            </span>
                        </Link>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
