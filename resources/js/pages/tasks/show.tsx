import { Form, Head, router } from '@inertiajs/react';
import ActivityFeed from '@/components/core/activity-feed';
import AttachmentsPanel from '@/components/core/attachments-panel';
import type { AttachmentItem } from '@/components/core/attachments-panel';
import type { ActivityItem } from '@/components/core/activity-feed';
import CommentsThread from '@/components/core/comments-thread';
import type { CommentItem } from '@/components/core/comments-thread';
import RelationsPanel from '@/components/core/relations-panel';
import type { RelationItem } from '@/components/core/relations-panel';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime, useTranslation } from '@/lib/i18n';
import TaskFields, { taskFormTransform } from '@/modules/tasks/task-fields';
import type { Member, TaskSummary } from '@/modules/tasks/types';
import { statusLabels, taskFieldLabels } from '@/modules/tasks/types';
import { destroy, index, update } from '@/routes/tasks';

type Props = {
    task: TaskSummary & {
        description: string | null;
        created_at: string;
        source: { type: string; title: string } | null;
    };
    members: Member[];
    comments: CommentItem[];
    activity: ActivityItem[];
    relations: RelationItem[];
    attachments: AttachmentItem[];
    recordId: string;
    can: { delete: boolean };
};

export default function ShowTask({
    task,
    members,
    comments,
    activity,
    relations,
    attachments,
    recordId,
    can,
}: Props) {
    const { t, locale } = useTranslation();

    return (
        <>
            <Head title={task.title} />

            <div className="grid gap-10 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="space-y-10">
                    <Heading title={task.title} />

                    <div className="-mt-4 flex flex-wrap items-end gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="status">{t('Status')}</Label>
                            <Select
                                value={task.status}
                                onValueChange={(status) =>
                                    router.patch(
                                        update(task.id).url,
                                        { status },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <SelectTrigger id="status" className="w-44">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(statusLabels).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {t(label)}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {t('Created :date', {
                                date: formatDateTime(task.created_at, locale),
                            })}
                            {task.source &&
                                ` · ${t('From: :title', { title: task.source.title })}`}
                        </p>
                    </div>

                    <Form
                        {...update.form(task.id)}
                        transform={taskFormTransform}
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                    >
                        {({ processing, errors, recentlySuccessful }) => (
                            <>
                                <TaskFields
                                    members={members}
                                    errors={errors}
                                    defaults={{
                                        title: task.title,
                                        type: task.type,
                                        description: task.description,
                                        assignees: task.assignees,
                                        co_assignees: task.co_assignees,
                                        start_date: task.start_date,
                                        deadline: task.deadline,
                                        priority: task.priority,
                                    }}
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
                    <AttachmentsPanel
                        recordId={recordId}
                        attachments={attachments}
                    />
                    <RelationsPanel recordId={recordId} relations={relations} />
                    <ActivityFeed
                        activity={activity}
                        fieldLabels={taskFieldLabels}
                    />

                    {can.delete && (
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="outline" size="sm">
                                    {t('Delete task')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>
                                    {t('Delete this task?')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t(
                                        'The task and its comments will be deleted. This cannot be undone.',
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
                                            router.delete(destroy(task.id).url)
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

ShowTask.layout = {
    breadcrumbs: [{ title: 'Tasks', href: index() }],
};
