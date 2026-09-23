import { X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { suggest } from '@/routes/tags';

/** Same normalization as App\Core\Terms: no case, accents or extra spaces. */
export function normalizeTerm(term: string) {
    return term
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

type Suggestions = { matches: string[]; similar: string[] };

const defaultSuggestUrl = (q: string) => suggest.url({ query: { q } });

/**
 * Tag editor sent as `tags[]`. Existing tags are suggested while typing, and a
 * new tag that looks like a typo of an existing one asks "Did you mean…?"
 * before being added. The server also refuses duplicates (App\Core\Tags).
 */
export default function TagInput({
    defaultValue = [],
    name = 'tags[]',
    id = 'tags',
    suggestUrl = defaultSuggestUrl,
    placeholder = 'Add a tag and press Enter',
}: {
    defaultValue?: string[];
    /** Form field name; any vocabulary with a suggest endpoint works (tags, expertise areas). */
    name?: string;
    id?: string;
    suggestUrl?: (query: string) => string;
    placeholder?: string;
}) {
    const { t } = useTranslation();
    const [tags, setTags] = useState<string[]>(defaultValue);
    const [text, setText] = useState('');
    const [suggestions, setSuggestions] = useState<Suggestions>({
        matches: [],
        similar: [],
    });
    const [pending, setPending] = useState<{
        typed: string;
        similar: string;
    } | null>(null);

    // Kept in a ref so an inline function from the caller does not re-run the effect on every render.
    const suggestUrlRef = useRef(suggestUrl);
    useEffect(() => {
        suggestUrlRef.current = suggestUrl;
    });

    useEffect(() => {
        if (text.trim().length < 2) {
            setSuggestions({ matches: [], similar: [] });

            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            fetch(suggestUrlRef.current(text), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((response) => response.json())
                .then(setSuggestions)
                .catch(() => {});
        }, 200);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [text]);

    const add = (name: string) => {
        const clean = name.replace(/\s+/g, ' ').trim();

        if (
            clean !== '' &&
            !tags.some((tag) => normalizeTerm(tag) === normalizeTerm(clean))
        ) {
            setTags([...tags, clean]);
        }

        setText('');
        setPending(null);
    };

    const submitTyped = () => {
        const typed = text.trim();
        const exact = suggestions.matches.find(
            (match) => normalizeTerm(match) === normalizeTerm(typed),
        );

        if (exact) {
            add(exact);
        } else if (suggestions.similar.length > 0) {
            setPending({ typed, similar: suggestions.similar[0] });
        } else {
            add(typed);
        }
    };

    const visibleMatches = suggestions.matches.filter(
        (match) =>
            !tags.some((tag) => normalizeTerm(tag) === normalizeTerm(match)),
    );

    return (
        <div className="space-y-2">
            {tags.map((tag) => (
                <input key={tag} type="hidden" name={name} value={tag} />
            ))}

            {tags.length > 0 && (
                <div className="flex flex-wrap gap-1">
                    {tags.map((tag) => (
                        <Badge key={tag} variant="secondary" className="gap-1">
                            {tag}
                            <button
                                type="button"
                                aria-label={t('Remove :name', { name: tag })}
                                onClick={() =>
                                    setTags(
                                        tags.filter((other) => other !== tag),
                                    )
                                }
                            >
                                <X className="size-3" />
                            </button>
                        </Badge>
                    ))}
                </div>
            )}

            <Input
                id={id}
                value={text}
                placeholder={t(placeholder)}
                onChange={(event) => {
                    setText(event.target.value);
                    setPending(null);
                }}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ',') {
                        event.preventDefault();
                        submitTyped();
                    }
                }}
            />

            {pending && (
                <p className="text-sm">
                    {t('Did you mean')}{' '}
                    <button
                        type="button"
                        className="font-medium underline"
                        onClick={() => add(pending.similar)}
                    >
                        {pending.similar}
                    </button>
                    ?{' '}
                    <button
                        type="button"
                        className="text-muted-foreground underline"
                        onClick={() => add(pending.typed)}
                    >
                        {t('Keep ":tag"', { tag: pending.typed })}
                    </button>
                </p>
            )}

            {!pending && visibleMatches.length > 0 && (
                <div className="flex flex-wrap gap-1">
                    {visibleMatches.map((match) => (
                        <button
                            key={match}
                            type="button"
                            className="rounded-md border px-2 py-0.5 text-xs hover:bg-muted"
                            onClick={() => add(match)}
                        >
                            {match}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
