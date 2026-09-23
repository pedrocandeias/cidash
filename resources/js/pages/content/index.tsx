import { Form, Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import Kanban from '@/components/core/kanban';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import ptLocale from '@fullcalendar/core/locales/pt';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';
import FullCalendar from '@fullcalendar/react';
import {
    formatDate,
    formatDateTime,
    localToday,
    useTranslation,
} from '@/lib/i18n';
import ContentFields, {
    contentFormTransform,
} from '@/modules/content/content-fields';
import type { ContentSummary, Stage } from '@/modules/content/types';
import {
    daysInStage,
    formatLabels,
    isApproval,
    stageLabels,
    stages,
} from '@/modules/content/types';
import type { Member } from '@/modules/tasks/types';
import { index, show, store, update } from '@/routes/content';

type Props = {
    items: ContentSummary[];
    showArchived: boolean;
    layout: 'board' | 'list' | 'calendar';
    members: Member[];
    can: { approve: boolean };
};

function CardContent({ item }: { item: ContentSummary }) {
    const { t, locale } = useTranslation();
    const stuck = item.stage === 'review' && daysInStage(item) >= 3;
    const overdue =
        item.due_at !== null &&
        item.due_at < localToday() &&
        !['published', 'archived'].includes(item.stage);

    return (
        <>
            <Link
                href={show(item.id)}
                className="block text-sm font-medium hover:underline"
            >
                {item.title}
            </Link>
            <p className="text-xs text-muted-foreground">
                {t(formatLabels[item.format] ?? item.format)}
                {item.owner && ` · ${item.owner.name}`}
            </p>
            <div className="flex flex-wrap gap-2 text-xs">
                {item.due_at && (
                    <span
                        className={
                            overdue
                                ? 'font-medium text-red-600'
                                : 'text-muted-foreground'
                        }
                    >
                        {formatDate(item.due_at, locale)}
                    </span>
                )}
                {stuck && (
                    <span className="font-medium text-amber-600">
                        {t(':days days in review', { days: daysInStage(item) })}
                    </span>
                )}
            </div>
        </>
    );
}

export default function ContentBoard({
    items: initialItems,
    showArchived,
    layout,
    members,
    can,
}: Props) {
    const { t, locale } = useTranslation();
    const [items, setItems] = useState(initialItems);
    const [creating, setCreating] = useState(false);

    useEffect(() => setItems(initialItems), [initialItems]);

    const onMove = (item: ContentSummary, to: Stage) => {
        if (isApproval(item.stage, to) && !can.approve) {
            toast.error(t('Only editors and managers can approve content.'));

            return;
        }

        const previous = items;
        setItems(
            items.map((candidate) =>
                candidate.id === item.id
                    ? {
                          ...candidate,
                          stage: to,
                          stage_changed_at: new Date().toISOString(),
                      }
                    : candidate,
            ),
        );

        router.patch(
            update(item.id).url,
            { stage: to },
            {
                preserveScroll: true,
                onError: (errors) => {
                    setItems(previous);
                    toast.error(errors.stage ?? t('Something went wrong.'));
                },
            },
        );
    };

    const visibleStages = stages.filter(
        (stage) => showArchived || stage !== 'archived',
    );

    return (
        <>
            <Head title={t('Content')} />

            <div className="space-y-4 px-4 py-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title={t('Content')} />
                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="show-archived"
                                checked={showArchived}
                                onCheckedChange={(checked) =>
                                    router.get(
                                        index().url,
                                        checked ? { archived: 1 } : {},
                                        { preserveScroll: true },
                                    )
                                }
                            />
                            <Label htmlFor="show-archived">
                                {t('Show archived')}
                            </Label>
                        </div>
                        <Dialog open={creating} onOpenChange={setCreating}>
                            <DialogTrigger asChild>
                                <Button>{t('New content')}</Button>
                            </DialogTrigger>
                            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                                <DialogTitle>{t('New content')}</DialogTitle>
                                <Form
                                    {...store.form()}
                                    transform={contentFormTransform}
                                    onSuccess={() => setCreating(false)}
                                    className="space-y-6"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <ContentFields
                                                members={members}
                                                errors={errors}
                                            />
                                            <Button disabled={processing}>
                                                {t('Create content')}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                <nav className="flex gap-1" aria-label={t('Layout')}>
                    {(['board', 'list', 'calendar'] as const).map((option) => (
                        <Link
                            key={option}
                            href={index({
                                query: {
                                    ...(option === 'board'
                                        ? {}
                                        : { layout: option }),
                                    ...(showArchived ? { archived: 1 } : {}),
                                },
                            })}
                            className={
                                layout === option
                                    ? 'rounded-md bg-muted px-3 py-1.5 text-sm font-medium'
                                    : 'rounded-md px-3 py-1.5 text-sm text-muted-foreground hover:text-foreground'
                            }
                        >
                            {t(
                                {
                                    board: 'Board',
                                    list: 'List',
                                    calendar: 'Editorial calendar',
                                }[option],
                            )}
                        </Link>
                    ))}
                </nav>

                {layout === 'board' && (
                    <Kanban
                        columns={visibleStages.map((stage) => ({
                            id: stage,
                            label: t(stageLabels[stage]),
                        }))}
                        items={items}
                        columnOf={(item) => item.stage}
                        renderCard={(item) => <CardContent item={item} />}
                        onMove={(item, stage) => onMove(item, stage as Stage)}
                    />
                )}

                {layout === 'list' && (
                    <table className="w-full text-sm">
                        <thead className="text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="py-2 font-medium">
                                    {t('Title')}
                                </th>
                                <th className="py-2 font-medium">
                                    {t('Stage')}
                                </th>
                                <th className="py-2 font-medium">
                                    {t('Format')}
                                </th>
                                <th className="py-2 font-medium">
                                    {t('Owner')}
                                </th>
                                <th className="py-2 font-medium">
                                    {t('Deadline')}
                                </th>
                                <th className="py-2 font-medium">
                                    {t('Publication date')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {items.map((item) => (
                                <tr key={item.id}>
                                    <td className="py-2">
                                        <Link
                                            href={show(item.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {item.title}
                                        </Link>
                                    </td>
                                    <td className="py-2">
                                        {t(stageLabels[item.stage])}
                                    </td>
                                    <td className="py-2 text-muted-foreground">
                                        {t(
                                            formatLabels[item.format] ??
                                                item.format,
                                        )}
                                    </td>
                                    <td className="py-2 text-muted-foreground">
                                        {item.owner?.name ?? '—'}
                                    </td>
                                    <td className="py-2 text-muted-foreground">
                                        {item.due_at
                                            ? formatDate(item.due_at, locale)
                                            : '—'}
                                    </td>
                                    <td className="py-2 text-muted-foreground">
                                        {item.publish_at
                                            ? formatDateTime(
                                                  item.publish_at,
                                                  locale,
                                              )
                                            : '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                {layout === 'calendar' && (
                    <div className="cidash-calendar text-sm">
                        <FullCalendar
                            plugins={[dayGridPlugin, listPlugin]}
                            locales={[ptLocale]}
                            locale={locale.startsWith('pt') ? 'pt' : 'en'}
                            initialView="dayGridMonth"
                            headerToolbar={{
                                left: 'prev,next today',
                                center: 'title',
                                right: 'dayGridMonth,listMonth',
                            }}
                            height="auto"
                            events={items
                                .filter((item) => item.publish_at !== null)
                                .map((item) => ({
                                    id: item.id,
                                    title: item.title,
                                    start: item.publish_at ?? undefined,
                                    url: show(item.id).url,
                                }))}
                            eventClick={(info) => {
                                info.jsEvent.preventDefault();

                                if (info.event.url) {
                                    router.visit(info.event.url);
                                }
                            }}
                        />
                        <p className="mt-2 text-xs text-muted-foreground">
                            {t(
                                'Only content with a publication date appears in the editorial calendar.',
                            )}
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}

ContentBoard.layout = {
    breadcrumbs: [{ title: 'Content', href: index() }],
};
