import { Link } from '@inertiajs/react';
import { formatDateTime, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { AlertItem } from './types';
import { severityDot, severityLabels } from './types';

export default function AlertLine({ alert }: { alert: AlertItem }) {
    const { t, locale } = useTranslation();

    return (
        <span className="flex min-w-0 items-center gap-2 text-sm">
            <span
                className={cn(
                    'size-2 shrink-0 rounded-full',
                    severityDot[alert.severity],
                )}
                title={t(severityLabels[alert.severity])}
            />
            <span className="shrink-0 font-medium">{t(alert.message)}</span>
            <span className="text-muted-foreground">·</span>
            {alert.url ? (
                <Link href={alert.url} className="truncate hover:underline">
                    {alert.title}
                </Link>
            ) : (
                <span className="truncate">{alert.title}</span>
            )}
            {alert.due_at && (
                <span className="shrink-0 text-xs text-muted-foreground">
                    {formatDateTime(alert.due_at, locale)}
                </span>
            )}
        </span>
    );
}
