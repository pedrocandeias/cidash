import { useEffect, useState } from 'react';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { search } from '@/routes/records';
import type { RecordSummary } from './relations-panel';

/**
 * Finds records of the current workspace by title and hands the chosen one to `onPick`.
 */
export default function RecordSearch({
    excludeId,
    excludeIds = [],
    placeholder,
    onPick,
}: {
    excludeId: string;
    excludeIds?: string[];
    placeholder: string;
    onPick: (record: RecordSummary) => void;
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
            fetch(search.url({ query: { q: query, exclude: excludeId } }), {
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
    }, [query, excludeId]);

    return (
        <div className="space-y-1">
            <Input
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder={placeholder}
                aria-label={placeholder}
            />
            {results
                .filter((result) => !excludeIds.includes(result.id))
                .map((result) => (
                    <button
                        key={result.id}
                        type="button"
                        className="block w-full truncate rounded-md px-2 py-1 text-left text-sm hover:bg-muted"
                        onClick={() => {
                            onPick(result);
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
    );
}
