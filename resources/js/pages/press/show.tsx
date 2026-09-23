import { Form, Head, router } from '@inertiajs/react';
import ActivityFeed from '@/components/core/activity-feed';
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
import { formatDateTime, useTranslation } from '@/lib/i18n';
import PressFields, { pressFormTransform } from '@/modules/press/press-fields';
import type { Known, PressDetails } from '@/modules/press/types';
import { pressFieldLabels } from '@/modules/press/types';
import type { Member } from '@/modules/tasks/types';
import { destroy, index, update } from '@/routes/press';

type Props = {
    pressRequest: PressDetails;
    members: Member[];
    known: Known;
    recordId: string;
    comments: CommentItem[];
    activity: ActivityItem[];
    relations: RelationItem[];
    reminders: ReminderItem[];
    can: { delete: boolean };
};

export default function ShowPressRequest({
    pressRequest,
    members,
    known,
    recordId,
    comments,
    activity,
    relations,
    reminders,
    can,
}: Props) {
    const { t, locale } = useTranslation();

    return (
        <>
            <Head title={pressRequest.subject} />

            <div className="grid gap-10 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="space-y-10">
                    <div className="space-y-1">
                        <Heading title={pressRequest.subject} />
                        {pressRequest.answered_at && (
                            <p className="-mt-6 text-sm text-muted-foreground">
                                {t('Answered on :date', {
                                    date: formatDateTime(
                                        pressRequest.answered_at,
                                        locale,
                                    ),
                                })}
                            </p>
                        )}
                    </div>

                    <Form
                        {...update.form(pressRequest.id)}
                        transform={pressFormTransform}
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                    >
                        {({ processing, errors, recentlySuccessful }) => (
                            <>
                                <PressFields
                                    members={members}
                                    known={known}
                                    errors={errors}
                                    defaults={pressRequest}
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
                    <CreateTaskButton
                        sourceId={recordId}
                        sourceTitle={pressRequest.subject}
                        members={members}
                    />
                    {pressRequest.deadline && (
                        <RemindersPanel
                            recordId={recordId}
                            anchor={pressRequest.deadline}
                            reminders={reminders}
                        />
                    )}
                    <RelationsPanel recordId={recordId} relations={relations} />
                    <ActivityFeed
                        activity={activity}
                        fieldLabels={pressFieldLabels}
                    />

                    {can.delete && (
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="outline" size="sm">
                                    {t('Delete request')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>
                                    {t('Delete this press request?')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t(
                                        'The request, its comments and reminders will be deleted. This cannot be undone.',
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
                                            router.delete(
                                                destroy(pressRequest.id).url,
                                            )
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

ShowPressRequest.layout = {
    breadcrumbs: [{ title: 'Press requests', href: index() }],
};
