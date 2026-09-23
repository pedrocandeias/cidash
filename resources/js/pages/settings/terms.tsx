import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/lib/i18n';
import { destroy, index, merge, update } from '@/routes/terms';

type Term = { id: number; name: string; usage: number };
type Kind = 'tags' | 'areas';

function TermRow({
    kind,
    term,
    others,
}: {
    kind: Kind;
    term: Term;
    others: Term[];
}) {
    const { t } = useTranslation();
    const [name, setName] = useState(term.name);

    return (
        <li className="flex flex-wrap items-center gap-2 px-3 py-2">
            <Input
                value={name}
                onChange={(event) => setName(event.target.value)}
                className="h-8 max-w-56"
                aria-label={t('Name')}
            />
            <span className="w-16 text-xs text-muted-foreground">
                {t(':count uses', { count: term.usage })}
            </span>
            {name !== term.name && (
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        router.patch(
                            update([kind, term.id]).url,
                            { name },
                            { preserveScroll: true },
                        )
                    }
                >
                    {t('Rename')}
                </Button>
            )}
            {others.length > 0 && (
                <Select
                    onValueChange={(into) =>
                        router.post(
                            merge([kind, term.id]).url,
                            { into },
                            { preserveScroll: true },
                        )
                    }
                >
                    <SelectTrigger
                        className="h-8 w-44"
                        aria-label={t('Merge into…')}
                    >
                        <SelectValue placeholder={t('Merge into…')} />
                    </SelectTrigger>
                    <SelectContent>
                        {others.map((other) => (
                            <SelectItem key={other.id} value={String(other.id)}>
                                {other.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}
            <Button
                size="sm"
                variant="ghost"
                onClick={() =>
                    router.delete(destroy([kind, term.id]).url, {
                        preserveScroll: true,
                    })
                }
            >
                {t('Delete')}
            </Button>
        </li>
    );
}

function TermList({
    kind,
    title,
    terms,
}: {
    kind: Kind;
    title: string;
    terms: Term[];
}) {
    const { t } = useTranslation();

    return (
        <div className="space-y-4">
            <Heading variant="small" title={t(title)} />
            {terms.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('None yet.')}
                </p>
            ) : (
                <ul className="divide-y rounded-lg border">
                    {terms.map((term) => (
                        <TermRow
                            key={term.id}
                            kind={kind}
                            term={term}
                            others={terms.filter(
                                (other) => other.id !== term.id,
                            )}
                        />
                    ))}
                </ul>
            )}
        </div>
    );
}

export default function Terms({
    tags,
    areas,
}: {
    tags: Term[];
    areas: Term[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Terms')} />
            <div className="space-y-2">
                <p className="text-sm text-muted-foreground">
                    {t(
                        'Rename, merge or delete the team tags and expertise areas. Renaming to an existing name merges both.',
                    )}
                </p>
            </div>
            <TermList kind="areas" title="Expertise areas" terms={areas} />
            <TermList kind="tags" title="Tags" terms={tags} />
        </>
    );
}

Terms.layout = {
    breadcrumbs: [{ title: 'Terms', href: index() }],
};
