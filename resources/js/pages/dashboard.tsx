import { Head, Link, usePage } from '@inertiajs/react';
import { Pin, TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import type { RecordSummary } from '@/components/core/relations-panel';
import AlertLine from '@/modules/alerts/alert-line';
import type { AlertItem } from '@/modules/alerts/types';
import { index as alertsIndex } from '@/routes/alerts';
import {
    formatDate,
    formatDateTime,
    localToday,
    useTranslation,
} from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { stageLabels } from '@/modules/content/types';
import type { Stage } from '@/modules/content/types';
import type { EventType } from '@/modules/events/types';
import { typeColors } from '@/modules/events/types';
import PriorityBadge from '@/modules/tasks/priority-badge';
import type { Priority } from '@/modules/tasks/types';
import { dashboard } from '@/routes';
import { index as contentIndex, show as showContent } from '@/routes/content';
import { index as eventsIndex, show as showEvent } from '@/routes/events';
import { index as noticesIndex, show as showNotice } from '@/routes/notices';
import { index as pressIndex, show as showPress } from '@/routes/press';
import { today as briefingToday } from '@/routes/briefings';
import { index as mentionsIndex, show as showMention } from '@/routes/mentions';
import { index as newsIndex, show as showNews } from '@/routes/news';
import { index as tasksIndex, show as showTask } from '@/routes/tasks';

type Props = {
    alerts: { count: number; items: AlertItem[] };
    counters: {
        events_today: number;
        my_tasks: number;
        press_48h: number;
        in_review: number;
        new_mentions: number;
    };
    news: {
        id: string;
        headline: string;
        outlet: string | null;
        story_count: number;
    }[];
    mentions: {
        by_rule: { name: string; count: number }[];
        latest: { id: string; headline: string; outlet: string | null }[];
    };
    events: {
        id: string;
        title: string;
        start_at: string;
        all_day: boolean;
        type: EventType;
    }[];
    tasks: {
        id: string;
        title: string;
        deadline: string | null;
        priority: Priority;
    }[];
    press: {
        id: string;
        subject: string;
        media_outlet: string | null;
        deadline: string | null;
        responsible: string | null;
    }[];
    content: {
        stages: { stage: Stage; count: number }[];
        stuck: { id: string; title: string; since: string }[];
    };
    notices: { id: string; title: string; pinned: boolean }[];
    reminders: { id: number; remind_at: string; record: RecordSummary }[];
    approvals: { id: string; title: string }[] | null;
    overdue: { name: string; count: number }[] | null;
};

function Widget({
    title,
    href,
    children,
}: {
    title: string;
    href?: string;
    children: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <section className="space-y-3 rounded-xl border p-4">
            <header className="flex items-center justify-between">
                <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                    {t(title)}
                </h2>
                {href && (
                    <Link
                        href={href}
                        className="text-xs text-muted-foreground hover:text-foreground"
                    >
                        {t('See all')}
                    </Link>
                )}
            </header>
            {children}
        </section>
    );
}

function Empty({ children }: { children: ReactNode }) {
    return <p className="text-sm text-muted-foreground">{children}</p>;
}

function greeting(): string {
    const hour = new Date().getHours();

    return hour < 12
        ? 'Good morning'
        : hour < 20
          ? 'Good afternoon'
          : 'Good evening';
}

export default function Dashboard({
    alerts,
    counters,
    news,
    mentions,
    events,
    tasks,
    press,
    content,
    notices,
    reminders,
    approvals,
    overdue,
}: Props) {
    const { t, locale } = useTranslation();
    const { auth } = usePage().props;
    const today = localToday();
    const dayLabel = new Intl.DateTimeFormat(locale.replace('_', '-'), {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }).format(new Date());

    const counterItems = [
        {
            label: 'Events today',
            value: counters.events_today,
            href: eventsIndex().url,
        },
        {
            label: 'My open tasks',
            value: counters.my_tasks,
            href: tasksIndex().url,
        },
        {
            label: 'Press within 48 h',
            value: counters.press_48h,
            href: pressIndex().url,
            alert: counters.press_48h > 0,
        },
        {
            label: 'New mentions',
            value: counters.new_mentions,
            href: mentionsIndex().url,
        },
        {
            label: 'In review',
            value: counters.in_review,
            href: contentIndex().url,
        },
    ];

    return (
        <>
            <Head title={t('Home')} />

            <div className="space-y-6 px-4 py-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t(greeting())}, {auth.user.name.split(' ')[0]}
                        </h1>
                        <p className="text-sm text-muted-foreground first-letter:uppercase">
                            {dayLabel}
                        </p>
                    </div>
                    <Link
                        href={briefingToday()}
                        className="text-sm font-medium hover:underline"
                    >
                        {t("See today's briefing")} →
                    </Link>
                </header>

                {alerts.count > 0 && (
                    <section
                        aria-label={t('Alerts')}
                        className="flex flex-wrap items-start gap-x-4 gap-y-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 dark:border-amber-900 dark:bg-amber-950/40"
                    >
                        <Link
                            href={alertsIndex()}
                            className="flex items-center gap-2 text-sm font-semibold hover:underline"
                        >
                            <TriangleAlert className="size-4 text-amber-600" />
                            {t('Alerts (:count)', { count: alerts.count })}
                        </Link>
                        <ul className="min-w-0 flex-1 space-y-1">
                            {alerts.items.map((alert) => (
                                <li key={alert.id}>
                                    <AlertLine alert={alert} />
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    {counterItems.map((item) => (
                        <Link
                            key={item.label}
                            href={item.href}
                            className="rounded-xl border p-4 hover:bg-muted/50"
                        >
                            <span
                                className={cn(
                                    'block text-3xl font-semibold',
                                    item.alert && 'text-red-600',
                                )}
                            >
                                {item.value}
                            </span>
                            <span className="text-sm text-muted-foreground">
                                {t(item.label)}
                            </span>
                        </Link>
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <div className="space-y-6">
                        <Widget
                            title="Today and next days"
                            href={eventsIndex().url}
                        >
                            {events.length === 0 ? (
                                <Empty>
                                    {t('No events in the next 7 days.')}
                                </Empty>
                            ) : (
                                <ul className="space-y-2">
                                    {events.map((event) => (
                                        <li
                                            key={event.id}
                                            className="flex items-center gap-3 text-sm"
                                        >
                                            <span
                                                className="size-2 shrink-0 rounded-full"
                                                style={{
                                                    backgroundColor:
                                                        typeColors[event.type],
                                                }}
                                            />
                                            <span className="w-32 shrink-0 text-muted-foreground">
                                                {event.all_day
                                                    ? formatDate(
                                                          event.start_at,
                                                          locale,
                                                      )
                                                    : formatDateTime(
                                                          event.start_at,
                                                          locale,
                                                      )}
                                            </span>
                                            <Link
                                                href={showEvent(event.id)}
                                                className="truncate hover:underline"
                                            >
                                                {event.title}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Widget>

                        <Widget title="My tasks" href={tasksIndex().url}>
                            {tasks.length === 0 ? (
                                <Empty>
                                    {t('No open tasks assigned to you.')}
                                </Empty>
                            ) : (
                                <ul className="space-y-2">
                                    {tasks.map((task) => (
                                        <li
                                            key={task.id}
                                            className="flex items-center gap-3 text-sm"
                                        >
                                            <Link
                                                href={showTask(task.id)}
                                                className="min-w-0 flex-1 truncate hover:underline"
                                            >
                                                {task.title}
                                            </Link>
                                            <PriorityBadge
                                                priority={task.priority}
                                            />
                                            {task.deadline && (
                                                <span
                                                    className={cn(
                                                        'text-xs',
                                                        task.deadline < today
                                                            ? 'font-medium text-red-600'
                                                            : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {formatDate(
                                                        task.deadline,
                                                        locale,
                                                    )}
                                                </span>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Widget>

                        <Widget title="Press requests" href={pressIndex().url}>
                            {press.length === 0 ? (
                                <Empty>{t('No open press requests.')}</Empty>
                            ) : (
                                <ul className="space-y-2">
                                    {press.map((request) => {
                                        const urgent =
                                            request.deadline !== null &&
                                            new Date(
                                                request.deadline,
                                            ).getTime() -
                                                Date.now() <
                                                24 * 36e5;

                                        return (
                                            <li
                                                key={request.id}
                                                className="flex items-center gap-3 text-sm"
                                            >
                                                <span
                                                    className={cn(
                                                        'size-2 shrink-0 rounded-full',
                                                        urgent
                                                            ? 'bg-red-600'
                                                            : 'bg-transparent',
                                                    )}
                                                />
                                                <Link
                                                    href={showPress(request.id)}
                                                    className="min-w-0 flex-1 truncate hover:underline"
                                                >
                                                    {request.media_outlet && (
                                                        <span className="text-muted-foreground">
                                                            {
                                                                request.media_outlet
                                                            }{' '}
                                                            ·{' '}
                                                        </span>
                                                    )}
                                                    {request.subject}
                                                </Link>
                                                {request.deadline && (
                                                    <span
                                                        className={cn(
                                                            'text-xs',
                                                            urgent
                                                                ? 'font-medium text-red-600'
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {formatDateTime(
                                                            request.deadline,
                                                            locale,
                                                        )}
                                                    </span>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </Widget>

                        <Widget
                            title="Content in preparation"
                            href={contentIndex().url}
                        >
                            <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                {content.stages.map((item) => (
                                    <span key={item.stage}>
                                        {t(stageLabels[item.stage])}{' '}
                                        <strong>{item.count}</strong>
                                    </span>
                                ))}
                            </div>
                            {content.stuck.length > 0 && (
                                <ul className="space-y-1 text-sm">
                                    {content.stuck.map((item) => (
                                        <li
                                            key={item.id}
                                            className="text-amber-700 dark:text-amber-400"
                                        >
                                            <Link
                                                href={showContent(item.id)}
                                                className="hover:underline"
                                            >
                                                {item.title}
                                            </Link>{' '}
                                            <span className="text-xs">
                                                (
                                                {t('in review since :date', {
                                                    date: formatDate(
                                                        item.since,
                                                        locale,
                                                    ),
                                                })}
                                                )
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Widget>
                    </div>

                    <div className="space-y-6">
                        {approvals && (
                            <Widget
                                title="Awaiting approval"
                                href={contentIndex().url}
                            >
                                {approvals.length === 0 ? (
                                    <Empty>{t('Nothing to approve.')}</Empty>
                                ) : (
                                    <ul className="space-y-1 text-sm">
                                        {approvals.map((item) => (
                                            <li key={item.id}>
                                                <Link
                                                    href={showContent(item.id)}
                                                    className="hover:underline"
                                                >
                                                    {item.title}
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Widget>
                        )}

                        {overdue && overdue.length > 0 && (
                            <Widget
                                title="Overdue tasks by person"
                                href={
                                    tasksIndex({ query: { view: 'team' } }).url
                                }
                            >
                                <ul className="space-y-1 text-sm">
                                    {overdue.map((row) => (
                                        <li
                                            key={row.name}
                                            className="flex justify-between"
                                        >
                                            <span>{row.name}</span>
                                            <span className="font-medium text-red-600">
                                                {row.count}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </Widget>
                        )}

                        <Widget title="Latest news" href={newsIndex().url}>
                            {news.length === 0 ? (
                                <Empty>
                                    {t('No news in the last two days.')}
                                </Empty>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {news.map((line) => (
                                        <li key={line.id}>
                                            <span className="block text-xs text-muted-foreground">
                                                {line.outlet}
                                                {line.story_count > 1 &&
                                                    ` · ${line.story_count}×`}
                                            </span>
                                            <Link
                                                href={showNews(line.id)}
                                                className="hover:underline"
                                            >
                                                {line.headline}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Widget>

                        <Widget title="New mentions" href={mentionsIndex().url}>
                            {counters.new_mentions === 0 ? (
                                <Empty>{t('Nothing left to triage.')}</Empty>
                            ) : (
                                <div className="space-y-3 text-sm">
                                    <p className="text-muted-foreground">
                                        {t(':count to review', {
                                            count: counters.new_mentions,
                                        })}
                                        {': '}
                                        {mentions.by_rule
                                            .map(
                                                (rule) =>
                                                    `${rule.name} ${rule.count}`,
                                            )
                                            .join(' · ')}
                                    </p>
                                    <ul className="space-y-2">
                                        {mentions.latest.map((mention) => (
                                            <li key={mention.id}>
                                                <span className="block text-xs text-muted-foreground">
                                                    {mention.outlet}
                                                </span>
                                                <Link
                                                    href={showMention(
                                                        mention.id,
                                                    )}
                                                    className="hover:underline"
                                                >
                                                    {mention.headline}
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </Widget>

                        <Widget title="Notices" href={noticesIndex().url}>
                            {notices.length === 0 ? (
                                <Empty>{t('No notices.')}</Empty>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {notices.map((notice) => (
                                        <li
                                            key={notice.id}
                                            className="flex items-start gap-2"
                                        >
                                            {notice.pinned ? (
                                                <Pin className="mt-0.5 size-3.5 shrink-0" />
                                            ) : (
                                                <span className="w-3.5 shrink-0" />
                                            )}
                                            <Link
                                                href={showNotice(notice.id)}
                                                className="hover:underline"
                                            >
                                                {notice.title}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Widget>

                        <Widget title="My reminders">
                            {reminders.length === 0 ? (
                                <Empty>
                                    {t('No reminders in the next 7 days.')}
                                </Empty>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {reminders.map((reminder) => (
                                        <li key={reminder.id}>
                                            <span className="block text-xs text-muted-foreground">
                                                {formatDateTime(
                                                    reminder.remind_at,
                                                    locale,
                                                )}
                                            </span>
                                            {reminder.record.url ? (
                                                <Link
                                                    href={reminder.record.url}
                                                    className="hover:underline"
                                                >
                                                    {reminder.record.title}
                                                </Link>
                                            ) : (
                                                reminder.record.title
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Widget>
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Home', href: dashboard() }],
};
