import { router, usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime, useTranslation } from '@/lib/i18n';
import { destroy, store } from '@/routes/reminders';

export type ReminderItem = { id: number; remind_at: string };

const offsets = [
    { minutes: 0, label: 'At the start' },
    { minutes: 15, label: '15 minutes before' },
    { minutes: 60, label: '1 hour before' },
    { minutes: 60 * 24, label: '1 day before' },
    { minutes: 60 * 24 * 7, label: '1 week before' },
];

/**
 * The current user's reminders on a record, relative to `anchor` (e.g. an event's start).
 */
export default function RemindersPanel({
    recordId,
    anchor,
    reminders,
}: {
    recordId: string;
    anchor: string;
    reminders: ReminderItem[];
}) {
    const { t, locale } = useTranslation();
    const { errors } = usePage().props;
    const [offset, setOffset] = useState('60');

    const add = () => {
        const remindAt = new Date(
            new Date(anchor).getTime() - Number(offset) * 60 * 1000,
        );

        router.post(
            store(recordId).url,
            { remind_at: remindAt.toISOString() },
            { preserveScroll: true },
        );
    };

    return (
        <div className="space-y-4">
            <h2 className="text-base font-medium">{t('My reminders')}</h2>

            <ul className="space-y-1 text-sm">
                {reminders.map((reminder) => (
                    <li key={reminder.id} className="flex items-center gap-2">
                        <span className="flex-1">
                            {formatDateTime(reminder.remind_at, locale)}
                        </span>
                        <button
                            type="button"
                            className="text-muted-foreground hover:text-foreground"
                            aria-label={t('Remove reminder')}
                            onClick={() =>
                                router.delete(destroy(reminder.id).url, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <X className="size-4" />
                        </button>
                    </li>
                ))}
            </ul>

            <div className="flex gap-2">
                <Select value={offset} onValueChange={setOffset}>
                    <SelectTrigger className="flex-1" aria-label={t('When')}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {offsets.map((option) => (
                            <SelectItem
                                key={option.minutes}
                                value={String(option.minutes)}
                            >
                                {t(option.label)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button type="button" variant="outline" onClick={add}>
                    {t('Remind me')}
                </Button>
            </div>
            <InputError message={errors?.remind_at} />
        </div>
    );
}
