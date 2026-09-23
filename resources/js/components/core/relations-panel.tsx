import { Link, router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useTranslation } from '@/lib/i18n';
import { destroy, store } from '@/routes/links';
import RecordSearch from './record-search';

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

            <RecordSearch
                excludeId={recordId}
                excludeIds={[...linked]}
                placeholder={t('Link to… (search by title)')}
                onPick={(record) =>
                    router.post(
                        store(recordId).url,
                        { target_id: record.id },
                        { preserveScroll: true },
                    )
                }
            />
        </div>
    );
}
