import type { DateClickArg } from '@fullcalendar/interaction';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import dayGridPlugin from '@fullcalendar/daygrid';
import ptLocale from '@fullcalendar/core/locales/pt';
import FullCalendar from '@fullcalendar/react';
import timeGridPlugin from '@fullcalendar/timegrid';
import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useTranslation } from '@/lib/i18n';
import EventFields, { eventFormTransform } from '@/modules/events/event-fields';
import type { EventStatus, EventType } from '@/modules/events/types';
import { typeColors, typeLabels } from '@/modules/events/types';
import type { Member } from '@/modules/tasks/types';
import { feed, index, store } from '@/routes/events';

type Props = { members: Member[] };

type Draft = { start_at: string; all_day: boolean };

export default function Calendar({ members }: Props) {
    const { t, locale } = useTranslation();
    const [draft, setDraft] = useState<Draft | null>(null);

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

                <div className="flex flex-wrap gap-4 text-xs text-muted-foreground">
                    {(Object.keys(typeLabels) as EventType[]).map((type) => (
                        <span key={type} className="flex items-center gap-1.5">
                            <span
                                className="size-2.5 rounded-full"
                                style={{ backgroundColor: typeColors[type] }}
                            />
                            {t(typeLabels[type])}
                        </span>
                    ))}
                </div>

                <div className="cidash-calendar text-sm">
                    <FullCalendar
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
                        events={feed.url()}
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
