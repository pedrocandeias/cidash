import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime, useTranslation } from '@/lib/i18n';
import { edit, test, update } from '@/routes/admin/social';

type Network = {
    key: 'mastodon' | 'bluesky' | 'youtube' | 'instagram';
    label: string;
    configured: boolean;
    interval: number;
    last_fetched_at: string | null;
    last_error: string | null;
};

type Props = {
    networks: Network[];
    settings: Record<string, Record<string, string | boolean | null>>;
    tags: string[];
};

type Field = {
    name: string;
    label: string;
    secret?: boolean;
    placeholder?: string;
};

const fields: Record<Network['key'], Field[]> = {
    mastodon: [
        {
            name: 'instance',
            label: 'Instance',
            placeholder: 'https://mastodon.social',
        },
    ],
    bluesky: [
        { name: 'handle', label: 'Account', placeholder: 'uporto.bsky.social' },
        { name: 'app_password', label: 'App password', secret: true },
    ],
    youtube: [{ name: 'api_key', label: 'API key', secret: true }],
    instagram: [
        {
            name: 'account_id',
            label: 'Instagram Business account ID',
            placeholder: '17841400000000000',
        },
        { name: 'access_token', label: 'Access token', secret: true },
    ],
};

const help: Record<Network['key'], string> = {
    mastodon:
        'Works without an account: public hashtag timelines of one instance. Leave empty to use mastodon.social.',
    bluesky:
        'Search needs a Bluesky account. Create an app password in Bluesky → Settings → Privacy and security → App passwords.',
    youtube:
        'A free YouTube Data API v3 key from Google Cloud. The daily quota allows a search every three hours per hashtag.',
    instagram:
        'Needs an Instagram Business account linked to a Facebook page and a Meta app with Instagram Public Content Access. Meta allows 30 different hashtags per week and does not return who posted.',
};

function NetworkCard({
    network,
    values,
    tags,
}: {
    network: Network;
    values: Record<string, string | boolean | null>;
    tags: string[];
}) {
    const { t, locale } = useTranslation();
    const [tag, setTag] = useState(tags[0] ?? 'uporto');

    return (
        <section className="space-y-4 rounded-lg border p-4">
            <div className="flex flex-wrap items-center gap-2">
                <h2 className="text-base font-bold">{network.label}</h2>
                <Badge variant={network.configured ? 'secondary' : 'outline'}>
                    {t(network.configured ? 'Set up' : 'Not set up')}
                </Badge>
                <span className="text-xs text-muted-foreground">
                    {network.interval >= 60
                        ? t('Every :hours hours', {
                              hours: network.interval / 60,
                          })
                        : t('Every :minutes minutes', {
                              minutes: network.interval,
                          })}
                    {network.last_fetched_at &&
                        ` · ${t('last collection :date', { date: formatDateTime(network.last_fetched_at, locale) })}`}
                </span>
            </div>
            <p className="text-sm text-muted-foreground">
                {t(help[network.key])}
            </p>
            {network.last_error && (
                <p className="rounded-md border border-critical/25 bg-critical-surface px-3 py-2 text-sm">
                    {network.last_error}
                </p>
            )}

            {fields[network.key].length > 0 && (
                <Form
                    {...update.form(network.key)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess={fields[network.key]
                        .filter((field) => field.secret)
                        .map((field) => field.name)}
                    className="grid gap-3 sm:grid-cols-2"
                >
                    {({ processing, errors }) => (
                        <>
                            {fields[network.key].map((field) => (
                                <div key={field.name} className="grid gap-1.5">
                                    <Label
                                        htmlFor={`${network.key}-${field.name}`}
                                    >
                                        {t(field.label)}
                                    </Label>
                                    <Input
                                        id={`${network.key}-${field.name}`}
                                        name={field.name}
                                        type={
                                            field.secret ? 'password' : 'text'
                                        }
                                        autoComplete="off"
                                        defaultValue={
                                            field.secret
                                                ? ''
                                                : ((values[field.name] as
                                                      | string
                                                      | null) ?? '')
                                        }
                                        placeholder={
                                            field.secret &&
                                            values[`has_${field.name}`]
                                                ? t(
                                                      'Saved. Leave empty to keep it.',
                                                  )
                                                : field.placeholder
                                        }
                                    />
                                    <InputError message={errors[field.name]} />
                                </div>
                            ))}
                            <div className="flex gap-2 sm:col-span-2">
                                <Button disabled={processing}>
                                    {t('Save')}
                                </Button>
                                {network.configured &&
                                    network.key !== 'mastodon' && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() =>
                                                router.put(
                                                    update(network.key).url,
                                                    { clear: true },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t('Forget credentials')}
                                        </Button>
                                    )}
                            </div>
                        </>
                    )}
                </Form>
            )}

            {network.configured && (
                <div className="flex flex-wrap items-center gap-2 border-t pt-4">
                    <Label htmlFor={`${network.key}-test`}>
                        {t('Try a hashtag')}
                    </Label>
                    <Input
                        id={`${network.key}-test`}
                        value={tag}
                        onChange={(event) => setTag(event.target.value)}
                        className="h-8 w-48"
                    />
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            router.post(
                                test(network.key).url,
                                { tag },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {t('Test')}
                    </Button>
                </div>
            )}
        </section>
    );
}

export default function SocialNetworks({ networks, settings, tags }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Social networks')} />

            <div className="space-y-6 px-4 py-6">
                <Heading
                    title={t('Social networks')}
                    description={t(
                        'Public posts with the hashtags the teams follow become mentions. Each team chooses its hashtags in Settings → Monitoring.',
                    )}
                />
                <p className="text-sm">
                    {tags.length === 0
                        ? t('No team follows a hashtag yet.')
                        : t('Hashtags followed: :tags', {
                              tags: tags.map((tag) => `#${tag}`).join(' '),
                          })}
                </p>
                <div className="grid gap-4 xl:grid-cols-2">
                    {networks.map((network) => (
                        <NetworkCard
                            key={network.key}
                            network={network}
                            values={settings[network.key] ?? {}}
                            tags={tags}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

SocialNetworks.layout = {
    breadcrumbs: [{ title: 'Social networks', href: edit() }],
};
