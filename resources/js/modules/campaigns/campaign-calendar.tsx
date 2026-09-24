import ptLocale from '@fullcalendar/core/locales/pt';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';
import FullCalendar from '@fullcalendar/react';
import { Form } from '@inertiajs/react';
import { CalendarPlus } from 'lucide-react';
import { useState } from 'react';
import { previewRecord } from '@/components/core/object-drawer';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
import { useOptions } from '@/lib/options';
import { cn } from '@/lib/utils';
import { store as storeEvent } from '@/routes/events';

export type CalendarEntry = {
    id: string;
    title: string;
    start: string;
    end: string | null;
    all_day: boolean;
    kind: 'event' | 'publication' | 'content_due' | 'task';
    type: string | null;
    done: boolean;
};

/** Colours per kind of date, from the theme (events use their type's colour). */
const kinds: { kind: CalendarEntry['kind']; label: string; colour: string }[] =
    [
        { kind: 'event', label: 'Events', colour: 'var(--primary)' },
        {
            kind: 'publication',
            label: 'Publications',
            colour: 'var(--success)',
        },
        {
            kind: 'content_due',
            label: 'Content deadlines',
            colour: 'var(--warning)',
        },
        { kind: 'task', label: 'Task deadlines', colour: 'var(--info)' },
    ];

function AddDateDialog({ campaignId }: { campaignId: string }) {
    const { t } = useTranslation();
    const types = useOptions('event_type');
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <CalendarPlus />
                    {t('Add important date')}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>
                    {t('Important date in this campaign')}
                </DialogTitle>
                <Form
                    {...storeEvent.form()}
                    transform={(data) => ({
                        ...data,
                        all_day: true,
                        campaign_id: campaignId,
                        priority: 'normal',
                        status: 'confirmed',
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="campaign-date-title">
                                    {t('Title')}
                                </Label>
                                <Input
                                    id="campaign-date-title"
                                    name="title"
                                    required
                                    placeholder={t('e.g. Applications open')}
                                />
                                <InputError message={errors.title} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="campaign-date-start">
                                        {t('Date')}
                                    </Label>
                                    <Input
                                        id="campaign-date-start"
                                        name="start_at"
                                        type="date"
                                        required
                                    />
                                    <InputError message={errors.start_at} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="campaign-date-end">
                                        {t('Until (optional)')}
                                    </Label>
                                    <Input
                                        id="campaign-date-end"
                                        name="end_at"
                                        type="date"
                                    />
                                    <InputError message={errors.end_at} />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="campaign-date-type">
                                    {t('Type')}
                                </Label>
                                <Select
                                    name="type"
                                    defaultValue={
                                        types.active.find(
                                            (option) =>
                                                option.key === 'deadline',
                                        )?.key ?? types.active[0]?.key
                                    }
                                >
                                    <SelectTrigger id="campaign-date-type">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {types.active.map((option) => (
                                            <SelectItem
                                                key={option.key}
                                                value={option.key}
                                            >
                                                {t(option.label)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button
                                disabled={processing}
                                className="justify-self-start"
                            >
                                {t('Add date')}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * The campaign's calendar: its period, events, publications, content and task deadlines.
 */
export default function CampaignCalendar({
    campaignId,
    entries,
    startDate,
    endDate,
}: {
    campaignId: string;
    entries: CalendarEntry[];
    startDate: string | null;
    endDate: string | null;
}) {
    const { t, locale } = useTranslation();
    const eventTypes = useOptions('event_type');
    const [hidden, setHidden] = useState<CalendarEntry['kind'][]>([]);
    const prefix: Record<CalendarEntry['kind'], string> = {
        event: '',
        publication: t('Publication: '),
        content_due: t('Due: '),
        task: t('Task: '),
    };
    const colour = (entry: CalendarEntry) =>
        entry.kind === 'event' && entry.type
            ? eventTypes.color(entry.type)
            : (kinds.find((kind) => kind.kind === entry.kind)?.colour ??
              'var(--primary)');

    // The campaign's period as a background band; the end date is inclusive.
    const period =
        startDate !== null
            ? [
                  {
                      start: startDate,
                      end: endDate
                          ? new Date(
                                new Date(`${endDate}T00:00:00`).getTime() +
                                    86400000,
                            )
                                .toISOString()
                                .slice(0, 10)
                          : undefined,
                      display: 'background',
                      color: 'var(--primary)',
                  },
              ]
            : [];

    return (
        <section className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-base font-medium">
                    {t('Campaign calendar')}
                </h2>
                <AddDateDialog campaignId={campaignId} />
            </div>
            <div className="flex flex-wrap items-center gap-3">
                {kinds.map((kind) => (
                    <button
                        key={kind.kind}
                        type="button"
                        aria-pressed={!hidden.includes(kind.kind)}
                        onClick={() =>
                            setHidden(
                                hidden.includes(kind.kind)
                                    ? hidden.filter(
                                          (other) => other !== kind.kind,
                                      )
                                    : [...hidden, kind.kind],
                            )
                        }
                        className={cn(
                            'flex items-center gap-1.5 rounded-md px-2 py-1 text-xs hover:bg-muted',
                            hidden.includes(kind.kind) &&
                                'text-muted-foreground line-through opacity-60',
                        )}
                    >
                        <span
                            className="size-2.5 rounded-xs"
                            style={{ backgroundColor: kind.colour }}
                        />
                        {t(kind.label)}
                    </button>
                ))}
            </div>
            <div className="cidash-calendar text-sm">
                <FullCalendar
                    plugins={[dayGridPlugin, listPlugin]}
                    locales={[ptLocale]}
                    locale={locale.startsWith('pt') ? 'pt' : 'en'}
                    initialView="dayGridMonth"
                    initialDate={startDate ?? undefined}
                    headerToolbar={{
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,listYear',
                    }}
                    height="auto"
                    dayMaxEvents={4}
                    events={[
                        ...period,
                        ...entries
                            .filter((entry) => !hidden.includes(entry.kind))
                            .map((entry) => ({
                                id: `${entry.kind}-${entry.id}`,
                                title: `${prefix[entry.kind]}${entry.title}`,
                                start: entry.start,
                                end: entry.end ?? undefined,
                                allDay: entry.all_day,
                                color: colour(entry),
                                classNames: entry.done
                                    ? ['line-through', 'opacity-60']
                                    : [],
                                extendedProps: { recordId: entry.id },
                            })),
                    ]}
                    eventClick={(info) => {
                        const recordId = info.event.extendedProps.recordId as
                            | string
                            | undefined;

                        if (recordId) {
                            previewRecord(recordId);
                        }
                    }}
                />
            </div>
        </section>
    );
}
