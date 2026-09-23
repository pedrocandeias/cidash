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
};

export default function SourceSubscriptions({
    sources,
}: {
    sources: Source[];
}) {
    const { t } = useTranslation();
    const save = (
        source: Source,
        data: { subscribed: boolean; is_priority?: boolean },
    ) => router.patch(update(source.id).url, data, { preserveScroll: true });

    return (
        <>
            <Head title={t('Sources')} />
            <Heading
                variant="small"
                title={t('Sources')}
                description={t(
                    'Choose the sources whose news reach the team. Priority sources are highlighted.',
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
                                        })
                                    }
                                />
                                {t('Priority')}
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
