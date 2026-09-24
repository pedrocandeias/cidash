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
import { useTranslation } from '@/lib/i18n';
import type { Member } from '@/modules/tasks/types';
import type { Known, PressDetails } from './types';
import { statusLabels } from './types';

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

export function pressFormTransform(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    return {
        ...data,
        assignees: data.assignees ?? [],
    };
}

export default function PressFields({
    members,
    known,
    errors,
    defaults,
}: {
    members: Member[];
    known: Known;
    errors: Partial<Record<string, string>>;
    defaults?: PressDetails;
}) {
    const { t } = useTranslation();

    return (
        <div className="grid gap-4">
            <datalist id="known-journalists">
                {known.journalists.map((name) => (
                    <option key={name} value={name} />
                ))}
            </datalist>
            <datalist id="known-outlets">
                {known.outlets.map((name) => (
                    <option key={name} value={name} />
                ))}
            </datalist>

            <div className="grid gap-2">
                <Label htmlFor="subject">{t('Subject')}</Label>
                <Input
                    id="subject"
                    name="subject"
                    defaultValue={defaults?.subject}
                    required
                    autoFocus={!defaults}
                />
                <InputError message={errors.subject} />
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="journalist">{t('Journalist')}</Label>
                    <Input
                        id="journalist"
                        name="journalist"
                        list="known-journalists"
                        defaultValue={defaults?.journalist ?? ''}
                    />
                    <InputError message={errors.journalist} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="media_outlet">{t('Media outlet')}</Label>
                    <Input
                        id="media_outlet"
                        name="media_outlet"
                        list="known-outlets"
                        defaultValue={defaults?.media_outlet ?? ''}
                    />
                    <InputError message={errors.media_outlet} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="contact">{t('Contact')}</Label>
                    <Input
                        id="contact"
                        name="contact"
                        defaultValue={defaults?.contact ?? ''}
                        placeholder={t('Email or phone')}
                    />
                    <InputError message={errors.contact} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="request">{t('Request')}</Label>
                <textarea
                    id="request"
                    name="request"
                    rows={4}
                    defaultValue={defaults?.request ?? ''}
                    className={textareaClass}
                />
                <InputError message={errors.request} />
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="received_at">{t('Received')}</Label>
                    <Input
                        id="received_at"
                        name="received_at"
                        type="datetime-local"
                        defaultValue={defaults?.received_at.slice(0, 16)}
                    />
                    <InputError message={errors.received_at} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="deadline">{t('Deadline')}</Label>
                    <Input
                        id="deadline"
                        name="deadline"
                        type="datetime-local"
                        defaultValue={defaults?.deadline?.slice(0, 16)}
                    />
                    <InputError message={errors.deadline} />
                </div>
                <AssigneesField
                    members={members}
                    defaultValue={defaults?.assignees.map(
                        (person) => person.id,
                    )}
                    error={errors.assignees}
                />
            </div>

            {defaults && (
                <>
                    <div className="grid gap-2 sm:w-1/3">
                        <Label htmlFor="status">{t('Status')}</Label>
                        <Select name="status" defaultValue={defaults.status}>
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
                    <div className="grid gap-2">
                        <Label htmlFor="response_notes">
                            {t('Response notes')}
                        </Label>
                        <textarea
                            id="response_notes"
                            name="response_notes"
                            rows={4}
                            defaultValue={defaults.response_notes ?? ''}
                            className={textareaClass}
                        />
                        <InputError message={errors.response_notes} />
                    </div>
                </>
            )}
        </div>
    );
}
