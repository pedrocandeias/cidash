import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import ActivityFeed from '@/components/core/activity-feed';
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
import { useTranslation } from '@/lib/i18n';
import NoticeFields, {
    noticeFormTransform,
} from '@/modules/notices/notice-fields';
import type { Notice } from '@/modules/notices/notice-fields';
import NoticeMeta from '@/modules/notices/notice-meta';
import { destroy, index, update } from '@/routes/notices';

type Props = {
    notice: Notice;
    recordId: string;
    comments: CommentItem[];
    activity: ActivityItem[];
    relations: RelationItem[];
    can: { delete: boolean; pin: boolean };
};

const fieldLabels: Record<string, string> = {
    title: 'Title',
    body: 'Text',
    published_at: 'Publish on',
    expires_at: 'Expires on',
    priority: 'Priority',
    pinned: 'Pinned',
};

export default function ShowNotice({
    notice,
    recordId,
    comments,
    activity,
    relations,
    can,
}: Props) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState(false);

    return (
        <>
            <Head title={notice.title} />

            <div className="grid gap-10 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="space-y-8">
                    <div className="space-y-2">
                        <NoticeMeta notice={notice} />
                        <Heading title={notice.title} />
                        <p className="-mt-6 text-sm whitespace-pre-line">
                            {notice.body}
                        </p>
                    </div>

                    <Dialog open={editing} onOpenChange={setEditing}>
                        <DialogTrigger asChild>
                            <Button variant="outline" size="sm">
                                {t('Edit')}
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-2xl">
                            <DialogTitle>{t('Edit notice')}</DialogTitle>
                            <Form
                                {...update.form(notice.id)}
                                transform={noticeFormTransform}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setEditing(false)}
                                className="space-y-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <NoticeFields
                                            errors={errors}
                                            defaults={notice}
                                            canPin={can.pin}
                                        />
                                        <Button disabled={processing}>
                                            {t('Save')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>

                    <CommentsThread recordId={recordId} comments={comments} />
                </div>

                <aside className="space-y-10">
                    <RelationsPanel recordId={recordId} relations={relations} />
                    <ActivityFeed
                        activity={activity}
                        fieldLabels={fieldLabels}
                    />

                    {can.delete && (
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="outline" size="sm">
                                    {t('Delete notice')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>
                                    {t('Delete this notice?')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t(
                                        'The notice and its comments will be deleted. This cannot be undone.',
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
                                                destroy(notice.id).url,
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

ShowNotice.layout = {
    breadcrumbs: [{ title: 'Notices', href: index() }],
};
