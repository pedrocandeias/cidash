import { Form, Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/lib/i18n';
import { destroy, index, store, update } from '@/routes/rules';

type Rule = {
    id: number;
    name: string;
    include_terms: string[];
    exclude_terms: string[];
    person_id: string | null;
    person: string | null;
    category: string | null;
    google_news: boolean;
    active: boolean;
};

const NONE = 'none';

export default function Monitoring({
    rules,
    people,
}: {
    rules: Rule[];
    people: { id: string; name: string }[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Monitoring')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Monitoring rules')}
                    description={t(
                        'An article becomes a mention when it contains one of the terms (whole words, ignoring case and accents) and none of the excluded ones.',
                    )}
                />

                {rules.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('No rules yet.')}
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {rules.map((rule) => (
                            <li key={rule.id} className="space-y-2 p-4">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="flex-1 text-sm font-medium">
                                        {rule.name}
                                    </span>
                                    {!rule.active && (
                                        <Badge variant="secondary">
                                            {t('Paused')}
                                        </Badge>
                                    )}
                                    {rule.google_news && (
                                        <Badge variant="outline">
                                            Google News
                                        </Badge>
                                    )}
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            router.patch(
                                                update(rule.id).url,
                                                { active: !rule.active },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {t(rule.active ? 'Pause' : 'Resume')}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            router.delete(
                                                destroy(rule.id).url,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {t('Delete')}
                                    </Button>
                                </div>
                                <div className="flex flex-wrap gap-1">
                                    {rule.include_terms.map((term) => (
                                        <Badge key={term} variant="secondary">
                                            {term}
                                        </Badge>
                                    ))}
                                    {rule.person && (
                                        <Badge variant="secondary">
                                            {rule.person}
                                        </Badge>
                                    )}
                                    {rule.exclude_terms.map((term) => (
                                        <Badge
                                            key={term}
                                            variant="outline"
                                            className="line-through"
                                        >
                                            {term}
                                        </Badge>
                                    ))}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <Heading variant="small" title={t('New rule')} />
                <Form
                    {...store.form()}
                    resetOnSuccess
                    options={{ preserveScroll: true }}
                    transform={(data) => ({
                        ...data,
                        person_id:
                            data.person_id === NONE ? null : data.person_id,
                        google_news: data.google_news === 'on',
                    })}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('Name')}</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    placeholder={t('e.g. University of Porto')}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="include_terms">
                                    {t('Terms')}
                                </Label>
                                <Input
                                    id="include_terms"
                                    name="include_terms"
                                    required
                                    placeholder={t(
                                        'Separated by commas, e.g. Universidade do Porto, U.Porto',
                                    )}
                                />
                                <InputError message={errors.include_terms} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="exclude_terms">
                                    {t('Exclude')}
                                </Label>
                                <Input
                                    id="exclude_terms"
                                    name="exclude_terms"
                                    placeholder={t(
                                        'Optional, separated by commas',
                                    )}
                                />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="person_id">
                                        {t('Person of interest')}
                                    </Label>
                                    <Select
                                        name="person_id"
                                        defaultValue={NONE}
                                    >
                                        <SelectTrigger id="person_id">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>
                                                {t('Nobody')}
                                            </SelectItem>
                                            {people.map((person) => (
                                                <SelectItem
                                                    key={person.id}
                                                    value={person.id}
                                                >
                                                    {person.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="category">
                                        {t('Category')}
                                    </Label>
                                    <Input
                                        id="category"
                                        name="category"
                                        placeholder={t('Optional')}
                                    />
                                </div>
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox name="google_news" defaultChecked />
                                {t('Also search Google News for these terms')}
                            </label>
                            <Button
                                disabled={processing}
                                className="justify-self-start"
                            >
                                {t('Add rule')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Monitoring.layout = {
    breadcrumbs: [{ title: 'Monitoring', href: index() }],
};
