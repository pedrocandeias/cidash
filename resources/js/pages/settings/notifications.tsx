import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslation } from '@/lib/i18n';
import { edit, update } from '@/routes/notification-preferences';

type Preferences = Record<string, boolean>;

const options: { key: string; label: string; description: string }[] = [
    {
        key: 'assignments',
        label: 'Tasks assigned to me',
        description: 'When someone assigns you a task.',
    },
    {
        key: 'reviews',
        label: 'Content awaiting review',
        description: 'For editors and managers, when content enters review.',
    },
    {
        key: 'reminders',
        label: 'My reminders',
        description: 'The reminders you set on any record.',
    },
    {
        key: 'alerts',
        label: 'Alerts',
        description:
            'For managers and the people responsible, when an alert opens.',
    },
    {
        key: 'daily_briefing',
        label: 'Daily briefing',
        description: 'At 07:00 on working days.',
    },
    {
        key: 'weekly_briefing',
        label: 'Weekly briefing',
        description: 'On Mondays at 07:00.',
    },
];

export default function NotificationPreferences({
    preferences,
    emailConfigured,
}: {
    preferences: Preferences;
    emailConfigured: boolean;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Notifications')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Email notifications')}
                    description={t(
                        'Everything is always in the app (the bell). Choose what you also receive by email.',
                    )}
                />

                {!emailConfigured && (
                    <p className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm dark:border-amber-900 dark:bg-amber-950/40">
                        {t(
                            'Email is not configured yet, so nothing is sent for now. Your choices are kept.',
                        )}
                    </p>
                )}

                <ul className="space-y-4">
                    {options.map((option) => (
                        <li key={option.key}>
                            <label className="flex items-start gap-3">
                                <Checkbox
                                    className="mt-0.5"
                                    checked={preferences[option.key]}
                                    onCheckedChange={(checked) =>
                                        router.patch(
                                            update().url,
                                            { [option.key]: checked === true },
                                            { preserveScroll: true },
                                        )
                                    }
                                />
                                <span className="grid gap-0.5">
                                    <span className="text-sm font-medium">
                                        {t(option.label)}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {t(option.description)}
                                    </span>
                                </span>
                            </label>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

NotificationPreferences.layout = {
    breadcrumbs: [{ title: 'Notifications', href: edit() }],
};
