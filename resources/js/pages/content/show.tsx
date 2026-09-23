import { Form, Head, router } from '@inertiajs/react';
import ActivityFeed from '@/components/core/activity-feed';
import type { ActivityItem } from '@/components/core/activity-feed';
import CommentsThread from '@/components/core/comments-thread';
import type { CommentItem } from '@/components/core/comments-thread';
import CreateTaskButton from '@/components/core/create-task-button';
import RelationsPanel from '@/components/core/relations-panel';
import type { RelationItem } from '@/components/core/relations-panel';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { useTranslation } from '@/lib/i18n';
import ContentFields, {
    contentFormTransform,
} from '@/modules/content/content-fields';
import type { ContentDetails, Stage } from '@/modules/content/types';
import {
    contentFieldLabels,
    isApproval,
    stageLabels,
    stages,
} from '@/modules/content/types';
import type { Member } from '@/modules/tasks/types';
import { destroy, index, update } from '@/routes/content';

type Props = {
    item: ContentDetails;
    members: Member[];
    recordId: string;
    comments: CommentItem[];
    activity: ActivityItem[];
    relations: RelationItem[];
    errors?: Record<string, string>;
    can: { delete: boolean; approve: boolean };
};

export default function ShowContent({
    item,
    members,
    recordId,
    comments,
    activity,
    relations,
    errors,
    can,
}: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={item.title} />

            <div className="grid gap-10 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="space-y-10">
                    <Heading title={item.title} />

                    <div className="-mt-10 grid gap-2">
                        <Label htmlFor="stage">{t('Stage')}</Label>
                        <Select
                            value={item.stage}
                            onValueChange={(stage) =>
                                router.patch(
                                    update(item.id).url,
                                    { stage },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <SelectTrigger id="stage" className="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {stages.map((stage: Stage) => (
                                    <SelectItem
                                        key={stage}
                                        value={stage}
                                        disabled={
                                            !can.approve &&
                                            isApproval(item.stage, stage)
                                        }
                                    >
                                        {t(stageLabels[stage])}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors?.stage} />
                        {!can.approve && item.stage === 'review' && (
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'Waiting for approval by an editor or manager.',
                                )}
                            </p>
                        )}
                    </div>

                    <Form
                        {...update.form(item.id)}
                        transform={contentFormTransform}
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                    >
                        {({
                            processing,
                            errors: formErrors,
                            recentlySuccessful,
                        }) => (
                            <>
                                <ContentFields
                                    members={members}
                                    errors={formErrors}
                                    defaults={item}
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
                        sourceTitle={item.title}
                        members={members}
                    />
                    <RelationsPanel recordId={recordId} relations={relations} />
                    <ActivityFeed
                        activity={activity}
                        fieldLabels={contentFieldLabels}
                    />

                    {can.delete && (
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="outline" size="sm">
                                    {t('Delete content')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>
                                    {t('Delete this content?')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t(
                                        'Consider archiving it instead. Deleting removes its comments and history. This cannot be undone.',
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
                                            router.delete(destroy(item.id).url)
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

ShowContent.layout = {
    breadcrumbs: [{ title: 'Content', href: index() }],
};
