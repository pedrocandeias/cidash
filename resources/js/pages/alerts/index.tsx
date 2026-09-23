import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { formatDateTime, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import AlertLine from '@/modules/alerts/alert-line';
import type { AlertItem } from '@/modules/alerts/types';
import { index, update } from '@/routes/alerts';

export default function Alerts({
    alerts,
    status,
}: {
    alerts: AlertItem[];
    status: 'active' | 'resolved';
}) {
    const { t, locale } = useTranslation();
    const acknowledge = (alert: AlertItem, acknowledged: boolean) =>
        router.patch(
            update(alert.id).url,
            { acknowledged },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={t('Alerts')} />

            <div className="space-y-6 px-4 py-6">
                <Heading
                    title={t('Alerts')}
                    description={t(
                        'Alerts close by themselves when the problem is fixed.',
                    )}
                />

                <nav className="flex gap-1" aria-label={t('Status')}>
                    {(['active', 'resolved'] as const).map((option) => (
                        <Link
                            key={option}
                            href={index({
                                query:
                                    option === 'active'
                                        ? {}
                                        : { status: option },
                            })}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm',
                                status === option
                                    ? 'bg-muted font-medium'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t(
                                option === 'active'
                                    ? 'Active alerts'
                                    : 'Resolved in the last 30 days',
                            )}
                        </Link>
                    ))}
                </nav>

                {alerts.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t(
                            status === 'active'
                                ? 'No alerts. All clear.'
                                : 'No resolved alerts.',
                        )}
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {alerts.map((alert) => (
                            <li
                                key={alert.id}
                                className={cn(
                                    'flex flex-wrap items-center gap-3 px-4 py-3',
                                    alert.status === 'acknowledged' &&
                                        'opacity-70',
                                )}
                            >
                                <div className="min-w-64 flex-1">
                                    <AlertLine alert={alert} />
                                </div>
                                <span className="text-xs text-muted-foreground">
                                    {alert.status === 'resolved' &&
                                    alert.resolved_at
                                        ? t('Resolved :date', {
                                              date: formatDateTime(
                                                  alert.resolved_at,
                                                  locale,
                                              ),
                                          })
                                        : alert.acknowledged_by
                                          ? t('Seen by :name', {
                                                name: alert.acknowledged_by,
                                            })
                                          : null}
                                </span>
                                {alert.status === 'open' && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => acknowledge(alert, true)}
                                    >
                                        {t('Mark as seen')}
                                    </Button>
                                )}
                                {alert.status === 'acknowledged' && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            acknowledge(alert, false)
                                        }
                                    >
                                        {t('Reopen')}
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

Alerts.layout = {
    breadcrumbs: [{ title: 'Alerts', href: index() }],
};
