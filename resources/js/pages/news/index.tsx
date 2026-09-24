import { Head, Link, router } from '@inertiajs/react';
import { Check, ExternalLink, X } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index, show, update } from '@/routes/news';

type Line = {
    id: string;
    headline: string;
    outlet: string | null;
    url: string;
    published_at: string;
    status: 'new' | 'relevant' | 'irrelevant' | 'archived';
    story_count: number;
    outlets: string[];
    priority: boolean;
    score: number;
    reasons: string[];
};

type Props = {
    lines: Line[];
    status: 'new' | 'relevant' | 'irrelevant' | 'all';
    sort: 'recent' | 'relevance';
    counts: Record<string, number>;
};

const tabs = [
    { value: 'new', label: 'To triage' },
    { value: 'relevant', label: 'Marked relevant' },
    { value: 'irrelevant', label: 'Marked not relevant' },
    { value: 'all', label: 'All' },
] as const;

export default function News({ lines, status, sort, counts }: Props) {
    const { t, locale } = useTranslation();

    const triage = (line: Line, next: 'relevant' | 'irrelevant') =>
        router.patch(
            update(line.id).url,
            { status: next, whole_story: true },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <>
            <Head title={t('News coverage')} />

            <div className="space-y-6 px-4 py-6">
                <Heading
                    title={t('News coverage')}
                    description={t(
                        'Articles from the sources your team follows, grouped by story.',
                    )}
                />

                <nav className="flex flex-wrap gap-1" aria-label={t('Status')}>
                    {tabs.map((tab) => (
                        <Link
                            key={tab.value}
                            href={index({
                                query: {
                                    ...(tab.value === 'new'
                                        ? {}
                                        : { status: tab.value }),
                                    ...(sort === 'relevance' ? { sort } : {}),
                                },
                            })}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm',
                                status === tab.value
                                    ? 'bg-muted font-medium'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t(tab.label)}
                            {tab.value !== 'all' && counts[tab.value] ? (
                                <span className="ml-1 text-xs text-muted-foreground">
                                    {counts[tab.value]}
                                </span>
                            ) : null}
                        </Link>
                    ))}
                    <span className="mx-2 w-px self-stretch bg-border" />
                    {(['recent', 'relevance'] as const).map((option) => (
                        <Link
                            key={option}
                            href={index({
                                query: {
                                    ...(status === 'new' ? {} : { status }),
                                    ...(option === 'relevance'
                                        ? { sort: option }
                                        : {}),
                                },
                            })}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm',
                                sort === option
                                    ? 'bg-muted font-medium'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t(
                                option === 'recent'
                                    ? 'Most recent'
                                    : 'Most relevant',
                            )}
                        </Link>
                    ))}
                </nav>

                {lines.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t(
                            status === 'new'
                                ? 'Nothing left to triage.'
                                : 'No news here.',
                        )}
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {lines.map((line) => (
                            <li
                                key={line.id}
                                className="flex flex-wrap items-start gap-3 px-4 py-3"
                            >
                                <div className="min-w-64 flex-1 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        <span
                                            className={cn(
                                                'inline-flex h-5 min-w-5 items-center justify-center rounded px-1 font-semibold tabular-nums',
                                                line.score >= 6
                                                    ? 'bg-primary text-primary-foreground'
                                                    : line.score >= 3
                                                      ? 'bg-primary/15 text-foreground'
                                                      : 'bg-muted',
                                            )}
                                            title={
                                                line.reasons.length > 0
                                                    ? line.reasons
                                                          .map((reason) =>
                                                              t(reason),
                                                          )
                                                          .join(' · ')
                                                    : t('No signals')
                                            }
                                            aria-label={t('Relevance :score', {
                                                score: line.score,
                                            })}
                                        >
                                            {line.score}
                                        </span>
                                        {line.priority && (
                                            <Badge variant="outline">
                                                {t('Priority source')}
                                            </Badge>
                                        )}
                                        <span className="font-medium text-foreground">
                                            {line.outlets.join(', ') ||
                                                line.outlet}
                                        </span>
                                        <span>
                                            {formatDateTime(
                                                line.published_at,
                                                locale,
                                            )}
                                        </span>
                                        {line.story_count > 1 && (
                                            <Badge variant="secondary">
                                                {t(':count articles', {
                                                    count: line.story_count,
                                                })}
                                            </Badge>
                                        )}
                                    </div>
                                    <Link
                                        href={show(line.id)}
                                        className="block text-sm font-medium hover:underline"
                                    >
                                        {line.headline}
                                    </Link>
                                </div>
                                <div className="flex items-center gap-1">
                                    <Button
                                        asChild
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Open original article')}
                                    >
                                        <a
                                            href={line.url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <ExternalLink />
                                        </a>
                                    </Button>
                                    {line.status !== 'relevant' && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                triage(line, 'relevant')
                                            }
                                        >
                                            <Check />
                                            {t('Relevant')}
                                        </Button>
                                    )}
                                    {line.status !== 'irrelevant' && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                triage(line, 'irrelevant')
                                            }
                                        >
                                            <X />
                                            {t('Not relevant')}
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

News.layout = {
    breadcrumbs: [{ title: 'News coverage', href: index() }],
};
