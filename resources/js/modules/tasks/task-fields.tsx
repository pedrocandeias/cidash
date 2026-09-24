import type { FormDataConvertible } from '@inertiajs/core';
import AssigneesField from '@/components/core/assignees-field';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { localToday, useTranslation } from '@/lib/i18n';
import { useOptions } from '@/lib/options';
import type { Member, Priority } from './types';
import { priorityLabels } from './types';

const NO_TYPE = 'none';

/** Form data from TaskFields; no one picked means nobody is responsible. */
export function taskFormTransform(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    return {
        ...data,
        type: data.type === NO_TYPE ? null : data.type,
        assignees: data.assignees ?? [],
        co_assignees: data.co_assignees ?? [],
    };
}

export default function TaskFields({
    members,
    errors,
    defaults = {},
    idPrefix = '',
}: {
    members: Member[];
    errors: Partial<Record<string, string>>;
    /** Needed when shown next to another form with the same field names. */
    idPrefix?: string;
    defaults?: {
        title?: string;
        type?: string | null;
        description?: string | null;
        assignees?: { id: number; name: string }[];
        co_assignees?: { id: number; name: string }[];
        start_date?: string | null;
        deadline?: string | null;
        priority?: Priority;
    };
}) {
    const { t } = useTranslation();
    const types = useOptions('task_type');
    // Switched-off types stay selectable for tasks that already use them.
    const typeChoices = types.items.filter(
        (option) => option.active || option.key === defaults.type,
    );

    return (
        <div className="grid gap-4">
            <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_14rem]">
                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}title`}>{t('Title')}</Label>
                    <Input
                        id={`${idPrefix}title`}
                        name="title"
                        defaultValue={defaults.title}
                        required
                        autoFocus={!defaults.title}
                    />
                    <InputError message={errors.title} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}type`}>{t('Task type')}</Label>
                    <Select name="type" defaultValue={defaults.type ?? NO_TYPE}>
                        <SelectTrigger id={`${idPrefix}type`}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NO_TYPE}>
                                {t('No type')}
                            </SelectItem>
                            {typeChoices.map((option) => (
                                <SelectItem key={option.key} value={option.key}>
                                    {t(option.label)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.type} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}description`}>
                    {t('Description')}
                </Label>
                <textarea
                    id={`${idPrefix}description`}
                    name="description"
                    rows={4}
                    defaultValue={defaults.description ?? ''}
                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={errors.description} />
            </div>

            <AssigneesField
                id={`${idPrefix}assignees`}
                members={members}
                defaultValue={defaults.assignees?.map((person) => person.id)}
                error={errors.assignees}
            />

            <AssigneesField
                id={`${idPrefix}co_assignees`}
                name="co_assignees"
                label="Co-responsible"
                members={members}
                defaultValue={defaults.co_assignees?.map((person) => person.id)}
                error={errors.co_assignees}
            />

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}start_date`}>
                        {t('Start date')}
                    </Label>
                    <Input
                        id={`${idPrefix}start_date`}
                        name="start_date"
                        type="date"
                        // A new task starts on the day it is created.
                        defaultValue={
                            defaults.title === undefined
                                ? localToday()
                                : (defaults.start_date ?? '')
                        }
                    />
                    <InputError message={errors.start_date} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}deadline`}>
                        {t('Deadline')}
                    </Label>
                    <Input
                        id={`${idPrefix}deadline`}
                        name="deadline"
                        type="datetime-local"
                        // Server time is ISO 8601; the input takes YYYY-MM-DDTHH:mm.
                        defaultValue={defaults.deadline?.slice(0, 16) ?? ''}
                    />
                    <InputError message={errors.deadline} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${idPrefix}priority`}>
                        {t('Priority')}
                    </Label>
                    <Select
                        name="priority"
                        defaultValue={defaults.priority ?? 'normal'}
                    >
                        <SelectTrigger id={`${idPrefix}priority`}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(priorityLabels).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {t(label)}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.priority} />
                </div>
            </div>
        </div>
    );
}
