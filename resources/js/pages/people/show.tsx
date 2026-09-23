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
import { Badge } from '@/components/ui/badge';
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
import { localToday, useTranslation } from '@/lib/i18n';
import CopyProfileButton from '@/modules/people/copy-profile-button';
import { PersonPhoto } from '@/modules/people/person-card';
import PersonFields, {
    personFormTransform,
} from '@/modules/people/person-fields';
import type { PersonDetails } from '@/modules/people/types';
import { personFieldLabels } from '@/modules/people/types';
import { destroy, index, update } from '@/routes/people';

type Props = {
    person: PersonDetails;
    recordId: string;
    comments: CommentItem[];
    activity: ActivityItem[];
    relations: RelationItem[];
    attachments: AttachmentItem[];
    can: { delete: boolean };
};

export default function ShowPerson({
    person,
    recordId,
    comments,
    activity,
    relations,
    attachments,
    can,
}: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={person.name} />

            <div className="grid gap-10 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="space-y-10">
                    <div className="flex items-start gap-4">
                        <PersonPhoto person={person} size="size-20" />
                        <div className="space-y-2">
                            <Heading
                                title={person.name}
                                description={[
                                    person.academic_title,
                                    person.affiliation,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            />
                            <div className="-mt-6 flex flex-wrap items-center gap-2">
                                <CopyProfileButton person={person} />
                                {person.needs_review && (
                                    <>
                                        <Badge variant="outline">
                                            {t('Bio to review')}
                                        </Badge>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                router.patch(
                                                    update(person.id).url,
                                                    {
                                                        last_reviewed_at:
                                                            localToday(),
                                                    },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t('Mark as reviewed today')}
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>

                    <Form
                        {...update.form(person.id)}
                        transform={personFormTransform}
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                    >
                        {({ processing, errors, recentlySuccessful }) => (
                            <>
                                <PersonFields
                                    errors={errors}
                                    defaults={person}
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
                        fieldLabels={personFieldLabels}
                    />

                    {can.delete && (
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="outline" size="sm">
                                    {t('Delete profile')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>
                                    {t('Delete this profile?')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t(
                                        'The profile, its photo and comments will be deleted. This cannot be undone.',
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
                                                destroy(person.id).url,
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

ShowPerson.layout = {
    breadcrumbs: [{ title: 'People of interest', href: index() }],
};
