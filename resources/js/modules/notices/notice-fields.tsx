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
import { priorityLabels } from '@/modules/tasks/types';

export type Notice = {
    id: string;
    title: string;
    body: string;
    published_at: string;
    expires_at: string | null;
    priority: 'low' | 'normal' | 'high' | 'urgent';
    pinned: boolean;
    active: boolean;
    author: string | null;
};

export function noticeFormTransform(
    data: Record<string, FormDataConvertible>,
): Record<string, FormDataConvertible> {
    return { ...data, pinned: data.pinned === 'on' || data.pinned === true };
}

export default function NoticeFields({
    errors,
    defaults,
    canPin,
}: {
    errors: Partial<Record<string, string>>;
    defaults?: Notice;
    canPin: boolean;
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

            <div className="grid gap-2">
                <Label htmlFor="body">{t('Text')}</Label>
                <textarea
                    id="body"
                    name="body"
                    rows={6}
                    defaultValue={defaults?.body}
                    required
                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={errors.body} />
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="published_at">{t('Publish on')}</Label>
                    <Input
                        id="published_at"
                        name="published_at"
                        type="datetime-local"
                        defaultValue={defaults?.published_at.slice(0, 16)}
                    />
                    <InputError message={errors.published_at} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="expires_at">{t('Expires on')}</Label>
                    <Input
                        id="expires_at"
                        name="expires_at"
                        type="datetime-local"
                        defaultValue={defaults?.expires_at?.slice(0, 16)}
                    />
                    <InputError message={errors.expires_at} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="priority">{t('Priority')}</Label>
                    <Select
                        name="priority"
                        defaultValue={defaults?.priority ?? 'normal'}
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
            </div>

            <p className="-mt-2 text-xs text-muted-foreground">
                {t(
                    'Leave "Publish on" empty to publish now, and "Expires on" empty to keep it until removed.',
                )}
            </p>

            {canPin && (
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="pinned"
                        name="pinned"
                        defaultChecked={defaults?.pinned}
                    />
                    <Label htmlFor="pinned">{t('Pinned')}</Label>
                </div>
            )}
        </div>
    );
}
