import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { formatDate, useTranslation } from '@/lib/i18n';
import { index, show, today } from '@/routes/briefings';

type Props = {
    briefings: {
        id: number;
        kind: string;
        date: string;
        counts: Record<string, number>;
    }[];
};

export default function Briefings({ briefings }: Props) {
    const { t, locale } = useTranslation();

    return (
        <>
            <Head title={t('Briefing')} />

            <div className="space-y-6 px-4 py-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t('Briefing')}
                        description={t(
                            'A daily summary generated at 07:00 on working days. Past briefings keep what was known on the day.',
                        )}
                    />
                    <Button asChild>
                        <Link href={today()}>{t("Today's briefing")}</Link>
                    </Button>
                </div>

                {briefings.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('No briefings yet.')}
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {briefings.map((briefing) => (
                            <li
                                key={briefing.id}
                                className="flex flex-wrap items-center gap-4 px-4 py-3 text-sm"
                            >
                                <Link
                                    href={show(briefing.id)}
                                    className="w-40 font-medium hover:underline"
                                >
                                    {formatDate(briefing.date, locale)}
                                </Link>
                                <span className="text-muted-foreground">
                                    {t(
                                        ':alerts alerts · :events events · :news news · :mentions mentions',
                                        {
                                            alerts: briefing.counts.alerts ?? 0,
                                            events: briefing.counts.events ?? 0,
                                            news: briefing.counts.news ?? 0,
                                            mentions:
                                                briefing.counts.mentions ?? 0,
                                        },
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

Briefings.layout = {
    breadcrumbs: [{ title: 'Briefing', href: index() }],
};
