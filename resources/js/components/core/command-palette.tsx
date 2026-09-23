import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index as campaigns } from '@/routes/campaigns';
import { index as content } from '@/routes/content';
import { index as events } from '@/routes/events';
import { index as notices } from '@/routes/notices';
import { index as people } from '@/routes/people';
import { index as press } from '@/routes/press';
import { search } from '@/routes/records';
import { index as tasks } from '@/routes/tasks';
import type { RecordSummary } from './relations-panel';

type Result = RecordSummary & { snippet?: string };

const shortcuts = [
    { title: 'Calendar', url: () => events().url },
    { title: 'Tasks', url: () => tasks().url },
    { title: 'Content', url: () => content().url },
    { title: 'Campaigns', url: () => campaigns().url },
    { title: 'Press requests', url: () => press().url },
    { title: 'People of interest', url: () => people().url },
    { title: 'Notices', url: () => notices().url },
];

/**
 * Global search and navigation (⌘K / Ctrl+K), over every record of the workspace.
 */
export function CommandPalette() {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Result[]>([]);
    const [active, setActive] = useState(0);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                setOpen((value) => !value);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    useEffect(() => {
        if (query.trim().length < 2) {
            setResults([]);

            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            fetch(search.url({ query: { q: query } }), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((response) => response.json())
                .then((data: Result[]) => {
                    setResults(data);
                    setActive(0);
                })
                .catch(() => {});
        }, 200);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query]);

    const items = useMemo(
        () =>
            query.trim().length < 2
                ? shortcuts.map((shortcut) => ({
                      key: shortcut.title,
                      label: t(shortcut.title),
                      detail: '',
                      url: shortcut.url(),
                  }))
                : results.map((result) => ({
                      key: result.id,
                      label: result.title,
                      detail: `${t(result.label)}${result.snippet ? ` · ${result.snippet}` : ''}`,
                      url: result.url,
                  })),
        [query, results, t],
    );

    const go = (url: string | null) => {
        if (url) {
            setOpen(false);
            setQuery('');
            router.visit(url);
        }
    };

    return (
        <>
            <Button
                variant="outline"
                size="sm"
                className="gap-2 text-muted-foreground"
                onClick={() => setOpen(true)}
            >
                <Search className="size-4" />
                <span className="hidden sm:inline">{t('Search')}</span>
                <kbd className="hidden rounded border px-1 text-[10px] sm:inline">
                    Ctrl K
                </kbd>
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="top-[20%] translate-y-0 gap-0 p-0 sm:max-w-xl">
                    <DialogTitle className="sr-only">{t('Search')}</DialogTitle>
                    <div className="flex items-center gap-2 border-b px-3">
                        <Search className="size-4 text-muted-foreground" />
                        <input
                            autoFocus
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'ArrowDown') {
                                    event.preventDefault();
                                    setActive((value) =>
                                        Math.min(value + 1, items.length - 1),
                                    );
                                } else if (event.key === 'ArrowUp') {
                                    event.preventDefault();
                                    setActive((value) =>
                                        Math.max(value - 1, 0),
                                    );
                                } else if (event.key === 'Enter') {
                                    event.preventDefault();
                                    go(items[active]?.url ?? null);
                                }
                            }}
                            placeholder={t(
                                'Search tasks, events, people, press requests…',
                            )}
                            aria-label={t('Search')}
                            className="h-12 flex-1 bg-transparent text-sm outline-none"
                        />
                    </div>
                    <ul className="max-h-80 overflow-y-auto p-1" role="listbox">
                        {query.trim().length >= 2 && items.length === 0 && (
                            <li className="px-3 py-6 text-center text-sm text-muted-foreground">
                                {t('No results.')}
                            </li>
                        )}
                        {items.map((item, position) => (
                            <li
                                key={item.key}
                                role="option"
                                aria-selected={position === active}
                            >
                                <button
                                    type="button"
                                    onMouseEnter={() => setActive(position)}
                                    onClick={() => go(item.url)}
                                    className={cn(
                                        'w-full rounded-md px-3 py-2 text-left',
                                        position === active && 'bg-muted',
                                    )}
                                >
                                    <span className="block truncate text-sm font-medium">
                                        {item.label}
                                    </span>
                                    {item.detail && (
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {item.detail}
                                        </span>
                                    )}
                                </button>
                            </li>
                        ))}
                    </ul>
                </DialogContent>
            </Dialog>
        </>
    );
}
