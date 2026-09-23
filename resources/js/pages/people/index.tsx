import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/lib/i18n';
import PersonCard from '@/modules/people/person-card';
import PersonFields, {
    personFormTransform,
} from '@/modules/people/person-fields';
import type { PersonSummary } from '@/modules/people/types';
import { index, store } from '@/routes/people';

type Props = {
    people: PersonSummary[];
    filters: { q: string; area: number | null };
    areas: { id: number; name: string }[];
};

const ALL = 'all';

export default function People({ people, filters, areas }: Props) {
    const { t } = useTranslation();
    const [creating, setCreating] = useState(false);
    const [query, setQuery] = useState(filters.q);

    const search = (next: { q?: string; area?: string }) => {
        const q = next.q ?? query;
        const area = next.area ?? String(filters.area ?? ALL);

        router.get(
            index().url,
            { ...(q ? { q } : {}), ...(area !== ALL ? { area } : {}) },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title={t('People of interest')} />

            <div className="space-y-6 px-4 py-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('People of interest')}
                        description={t('Experts to suggest to the media')}
                    />
                    <Dialog open={creating} onOpenChange={setCreating}>
                        <DialogTrigger asChild>
                            <Button>{t('New profile')}</Button>
                        </DialogTrigger>
                        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                            <DialogTitle>{t('New profile')}</DialogTitle>
                            <Form
                                {...store.form()}
                                transform={personFormTransform}
                                onSuccess={() => setCreating(false)}
                                className="space-y-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <PersonFields errors={errors} />
                                        <Button disabled={processing}>
                                            {t('Create profile')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                <form
                    className="flex flex-wrap gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        search({ q: query });
                    }}
                >
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder={t('Search by name, topic, affiliation…')}
                        aria-label={t('Search')}
                        className="max-w-sm"
                    />
                    <Select
                        value={String(filters.area ?? ALL)}
                        onValueChange={(area) => search({ area })}
                    >
                        <SelectTrigger
                            className="w-56"
                            aria-label={t('Expertise area')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('All areas')}
                            </SelectItem>
                            {areas.map((area) => (
                                <SelectItem
                                    key={area.id}
                                    value={String(area.id)}
                                >
                                    {area.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button type="submit" variant="outline">
                        {t('Search')}
                    </Button>
                </form>

                {people.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('Nobody found.')}
                    </p>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {people.map((person) => (
                            <PersonCard key={person.id} person={person} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

People.layout = {
    breadcrumbs: [{ title: 'People of interest', href: index() }],
};
