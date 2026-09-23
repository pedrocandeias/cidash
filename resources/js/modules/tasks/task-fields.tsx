import type { FormDataConvertible } from '@inertiajs/core';
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
import { useTranslation } from '@/lib/i18n';
import type { Member, Priority } from './types';
import { priorityLabels } from './types';

const UNASSIGNED = 'none';

/** Form data from TaskFields, with "unassigned" sent as null. */
export function taskFormTransform(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    return {
        ...data,
        assigned_to: data.assigned_to === UNASSIGNED ? null : data.assigned_to,
    };
}

export default function TaskFields({
    members,
    errors,
    defaults = {},
}: {
    members: Member[];
    errors: Partial<Record<string, string>>;
    defaults?: {
        title?: string;
        description?: string | null;
        assigned_to?: number | null;
        deadline?: string | null;
        priority?: Priority;
    };
}) {
    const { t } = useTranslation();

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

            <div className="grid gap-2">
                <Label htmlFor="description">{t('Description')}</Label>
                <textarea
                    id="description"
                    name="description"
                    rows={4}
                    defaultValue={defaults.description ?? ''}
                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={errors.description} />
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="assigned_to">{t('Assignee')}</Label>
                    <Select
                        name="assigned_to"
                        defaultValue={String(
                            defaults.assigned_to ?? UNASSIGNED,
                        )}
                    >
                        <SelectTrigger id="assigned_to">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={UNASSIGNED}>
                                {t('Unassigned')}
                            </SelectItem>
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
                    <InputError message={errors.assigned_to} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="deadline">{t('Deadline')}</Label>
                    <Input
                        id="deadline"
                        name="deadline"
                        type="date"
                        defaultValue={defaults.deadline ?? ''}
                    />
                    <InputError message={errors.deadline} />
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
                    <InputError message={errors.priority} />
                </div>
            </div>
        </div>
    );
}
