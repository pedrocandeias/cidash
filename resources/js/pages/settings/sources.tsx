import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslation } from '@/lib/i18n';
import { index, update } from '@/routes/subscriptions';

type Source = {
    id: number;
    name: string;
    kind: string;
    subscribed: boolean;
    is_priority: boolean;
    only_matching: boolean;
};

export default function SourceSubscriptions({
    sources,
}: {
    sources: Source[];
}) {
    const { t } = useTranslation();
    const save = (
        source: Source,
        data: {
            subscribed: boolean;
            is_priority?: boolean;
            only_matching?: boolean;
        },
    ) => router.patch(update(source.id).url, data, { preserveScroll: true });

    return (
        <>
            <Head title={t('Sources')} />
            <Heading
                variant="small"
                title={t('Sources')}
                description={t(
                    'Choose the sources whose news reach the team. Priority sources are highlighted. With "only matching", only articles that match a monitoring rule reach the news inbox.',
                )}
            />
            <ul className="divide-y rounded-lg border">
                {sources.map((source) => (
                    <li
                        key={source.id}
                        className="flex flex-wrap items-center gap-4 px-4 py-3 text-sm"
                    >
                        <label className="flex flex-1 items-center gap-2">
                            <Checkbox
                                checked={source.subscribed}
                                onCheckedChange={(checked) =>
                                    save(source, {
                                        subscribed: checked === true,
                                    })
                                }
                            />
                            {source.name}
                        </label>
                        {source.subscribed && (
                            <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                <Checkbox
                                    checked={source.is_priority}
                                    onCheckedChange={(checked) =>
                                        save(source, {
                                            subscribed: true,
                                            is_priority: checked === true,
                                            only_matching: source.only_matching,
                                        })
                                    }
                                />
                                {t('Priority')}
                            </label>
                        )}
                        {source.subscribed && (
                            <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                <Checkbox
                                    checked={source.only_matching}
                                    onCheckedChange={(checked) =>
                                        save(source, {
                                            subscribed: true,
                                            is_priority: source.is_priority,
                                            only_matching: checked === true,
                                        })
                                    }
                                />
                                {t('Only articles matching the rules')}
                            </label>
                        )}
                    </li>
                ))}
            </ul>
        </>
    );
}

SourceSubscriptions.layout = {
    breadcrumbs: [{ title: 'Sources', href: index() }],
};
