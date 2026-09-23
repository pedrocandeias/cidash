import { formatDateTime, useTranslation } from '@/lib/i18n';

export type ActivityItem = {
    id: number;
    action: string;
    fields: string[];
    user: string | null;
    created_at: string;
};

const actions: Record<string, string> = {
    created: 'created',
    updated: 'updated',
    deleted: 'deleted',
};

/**
 * History of a record, from activity_log. `fieldLabels` names the changed fields.
 */
export default function ActivityFeed({
    activity,
    fieldLabels = {},
}: {
    activity: ActivityItem[];
    fieldLabels?: Record<string, string>;
}) {
    const { t, locale } = useTranslation();

    return (
        <div className="space-y-4">
            <h2 className="text-base font-medium">{t('History')}</h2>
            <ul className="space-y-2 text-sm">
                {activity.map((item) => (
                    <li key={item.id} className="text-muted-foreground">
                        <span className="font-medium text-foreground">
                            {item.user ?? t('System')}
                        </span>{' '}
                        {t(actions[item.action] ?? item.action)}
                        {item.fields.length > 0 && (
                            <>
                                {': '}
                                {item.fields
                                    .map((field) =>
                                        t(fieldLabels[field] ?? field),
                                    )
                                    .join(', ')}
                            </>
                        )}
                        <span className="ml-2 text-xs">
                            {formatDateTime(item.created_at, locale)}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
