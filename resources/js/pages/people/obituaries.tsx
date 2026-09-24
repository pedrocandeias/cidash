import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate, formatDateTime, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import PeopleTabs from '@/modules/people/people-tabs';
import { PersonPhoto } from '@/modules/people/person-card';
import type { PersonSummary } from '@/modules/people/types';
import { obituaries, show } from '@/routes/people';

type Entry = PersonSummary & {
    has_obituary: boolean;
    obituary_updated_at: string | null;
};

type Props = {
    people: Entry[];
    filter: 'all' | 'alive' | 'deceased';
    candidates: { id: string; name: string }[];
};

const filters = [
    { value: 'all', label: 'Everyone' },
    { value: 'alive', label: 'Alive, obituary prepared' },
    { value: 'deceased', label: 'Deceased' },
] as const;

export default function Obituaries({ people, filter, candidates }: Props) {
    const { t, locale } = useTranslation();
    const [candidate, setCandidate] = useState('');

    return (
        <>
            <Head title={t('Dead or Alive')} />

            <div className="space-y-6 px-4 py-6">
                <Heading
                    title={t('People of interest')}
                    description={t('Experts to suggest to the media')}
                />

                <PeopleTabs current="obituaries" />

                <p className="max-w-prose text-sm text-muted-foreground">
                    {t(
                        'Obituaries prepared in advance, ready to publish when needed, and the people who have died. The deceased are no longer suggested to the media.',
                    )}
                </p>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <nav className="flex gap-1" aria-label={t('Status')}>
                        {filters.map((option) => (
                            <Link
                                key={option.value}
                                href={obituaries({
                                    query:
                                        option.value === 'all'
                                            ? {}
                                            : { filter: option.value },
                                })}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm',
                                    filter === option.value
                                        ? 'bg-muted font-bold'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {t(option.label)}
                            </Link>
                        ))}
                    </nav>
                    {candidates.length > 0 && (
                        <div className="flex items-center gap-2">
                            <Select
                                value={candidate}
                                onValueChange={setCandidate}
                            >
                                <SelectTrigger
                                    className="w-64"
                                    aria-label={t('Prepare an obituary for…')}
                                >
                                    <SelectValue
                                        placeholder={t(
                                            'Prepare an obituary for…',
                                        )}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {candidates.map((person) => (
                                        <SelectItem
                                            key={person.id}
                                            value={person.id}
                                        >
                                            {person.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button
                                variant="outline"
                                disabled={candidate === ''}
                                onClick={() =>
                                    router.visit(show(candidate).url)
                                }
                            >
                                {t('Open profile')}
                            </Button>
                        </div>
                    )}
                </div>

                {people.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('No obituaries here yet.')}
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {people.map((person) => (
                            <li
                                key={person.id}
                                className="flex flex-wrap items-center gap-4 px-4 py-3"
                            >
                                <PersonPhoto person={person} size="size-12" />
                                <div className="min-w-48 flex-1">
                                    <Link
                                        href={show(person.id)}
                                        className="font-bold hover:underline"
                                    >
                                        {person.name}
                                    </Link>
                                    <p className="text-sm text-muted-foreground">
                                        {[
                                            person.academic_title,
                                            person.affiliation,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                </div>
                                {person.deceased_on ? (
                                    <Badge variant="secondary">
                                        {t('Died on :date', {
                                            date: formatDate(
                                                person.deceased_on,
                                                locale,
                                            ),
                                        })}
                                    </Badge>
                                ) : (
                                    <Badge variant="outline">
                                        {t('Alive')}
                                    </Badge>
                                )}
                                <span className="w-56 text-right text-xs text-muted-foreground">
                                    {person.has_obituary &&
                                    person.obituary_updated_at
                                        ? t('Obituary updated :date', {
                                              date: formatDateTime(
                                                  person.obituary_updated_at,
                                                  locale,
                                              ),
                                          })
                                        : t('No obituary prepared.')}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

Obituaries.layout = {
    breadcrumbs: [{ title: 'Dead or Alive', href: obituaries() }],
};
