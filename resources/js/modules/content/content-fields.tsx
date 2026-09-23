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
import type { Member } from '@/modules/tasks/types';
import type { ContentDetails } from './types';
import { channelLabels, formatLabels } from './types';

const NONE = 'none';

export function contentFormTransform(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    return {
        ...data,
        owner_id: data.owner_id === NONE ? null : data.owner_id,
        channels: data.channels ?? [],
    };
}

export default function ContentFields({
    members,
    errors,
    defaults,
}: {
    members: Member[];
    errors: Partial<Record<string, string>>;
    defaults?: ContentDetails;
}) {
    const { t } = useTranslation();

    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor="title">{t('Title')}</Label>
                <Input
                    id="title"
                    name="title"
                    defaultValue={defaults?.title}
                    required
                    autoFocus={!defaults}
                />
                <InputError message={errors.title} />
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="format">{t('Format')}</Label>
                    <Select
                        name="format"
                        defaultValue={defaults?.format ?? 'news'}
                    >
                        <SelectTrigger id="format">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(formatLabels).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {t(label)}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="owner_id">{t('Owner')}</Label>
                    <Select
                        name="owner_id"
                        defaultValue={String(defaults?.owner_id ?? NONE)}
                    >
                        <SelectTrigger id="owner_id">
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
                    <InputError message={errors.owner_id} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="due_at">{t('Deadline')}</Label>
                    <Input
                        id="due_at"
                        name="due_at"
                        type="date"
                        defaultValue={defaults?.due_at ?? ''}
                    />
                    <InputError message={errors.due_at} />
                </div>
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
                <InputError message={errors.channels} />
            </fieldset>

            <div className="grid gap-2">
                <Label htmlFor="brief">{t('Brief')}</Label>
                <textarea
                    id="brief"
                    name="brief"
                    rows={5}
                    defaultValue={defaults?.brief ?? ''}
                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={errors.brief} />
            </div>

            {defaults && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="publish_at">
                            {t('Publication date')}
                        </Label>
                        <Input
                            id="publish_at"
                            name="publish_at"
                            type="datetime-local"
                            defaultValue={defaults.publish_at?.slice(0, 16)}
                        />
                        <InputError message={errors.publish_at} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="published_url">
                            {t('Published URL')}
                        </Label>
                        <Input
                            id="published_url"
                            name="published_url"
                            type="url"
                            defaultValue={defaults.published_url ?? ''}
                            placeholder="https://"
                        />
                        <InputError message={errors.published_url} />
                    </div>
                </div>
            )}
        </div>
    );
}
