import type { FormDataConvertible } from '@inertiajs/core';
import { useState } from 'react';
import TagInput from '@/components/core/tag-input';
import InputError from '@/components/input-error';
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
import type { Member } from '@/modules/tasks/types';
import { priorityLabels } from '@/modules/tasks/types';
import type { EventDetails } from './types';
import { statusLabels, typeLabels } from './types';

const NONE = 'none';

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/** Form data from EventFields: "none" responsible as null, checkbox as boolean, tags always sent. */
export function eventFormTransform(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    return {
        ...data,
        all_day: data.all_day === 'on' || data.all_day === true,
        responsible_user_id:
            data.responsible_user_id === NONE ? null : data.responsible_user_id,
        tags: data.tags ?? [],
    };
}

/** ISO date-time (Lisbon, with offset) to an input value, without time zone conversion. */
function inputValue(value: string | null | undefined, allDay: boolean) {
    if (!value) {
        return '';
    }

    return allDay ? value.slice(0, 10) : value.slice(0, 16);
}

export default function EventFields({
    members,
    errors,
    defaults = {},
    withStatus = false,
}: {
    members: Member[];
    errors: Partial<Record<string, string>>;
    defaults?: Partial<EventDetails>;
    withStatus?: boolean;
}) {
    const { t } = useTranslation();
    const [allDay, setAllDay] = useState(defaults.all_day ?? false);

    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor="title">{t('Title')}</Label>
                <Input
                    id="title"
                    name="title"
                    defaultValue={defaults.title}
                    required
                    autoFocus={!defaults.title}
                />
                <InputError message={errors.title} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="type">{t('Type')}</Label>
                    <Select
                        name="type"
                        defaultValue={defaults.type ?? 'institutional'}
                    >
                        <SelectTrigger id="type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(typeLabels).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {t(label)}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.type} />
                </div>
                <div className="flex items-end gap-2 pb-2">
                    <Checkbox
                        id="all_day"
                        name="all_day"
                        checked={allDay}
                        onCheckedChange={(checked) =>
                            setAllDay(checked === true)
                        }
                    />
                    <Label htmlFor="all_day">{t('All day')}</Label>
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="start_at">{t('Start')}</Label>
                    <Input
                        key={`start-${allDay}`}
                        id="start_at"
                        name="start_at"
                        type={allDay ? 'date' : 'datetime-local'}
                        defaultValue={inputValue(defaults.start_at, allDay)}
                        required
                    />
                    <InputError message={errors.start_at} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="end_at">{t('End')}</Label>
                    <Input
                        key={`end-${allDay}`}
                        id="end_at"
                        name="end_at"
                        type={allDay ? 'date' : 'datetime-local'}
                        defaultValue={inputValue(defaults.end_at, allDay)}
                    />
                    <InputError message={errors.end_at} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="location">{t('Location')}</Label>
                    <Input
                        id="location"
                        name="location"
                        defaultValue={defaults.location ?? ''}
                    />
                    <InputError message={errors.location} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="organizer">{t('Organizer')}</Label>
                    <Input
                        id="organizer"
                        name="organizer"
                        defaultValue={defaults.organizer ?? ''}
                    />
                    <InputError message={errors.organizer} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="responsible_user_id">
                        {t('Responsible')}
                    </Label>
                    <Select
                        name="responsible_user_id"
                        defaultValue={String(
                            defaults.responsible_user_id ?? NONE,
                        )}
                    >
                        <SelectTrigger id="responsible_user_id">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NONE}>{t('Nobody')}</SelectItem>
                            {members.map((member) => (
                                <SelectItem
                                    key={member.id}
                                    value={String(member.id)}
                                >
                                    {member.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.responsible_user_id} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="priority">{t('Priority')}</Label>
                    <Select
                        name="priority"
                        defaultValue={defaults.priority ?? 'normal'}
                    >
                        <SelectTrigger id="priority">
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
                </div>
                {withStatus && (
                    <div className="grid gap-2">
                        <Label htmlFor="status">{t('Status')}</Label>
                        <Select
                            name="status"
                            defaultValue={defaults.status ?? 'confirmed'}
                        >
                            <SelectTrigger id="status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(statusLabels).map(
                                    ([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {t(label)}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                    </div>
                )}
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">{t('Description')}</Label>
                <textarea
                    id="description"
                    name="description"
                    rows={3}
                    defaultValue={defaults.description ?? ''}
                    className={textareaClass}
                />
                <InputError message={errors.description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="notes">{t('Notes')}</Label>
                <textarea
                    id="notes"
                    name="notes"
                    rows={2}
                    defaultValue={defaults.notes ?? ''}
                    className={textareaClass}
                />
                <InputError message={errors.notes} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="tags">{t('Tags')}</Label>
                <TagInput defaultValue={defaults.tags} />
                <InputError message={errors.tags} />
            </div>
        </div>
    );
}
