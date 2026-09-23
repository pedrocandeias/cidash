import type { EventApi } from '@fullcalendar/core';
import type { DateClickArg } from '@fullcalendar/interaction';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import dayGridPlugin from '@fullcalendar/daygrid';
import ptLocale from '@fullcalendar/core/locales/pt';
import FullCalendar from '@fullcalendar/react';
import timeGridPlugin from '@fullcalendar/timegrid';
import { Form, Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { localDate, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import EventFields, { eventFormTransform } from '@/modules/events/event-fields';
import type { EventStatus, EventType } from '@/modules/events/types';
import { typeColors, typeLabels } from '@/modules/events/types';
import type { Member } from '@/modules/tasks/types';
import { feed, index, store, update } from '@/routes/events';

type Props = { members: Member[]; campaigns: { id: string; name: string }[] };

const ALL = 'all';

type Draft = { start_at: string; all_day: boolean };

export default function Calendar({ members, campaigns }: Props) {
    const { t, locale } = useTranslation();
    const [draft, setDraft] = useState<Draft | null>(null);
    const [hiddenTypes, setHiddenTypes] = useState<EventType[]>([]);
    const [responsible, setResponsible] = useState(ALL);
    const [campaign, setCampaign] = useState(ALL);
    const calendar = useRef<FullCalendar>(null);

    // Read by the event source on every fetch; refetched when a filter changes.
    const filters = useRef<Record<string, string>>({});
    useEffect(() => {
        const visible = (Object.keys(typeLabels) as EventType[]).filter(
            (type) => !hiddenTypes.includes(type),
        );
        filters.current = {
            ...Object.fromEntries(
                visible.map((type, position) => [`types[${position}]`, type]),
            ),
            ...(responsible !== ALL ? { responsible } : {}),
            ...(campaign !== ALL ? { campaign } : {}),
        };
        calendar.current?.getApi().refetchEvents();
    }, [hiddenTypes, responsible, campaign]);

    const toggleType = (type: EventType) =>
        setHiddenTypes((hidden) =>
            hidden.includes(type)
                ? hidden.filter((other) => other !== type)
                : [...hidden, type],
        );

    // Dragging or resizing an event saves it; all-day ends are exclusive in FullCalendar and inclusive in CIDASH.
    const persist = ({
        event,
        revert,
    }: {
        event: EventApi;
        revert: () => void;
    }) => {
        if (!event.start) {
            return revert();
        }

        const dayBefore = (date: Date) =>
            new Date(date.getFullYear(), date.getMonth(), date.getDate() - 1);
        const payload = event.allDay
            ? {
                  all_day: true,
                  start_at: localDate(event.start),
                  end_at: event.end ? localDate(dayBefore(event.end)) : null,
              }
            : {
                  all_day: false,
                  start_at: event.start.toISOString(),
                  end_at: event.end ? event.end.toISOString() : null,
              };

        router.patch(update(event.id).url, payload, {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                revert();
                toast.error(t('The event could not be moved.'));
            },
        });
    };

    const openDraft = (arg?: DateClickArg) => {
        if (!arg) {
            setDraft({ start_at: '', all_day: false });

            return;
        }

        // Month view clicks are days; week/day view clicks are times.
        setDraft({
            start_at: arg.allDay ? arg.dateStr : arg.dateStr.slice(0, 16),
            all_day: arg.allDay,
        });
    };

    return (
        <>
            <Head title={t('Calendar')} />

            <div className="space-y-4 px-4 py-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title={t('Calendar')} />
                    <Button onClick={() => openDraft()}>
                        {t('New event')}
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    {(Object.keys(typeLabels) as EventType[]).map((type) => (
                        <button
                            key={type}
                            type="button"
                            aria-pressed={!hiddenTypes.includes(type)}
                            onClick={() => toggleType(type)}
                            className={cn(
                                'flex items-center gap-1.5 rounded-md px-2 py-1 text-xs hover:bg-muted',
                                hiddenTypes.includes(type)
                                    ? 'text-muted-foreground line-through opacity-60'
                                    : 'text-foreground',
                            )}
                        >
                            <span
                                className="size-2.5 rounded-full"
                                style={{ backgroundColor: typeColors[type] }}
                            />
                            {t(typeLabels[type])}
                        </button>
                    ))}
                    <Select value={responsible} onValueChange={setResponsible}>
                        <SelectTrigger
                            className="h-8 w-44"
                            aria-label={t('Responsible')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('Any responsible')}
                            </SelectItem>
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
                    {campaigns.length > 0 && (
                        <Select value={campaign} onValueChange={setCampaign}>
                            <SelectTrigger
                                className="h-8 w-52"
                                aria-label={t('Campaign')}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    {t('Any campaign')}
                                </SelectItem>
                                {campaigns.map((option) => (
                                    <SelectItem
                                        key={option.id}
                                        value={option.id}
                                    >
                                        {option.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>

                <div className="cidash-calendar text-sm">
                    <FullCalendar
                        ref={calendar}
                        plugins={[
                            dayGridPlugin,
                            timeGridPlugin,
                            listPlugin,
                            interactionPlugin,
                        ]}
                        locales={[ptLocale]}
                        locale={locale.startsWith('pt') ? 'pt' : 'en'}
                        initialView="dayGridMonth"
                        headerToolbar={{
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth',
                        }}
                        height="auto"
                        nowIndicator
                        dayMaxEvents={4}
                        events={{
                            url: feed.url(),
                            extraParams: () => filters.current,
                        }}
                        editable
                        eventDrop={persist}
                        eventResize={persist}
                        eventDataTransform={(event) => {
                            const props = event.extendedProps as {
                                type: EventType;
                                status: EventStatus;
                            };

                            return {
                                ...event,
                                color: typeColors[props.type],
                                classNames:
                                    props.status === 'cancelled'
                                        ? ['line-through', 'opacity-60']
                                        : props.status === 'tentative'
                                          ? ['opacity-70']
                                          : [],
                            };
                        }}
                        eventClick={(info) => {
                            info.jsEvent.preventDefault();

                            if (info.event.url) {
                                router.visit(info.event.url);
                            }
                        }}
                        dateClick={openDraft}
                    />
                </div>
            </div>

            <Dialog
                open={draft !== null}
                onOpenChange={(open) => !open && setDraft(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogTitle>{t('New event')}</DialogTitle>
                    {draft && (
                        <Form
                            {...store.form()}
                            transform={eventFormTransform}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <EventFields
                                        members={members}
                                        errors={errors}
                                        defaults={draft}
                                    />
                                    <Button disabled={processing}>
                                        {t('Create event')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

Calendar.layout = {
    breadcrumbs: [{ title: 'Calendar', href: index() }],
};
