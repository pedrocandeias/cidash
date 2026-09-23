import { Link, router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { destroy, store } from '@/routes/links';
import { search } from '@/routes/records';

export type RecordSummary = {
    id: string;
    type: string;
    label: string;
    title: string;
    url: string | null;
};

export type RelationItem = {
    id: number;
    type: string;
    outgoing: boolean;
    record: RecordSummary;
};

/** How a relation reads from this record's side. */
function relationLabel(type: string, outgoing: boolean): string | null {
    switch (type) {
        case 'originated_from':
            return outgoing ? 'Created from' : 'Originated';
        case 'part_of':
            return outgoing ? 'Part of' : 'Includes';
        case 'covers':
            return outgoing ? 'Covers' : 'Covered by';
        case 'mentions':
            return outgoing ? 'Mentions' : 'Mentioned in';
        default:
            return null;
    }
}

export default function RelationsPanel({
    recordId,
    relations,
}: {
    recordId: string;
    relations: RelationItem[];
}) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<RecordSummary[]>([]);

    useEffect(() => {
        if (query.trim().length < 2) {
            setResults([]);

            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            fetch(search.url({ query: { q: query, exclude: recordId } }), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((response) => response.json())
                .then(setResults)
                .catch(() => {});
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query, recordId]);

    const linked = new Set(relations.map((relation) => relation.record.id));

    return (
        <div className="space-y-4">
            <h2 className="text-base font-medium">{t('Relations')}</h2>

            {relations.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {t('Not linked to anything yet.')}
                </p>
            )}

            <ul className="space-y-2 text-sm">
                {relations.map((relation) => {
                    const label = relationLabel(
                        relation.type,
                        relation.outgoing,
                    );

                    return (
                        <li
                            key={relation.id}
                            className="flex items-start gap-2"
                        >
                            <div className="min-w-0 flex-1">
                                <span className="text-xs text-muted-foreground">
                                    {t(relation.record.label)}
                                    {label && ` · ${t(label)}`}
                                </span>
                                {relation.record.url ? (
                                    <Link
                                        href={relation.record.url}
                                        className="block truncate hover:underline"
                                    >
                                        {relation.record.title}
                                    </Link>
                                ) : (
                                    <span className="block truncate">
                                        {relation.record.title}
                                    </span>
                                )}
                            </div>
                            <button
                                type="button"
                                className="text-muted-foreground hover:text-foreground"
                                aria-label={t('Remove link')}
                                onClick={() =>
                                    router.delete(destroy(relation.id).url, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <X className="size-4" />
                            </button>
                        </li>
                    );
                })}
            </ul>

            <div className="space-y-1">
                <Input
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder={t('Link to… (search by title)')}
                    aria-label={t('Link to… (search by title)')}
                />
                {results
                    .filter((result) => !linked.has(result.id))
                    .map((result) => (
                        <button
                            key={result.id}
                            type="button"
                            className="block w-full truncate rounded-md px-2 py-1 text-left text-sm hover:bg-muted"
                            onClick={() => {
                                router.post(
                                    store(recordId).url,
                                    { target_id: result.id },
                                    { preserveScroll: true },
                                );
                                setQuery('');
                            }}
                        >
                            <span className="text-xs text-muted-foreground">
                                {t(result.label)} ·{' '}
                            </span>
                            {result.title}
                        </button>
                    ))}
            </div>
        </div>
    );
}
