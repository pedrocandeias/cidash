import { Form, Head, router } from '@inertiajs/react';
import { CalendarPlus } from 'lucide-react';
import ActivityFeed from '@/components/core/activity-feed';
import AttachmentsPanel from '@/components/core/attachments-panel';
import type { AttachmentItem } from '@/components/core/attachments-panel';
import type { ActivityItem } from '@/components/core/activity-feed';
import CommentsThread from '@/components/core/comments-thread';
import type { CommentItem } from '@/components/core/comments-thread';
import CreateTaskButton from '@/components/core/create-task-button';
import RelationsPanel from '@/components/core/relations-panel';
import type { RelationItem } from '@/components/core/relations-panel';
import RemindersPanel from '@/components/core/reminders-panel';
import type { ReminderItem } from '@/components/core/reminders-panel';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useTranslation } from '@/lib/i18n';
import { useOptions } from '@/lib/options';
import EventFields, { eventFormTransform } from '@/modules/events/event-fields';
import type { EventDetails } from '@/modules/events/types';
import { eventFieldLabels } from '@/modules/events/types';
import type { Member } from '@/modules/tasks/types';
import { destroy, ics, index, update } from '@/routes/events';

type Props = {
    event: EventDetails;
    members: Member[];
    recordId: string;
    comments: CommentItem[];
    activity: ActivityItem[];
    relations: RelationItem[];
    attachments: AttachmentItem[];
    reminders: ReminderItem[];
    can: { delete: boolean };
};

export default function ShowEvent({
    event,
    members,
    recordId,
    comments,
    activity,
    relations,
    attachments,
    reminders,
    can,
}: Props) {
    const { t } = useTranslation();
    const types = useOptions('event_type');

    return (
        <>
            <Head title={event.title} />

            <div className="grid gap-10 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="space-y-10">
                    <div className="space-y-2">
                        <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span
                                className="size-2.5 rounded-full"
                                style={{
                                    backgroundColor: types.color(event.type),
                                }}
                            />
                            {t(types.label(event.type))}
                        </span>
                        <Heading title={event.title} />
                    </div>

                    <Form
                        {...update.form(event.id)}
                        transform={eventFormTransform}
                        options={{ preserveScroll: true }}
                        className="-mt-8 space-y-6"
                    >
                        {({ processing, errors, recentlySuccessful }) => (
                            <>
                                <EventFields
                                    members={members}
                                    errors={errors}
                                    defaults={event}
                                    withStatus
                                />
                                <div className="flex items-center gap-4">
                                    <Button disabled={processing}>
                                        {t('Save')}
                                    </Button>
                                    {recentlySuccessful && (
                                        <span className="text-sm text-muted-foreground">
                                            {t('Saved.')}
                                        </span>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>

                    <CommentsThread recordId={recordId} comments={comments} />
                </div>

                <aside className="space-y-10">
                    <div className="flex flex-wrap gap-2">
                        <CreateTaskButton
                            sourceId={recordId}
                            sourceTitle={event.title}
                            members={members}
                        />
                        <Button asChild variant="outline" size="sm">
                            <a href={ics(event.id).url}>
                                <CalendarPlus />
                                {t('Add to my calendar')}
                            </a>
                        </Button>
                    </div>
                    <RemindersPanel
                        recordId={recordId}
                        anchor={event.start_at}
                        reminders={reminders}
                    />
                    <AttachmentsPanel
                        recordId={recordId}
                        attachments={attachments}
                    />
                    <RelationsPanel recordId={recordId} relations={relations} />
                    <ActivityFeed
                        activity={activity}
                        fieldLabels={eventFieldLabels}
                    />

                    {can.delete && (
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="outline" size="sm">
                                    {t('Delete event')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>
                                    {t('Delete this event?')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t(
                                        'The event, its comments and reminders will be deleted. This cannot be undone.',
                                    )}
                                </DialogDescription>
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button variant="secondary">
                                            {t('Cancel')}
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        variant="destructive"
                                        onClick={() =>
                                            router.delete(destroy(event.id).url)
                                        }
                                    >
                                        {t('Delete')}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    )}
                </aside>
            </div>
        </>
    );
}

ShowEvent.layout = {
    breadcrumbs: [{ title: 'Calendar', href: index() }],
};
