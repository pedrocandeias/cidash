import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, RefreshCw } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateTime, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index, refresh, show } from '@/routes/briefings';

type BriefingItem = {
    title: string;
    url: string | null;
    at: string | null;
    detail: string | null;
    label: string | null;
    flag: boolean;
};

type Section = {
    key: string;
    title: string;
    count: number;
    items: BriefingItem[];
};

type Props = {
    briefing: {
        id: number;
        kind: 'daily' | 'weekly';
        date: string;
        generated_at: string;
        sections: Section[];
        is_current: boolean;
    };
    previous: number | null;
    next: number | null;
};

const emptyLabels: Record<string, string> = {
    alerts: 'No open alerts.',
    events: 'No events today.',
    press: 'No press deadlines until tomorrow.',
    tasks: 'No tasks due.',
    content: 'No content publishing today or in review.',
    news: 'No news.',
    mentions: 'No new mentions.',
    notices: 'No pinned notices.',
    campaigns: 'No campaigns starting.',
    answered: 'No press requests answered.',
    published: 'No content published.',
};

function When({ at }: { at: string }) {
    const { locale } = useTranslation();

    // Dates without a time (task deadlines) are shown as dates.
    return (
        <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
            {at.length === 10
                ? formatDate(at, locale)
                : formatDateTime(at, locale)}
        </span>
    );
}

export default function ShowBriefing({ briefing, previous, next }: Props) {
    const { t, locale } = useTranslation();

    return (
        <>
            <Head title={t('Briefing')} />

            <div className="mx-auto max-w-4xl space-y-8 px-4 py-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t(
                            briefing.kind === 'weekly'
                                ? 'Weekly briefing of :date'
                                : 'Briefing of :date',
                            { date: formatDate(briefing.date, locale) },
                        )}
                        description={t('Generated :date', {
                            date: formatDateTime(briefing.generated_at, locale),
                        })}
                    />
                    <div className="flex items-center gap-1">
                        {briefing.is_current && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        refresh(briefing.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <RefreshCw />
                                {t('Update')}
                            </Button>
                        )}
                        {previous !== null && (
                            <Button
                                asChild
                                variant="ghost"
                                size="icon"
                                aria-label={t('Previous briefing')}
                            >
                                <Link href={show(previous)}>
                                    <ChevronLeft />
                                </Link>
                            </Button>
                        )}
                        {next !== null && (
                            <Button
                                asChild
                                variant="ghost"
                                size="icon"
                                aria-label={t('Next briefing')}
                            >
                                <Link href={show(next)}>
                                    <ChevronRight />
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {briefing.sections.map((section) => (
                    <section key={section.key} className="space-y-2">
                        <h2 className="flex items-baseline gap-2 text-sm font-semibold tracking-wide uppercase">
                            {t(section.title)}
                            {section.count > 0 && (
                                <span className="text-xs font-normal text-muted-foreground">
                                    {section.count}
                                </span>
                            )}
                        </h2>
                        {section.items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t(emptyLabels[section.key] ?? 'Nothing.')}
                            </p>
                        ) : (
                            <ul className="divide-y rounded-lg border">
                                {section.items.map((item, position) => (
                                    <li
                                        key={position}
                                        className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2 text-sm"
                                    >
                                        {item.flag && (
                                            <span className="size-2 shrink-0 rounded-xs bg-critical" />
                                        )}
                                        {item.label && (
                                            <span className="shrink-0 font-medium">
                                                {t(item.label)}
                                            </span>
                                        )}
                                        {item.url ? (
                                            <Link
                                                href={item.url}
                                                className={cn(
                                                    'min-w-0 flex-1 truncate hover:underline',
                                                    !item.label &&
                                                        'font-medium',
                                                )}
                                            >
                                                {item.title}
                                            </Link>
                                        ) : (
                                            <span className="min-w-0 flex-1 truncate">
                                                {item.title}
                                            </span>
                                        )}
                                        {item.detail && (
                                            <span className="text-xs text-muted-foreground">
                                                {item.detail}
                                            </span>
                                        )}
                                        {item.at && <When at={item.at} />}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {section.count > section.items.length && (
                            <p className="text-xs text-muted-foreground">
                                {t('And :count more.', {
                                    count: section.count - section.items.length,
                                })}
                            </p>
                        )}
                    </section>
                ))}
            </div>
        </>
    );
}

ShowBriefing.layout = {
    breadcrumbs: [{ title: 'Briefing', href: index() }],
};
