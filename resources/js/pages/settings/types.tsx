import { Form, Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import type { OptionList } from '@/lib/options';
import { index, store, update } from '@/routes/options';

type Option = {
    id: number;
    key: string;
    label: string;
    color: string | null;
    active: boolean;
};

const sections: { list: OptionList; title: string; description: string }[] = [
    {
        list: 'event_type',
        title: 'Event types',
        description: 'Shown in the calendar with their colour.',
    },
    {
        list: 'content_format',
        title: 'Content formats',
        description: 'The formats of the content pipeline.',
    },
    {
        list: 'task_type',
        title: 'Task types',
        description: 'What kind of work a task is: translation, video, speech…',
    },
    {
        list: 'asset_category',
        title: 'Asset categories',
        description: 'How images, videos and graphics are organised.',
    },
];

function OptionRow({
    option,
    withColor,
}: {
    option: Option;
    withColor: boolean;
}) {
    const { t } = useTranslation();
    const save = (data: Record<string, string | boolean>) =>
        router.patch(update(option.id).url, data, { preserveScroll: true });

    return (
        <li className="flex flex-wrap items-center gap-3 px-4 py-2">
            {withColor && (
                <input
                    type="color"
                    aria-label={t('Colour')}
                    defaultValue={option.color ?? '#6b7280'}
                    className="size-8 cursor-pointer rounded border bg-transparent"
                    onBlur={(event) =>
                        event.target.value !== option.color &&
                        save({ color: event.target.value })
                    }
                />
            )}
            <Input
                aria-label={t('Name')}
                defaultValue={t(option.label)}
                className="h-8 max-w-64 flex-1"
                onBlur={(event) => {
                    const label = event.target.value.trim();

                    if (label !== '' && label !== t(option.label)) {
                        save({ label });
                    }
                }}
            />
            <label className="flex items-center gap-2 text-sm text-muted-foreground">
                <Checkbox
                    checked={option.active}
                    onCheckedChange={(checked) =>
                        save({ active: checked === true })
                    }
                />
                {t('In use')}
            </label>
        </li>
    );
}

export default function Types({
    lists,
}: {
    lists: Record<OptionList, Option[]>;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Types')} />

            <div className="space-y-10">
                {sections.map((section) => (
                    <div key={section.list} className="space-y-4">
                        <Heading
                            variant="small"
                            title={t(section.title)}
                            description={`${t(section.description)} ${t('Entries in use by records are switched off, not deleted.')}`}
                        />
                        <ul className="divide-y rounded-lg border">
                            {lists[section.list].map((option) => (
                                <OptionRow
                                    key={option.id}
                                    option={option}
                                    withColor={section.list === 'event_type'}
                                />
                            ))}
                        </ul>
                        <Form
                            {...store.form()}
                            resetOnSuccess
                            options={{ preserveScroll: true }}
                            className="flex flex-wrap items-start gap-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="list"
                                        value={section.list}
                                    />
                                    {section.list === 'event_type' && (
                                        <input
                                            type="color"
                                            name="color"
                                            aria-label={t('Colour')}
                                            defaultValue="#6b7280"
                                            className="size-9 cursor-pointer rounded border bg-transparent"
                                        />
                                    )}
                                    <div className="grid gap-1">
                                        <Input
                                            name="label"
                                            required
                                            aria-label={t('Name')}
                                            placeholder={t('New entry')}
                                            className="w-64"
                                        />
                                        <InputError message={errors.label} />
                                    </div>
                                    <Button
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        {t('Add')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                ))}
            </div>
        </>
    );
}

Types.layout = {
    breadcrumbs: [{ title: 'Types', href: index() }],
};
