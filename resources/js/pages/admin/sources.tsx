import { Form, Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime, useTranslation } from '@/lib/i18n';
import {
    destroy,
    fetch as fetchNow,
    index,
    store,
    update,
} from '@/routes/admin/sources';

type Source = {
    id: number;
    name: string;
    kind: 'rss' | 'google_news' | 'scraper';
    url: string;
    active: boolean;
    poll_minutes: number;
    last_fetched_at: string | null;
    last_error: string | null;
    consecutive_failures: number;
    subscribers: number;
    items: number;
};

const kindLabels = {
    rss: 'RSS / Atom',
    google_news: 'Google News',
    scraper: 'Scraper (HTML)',
};

export default function AdminSources({ sources }: { sources: Source[] }) {
    const { t, locale } = useTranslation();

    return (
        <>
            <Head title={t('Sources')} />

            <div className="space-y-8 px-4 py-6">
                <Heading
                    title={t('Sources')}
                    description={t(
                        'Catalogue of news sources. Each team chooses which ones it follows.',
                    )}
                />

                <ul className="divide-y rounded-lg border">
                    {sources.map((source) => (
                        <li
                            key={source.id}
                            className="flex flex-wrap items-center gap-3 p-4"
                        >
                            <div className="min-w-64 flex-1">
                                <p className="text-sm font-medium">
                                    {source.name}{' '}
                                    <span className="text-xs font-normal text-muted-foreground">
                                        · {kindLabels[source.kind]}
                                    </span>
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {source.url}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {t(':count articles', {
                                        count: source.items,
                                    })}{' '}
                                    ·{' '}
                                    {t(':count teams', {
                                        count: source.subscribers,
                                    })}
                                    {source.last_fetched_at &&
                                        ` · ${t('last collected :date', { date: formatDateTime(source.last_fetched_at, locale) })}`}
                                </p>
                                {source.last_error && (
                                    <p className="text-xs text-red-600">
                                        {t(':count failures in a row', {
                                            count: source.consecutive_failures,
                                        })}
                                        : {source.last_error}
                                    </p>
                                )}
                            </div>
                            {!source.active && (
                                <Badge variant="secondary">{t('Paused')}</Badge>
                            )}
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        fetchNow(source.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {t('Collect now')}
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() =>
                                    router.patch(
                                        update(source.id).url,
                                        { active: !source.active },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {t(source.active ? 'Pause' : 'Resume')}
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() =>
                                    router.delete(destroy(source.id).url, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                {t('Delete')}
                            </Button>
                        </li>
                    ))}
                </ul>

                <div className="max-w-2xl space-y-4">
                    <Heading
                        variant="small"
                        title={t('New source')}
                        description={t(
                            'Prefer RSS when the outlet publishes it. The scraper respects robots.txt and stores metadata only.',
                        )}
                    />
                    <Form
                        {...store.form()}
                        resetOnSuccess
                        options={{ preserveScroll: true }}
                        className="grid gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">
                                            {t('Name')}
                                        </Label>
                                        <Input id="name" name="name" required />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="kind">
                                            {t('Type')}
                                        </Label>
                                        <Select name="kind" defaultValue="rss">
                                            <SelectTrigger id="kind">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(kindLabels).map(
                                                    ([value, label]) => (
                                                        <SelectItem
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="url">{t('Address')}</Label>
                                    <Input
                                        id="url"
                                        name="url"
                                        type="url"
                                        required
                                        placeholder="https://"
                                    />
                                    <InputError message={errors.url} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="config">
                                        {t('Scraper settings (JSON, optional)')}
                                    </Label>
                                    <Input
                                        id="config"
                                        name="config"
                                        placeholder='{"sections": ["https://…"], "link_selector": "article a"}'
                                    />
                                    <InputError message={errors.config} />
                                </div>
                                <Button
                                    disabled={processing}
                                    className="justify-self-start"
                                >
                                    {t('Add source')}
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

AdminSources.layout = {
    breadcrumbs: [{ title: 'Sources', href: index() }],
};
