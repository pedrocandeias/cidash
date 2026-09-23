import type { FormDataConvertible } from '@inertiajs/core';
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
import { channelLabels } from '@/modules/content/types';
import type { Member } from '@/modules/tasks/types';
import type { CampaignDetails } from './types';
import { statusLabels } from './types';

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

export function campaignFormTransform(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    return {
        ...data,
        channels: data.channels ?? [],
        responsibles: data.responsibles ?? [],
    };
}

export default function CampaignFields({
    members,
    errors,
    defaults,
}: {
    members: Member[];
    errors: Partial<Record<string, string>>;
    defaults?: CampaignDetails;
}) {
    const { t } = useTranslation();
    const responsibleIds = defaults?.responsibles.map((user) => user.id) ?? [];

    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor="name">{t('Name')}</Label>
                <Input
                    id="name"
                    name="name"
                    defaultValue={defaults?.name}
                    required
                    autoFocus={!defaults}
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="start_date">{t('Start')}</Label>
                    <Input
                        id="start_date"
                        name="start_date"
                        type="date"
                        defaultValue={defaults?.start_date ?? ''}
                    />
                    <InputError message={errors.start_date} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="end_date">{t('End')}</Label>
                    <Input
                        id="end_date"
                        name="end_date"
                        type="date"
                        defaultValue={defaults?.end_date ?? ''}
                    />
                    <InputError message={errors.end_date} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="status">{t('Status')}</Label>
                    <Select
                        name="status"
                        defaultValue={defaults?.status ?? 'planning'}
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
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">{t('Description')}</Label>
                <textarea
                    id="description"
                    name="description"
                    rows={3}
                    defaultValue={defaults?.description ?? ''}
                    className={textareaClass}
                />
                <InputError message={errors.description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="objectives">{t('Objectives')}</Label>
                <textarea
                    id="objectives"
                    name="objectives"
                    rows={3}
                    defaultValue={defaults?.objectives ?? ''}
                    className={textareaClass}
                />
                <InputError message={errors.objectives} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="audiences">{t('Audiences')}</Label>
                <Input
                    id="audiences"
                    name="audiences"
                    defaultValue={defaults?.audiences.join(', ')}
                    placeholder={t(
                        'Separated by commas, e.g. future students, alumni',
                    )}
                />
                <InputError message={errors.audiences} />
            </div>

            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm font-medium">
                    {t('Channels')}
                </legend>
                <div className="flex flex-wrap gap-x-4 gap-y-2">
                    {Object.entries(channelLabels).map(([value, label]) => (
                        <label
                            key={value}
                            className="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                name="channels[]"
                                value={value}
                                defaultChecked={defaults?.channels.includes(
                                    value,
                                )}
                            />
                            {t(label)}
                        </label>
                    ))}
                </div>
            </fieldset>

            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm font-medium">
                    {t('Responsible')}
                </legend>
                <div className="flex flex-wrap gap-x-4 gap-y-2">
                    {members.map((member) => (
                        <label
                            key={member.id}
                            className="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                name="responsibles[]"
                                value={String(member.id)}
                                defaultChecked={responsibleIds.includes(
                                    member.id,
                                )}
                            />
                            {member.name}
                        </label>
                    ))}
                </div>
                <InputError message={errors.responsibles} />
            </fieldset>
        </div>
    );
}
