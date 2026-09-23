import { Pin } from 'lucide-react';
import { formatDateTime, useTranslation } from '@/lib/i18n';
import PriorityBadge from '@/modules/tasks/priority-badge';
import type { Notice } from './notice-fields';

export default function NoticeMeta({ notice }: { notice: Notice }) {
    const { t, locale } = useTranslation();
    const scheduled = new Date(notice.published_at) > new Date();

    return (
        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            {notice.pinned && (
                <span className="flex items-center gap-1 font-medium text-foreground">
                    <Pin className="size-3" />
                    {t('Pinned')}
                </span>
            )}
            <PriorityBadge priority={notice.priority} />
            <span>
                {notice.author ?? t('Deleted user')} ·{' '}
                {scheduled
                    ? t('Scheduled for :date', {
                          date: formatDateTime(notice.published_at, locale),
                      })
                    : formatDateTime(notice.published_at, locale)}
            </span>
            {notice.expires_at && (
                <span>
                    ·{' '}
                    {t('Expires :date', {
                        date: formatDateTime(notice.expires_at, locale),
                    })}
                </span>
            )}
        </div>
    );
}
