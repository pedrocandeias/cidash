import { Head, Link, router } from '@inertiajs/react';
import { Check, ExternalLink, X } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index, show, update } from '@/routes/mentions';

type MentionLine = {
    id: string;
    headline: string;
    outlet: string | null;
    url: string;
    published_at: string | null;
    matched_keyword: string;
    rule: string | null;
    status: 'new' | 'relevant' | 'irrelevant' | 'archived';
    network: string | null;
    author: string | null;
};

const tabs = [
    { value: 'new', label: 'To triage' },
    { value: 'relevant', label: 'Marked relevant' },
    { value: 'irrelevant', label: 'Marked not relevant' },
    { value: 'all', label: 'All' },
] as const;

const sources = [
    { value: 'all', label: 'News and social networks' },
    { value: 'news', label: 'News coverage' },
    { value: 'social', label: 'Social networks' },
] as const;

const networkOptions = [
    { value: 'mastodon', label: 'Mastodon' },
    { value: 'bluesky', label: 'Bluesky' },
    { value: 'youtube', label: 'YouTube' },
    { value: 'instagram', label: 'Instagram' },
] as const;

export default function Mentions({
    mentions,
    status,
    source,
    networks,
    networkCounts,
    counts,
}: {
    mentions: MentionLine[];
    status: string;
    source: 'all' | 'news' | 'social';
    networks: string[];
    networkCounts: Record<string, number> | null;
    counts: Record<string, number>;
}) {
    const { t, locale } = useTranslation();
    const review = (mention: MentionLine, next: 'relevant' | 'irrelevant') =>
        router.patch(
            update(mention.id).url,
            { review_status: next },
            { preserveScroll: true, preserveState: true },
        );

    // Picking networks keeps the status tab; none picked shows every network.
    const toggleNetwork = (network: string) => {
        const next = networks.includes(network)
            ? networks.filter((other) => other !== network)
            : [...networks, network];

        router.get(
            index().url,
            {
                ...(status === 'new' ? {} : { status }),
                source: 'social',
                ...(next.length > 0 ? { networks: next } : {}),
            },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={t('Media mentions')} />

            <div className="space-y-6 px-4 py-6">
                <Heading
                    title={t('Media mentions')}
                    description={t(
                        'News and social posts that match the team monitoring rules and hashtags.',
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
                                    ...(source === 'all' ? {} : { source }),
                                    ...(networks.length > 0
                                        ? { networks }
                                        : {}),
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
                    {sources.map((option) => (
                        <Link
                            key={option.value}
                            href={index({
                                query: {
                                    ...(status === 'new' ? {} : { status }),
                                    ...(option.value === 'all'
                                        ? {}
                                        : { source: option.value }),
                                },
                            })}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm',
                                source === option.value
                                    ? 'bg-muted font-medium'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t(option.label)}
                        </Link>
                    ))}
                </nav>

                {source === 'social' && (
                    <div
                        className="flex flex-wrap items-center gap-2"
                        role="group"
                        aria-label={t('Social networks')}
                    >
                        {networkOptions.map((option) => {
                            const active = networks.includes(option.value);

                            return (
                                <button
                                    key={option.value}
                                    type="button"
                                    aria-pressed={active}
                                    onClick={() => toggleNetwork(option.value)}
                                    className={cn(
                                        'rounded-md border px-3 py-1 text-sm',
                                        active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                    )}
                                >
                                    {option.label}
                                    <span className="ml-1.5 text-xs tabular-nums opacity-80">
                                        {networkCounts?.[option.value] ?? 0}
                                    </span>
                                </button>
                            );
                        })}
                        {networks.length > 0 && (
                            <button
                                type="button"
                                className="text-xs text-muted-foreground underline hover:text-foreground"
                                onClick={() =>
                                    router.get(
                                        index().url,
                                        {
                                            ...(status === 'new'
                                                ? {}
                                                : { status }),
                                            source: 'social',
                                        },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {t('All networks')}
                            </button>
                        )}
                    </div>
                )}

                {mentions.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t(
                            status === 'new'
                                ? 'Nothing left to triage.'
                                : 'No mentions here.',
                        )}
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {mentions.map((mention) => (
                            <li
                                key={mention.id}
                                className="flex flex-wrap items-start gap-3 px-4 py-3"
                            >
                                <div className="min-w-64 flex-1 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        <span className="font-medium text-foreground">
                                            {mention.outlet}
                                        </span>
                                        {mention.author && (
                                            <span>{mention.author}</span>
                                        )}
                                        {mention.published_at && (
                                            <span>
                                                {formatDateTime(
                                                    mention.published_at,
                                                    locale,
                                                )}
                                            </span>
                                        )}
                                        <Badge variant="secondary">
                                            {mention.matched_keyword}
                                        </Badge>
                                        {mention.rule && (
                                            <span>· {mention.rule}</span>
                                        )}
                                    </div>
                                    <Link
                                        href={show(mention.id)}
                                        className="block text-sm font-medium hover:underline"
                                    >
                                        {mention.headline}
                                    </Link>
                                </div>
                                <div className="flex items-center gap-1">
                                    <Button
                                        asChild
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t(
                                            mention.network
                                                ? 'Open original post'
                                                : 'Open original article',
                                        )}
                                    >
                                        <a
                                            href={mention.url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <ExternalLink />
                                        </a>
                                    </Button>
                                    {mention.status !== 'relevant' && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                review(mention, 'relevant')
                                            }
                                        >
                                            <Check />
                                            {t('Relevant')}
                                        </Button>
                                    )}
                                    {mention.status !== 'irrelevant' && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                review(mention, 'irrelevant')
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

Mentions.layout = {
    breadcrumbs: [{ title: 'Media mentions', href: index() }],
};
