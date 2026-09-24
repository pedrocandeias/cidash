import { Form, Head, router } from '@inertiajs/react';
import { Pencil, X } from 'lucide-react';
import { useState } from 'react';
import ActivityFeed from '@/components/core/activity-feed';
import AttachmentsPanel from '@/components/core/attachments-panel';
import type { AttachmentItem } from '@/components/core/attachments-panel';
import type { ActivityItem } from '@/components/core/activity-feed';
import CommentsThread from '@/components/core/comments-thread';
import type { CommentItem } from '@/components/core/comments-thread';
import { RecordLink } from '@/components/core/object-drawer';
import RecordSearch from '@/components/core/record-search';
import RelationsPanel from '@/components/core/relations-panel';
import type {
    RecordSummary,
    RelationItem,
} from '@/components/core/relations-panel';
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
import CampaignFields, {
    campaignFormTransform,
} from '@/modules/campaigns/campaign-fields';
import CampaignCalendar from '@/modules/campaigns/campaign-calendar';
import type { CalendarEntry } from '@/modules/campaigns/campaign-calendar';
import CampaignTasks from '@/modules/campaigns/campaign-tasks';
import type { CampaignTask } from '@/modules/campaigns/campaign-tasks';
import CampaignSummaryView from '@/modules/campaigns/campaign-summary';
import type { CampaignDetails } from '@/modules/campaigns/types';
import { campaignFieldLabels } from '@/modules/campaigns/types';
import type { Member } from '@/modules/tasks/types';
import { destroy, index, update } from '@/routes/campaigns';
import { destroy as unlink, store as link } from '@/routes/links';

type Props = {
    campaign: CampaignDetails;
    parts: { link_id: number; record: RecordSummary }[];
    tasks: CampaignTask[];
    calendar: CalendarEntry[];
    members: Member[];
    recordId: string;
    comments: CommentItem[];
    activity: ActivityItem[];
    relations: RelationItem[];
    attachments: AttachmentItem[];
    can: { delete: boolean };
};

export default function ShowCampaign({
    campaign,
    parts,
    tasks,
    calendar,
    members,
    recordId,
    comments,
    activity,
    relations,
    attachments,
    can,
}: Props) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState(false);

    return (
        <>
            <Head title={campaign.name} />

            <div className="grid gap-10 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="space-y-10">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <Heading title={campaign.name} />
                        {!editing && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setEditing(true)}
                            >
                                <Pencil />
                                {t('Edit')}
                            </Button>
                        )}
                    </div>

                    {editing ? (
                        <Form
                            {...update.form(campaign.id)}
                            transform={campaignFormTransform}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setEditing(false)}
                            className="-mt-6 space-y-6 rounded-lg border p-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <CampaignFields
                                        members={members}
                                        errors={errors}
                                        defaults={campaign}
                                    />
                                    <div className="flex items-center gap-2">
                                        <Button disabled={processing}>
                                            {t('Save')}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() => setEditing(false)}
                                        >
                                            {t('Cancel')}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    ) : (
                        <CampaignSummaryView campaign={campaign} />
                    )}

                    <section className="space-y-3">
                        <h2 className="text-base font-medium">
                            {t('In this campaign')}
                        </h2>
                        {parts.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('No events or content added yet.')}
                            </p>
                        ) : (
                            <ul className="divide-y rounded-lg border">
                                {parts.map((part) => (
                                    <li
                                        key={part.link_id}
                                        className="flex items-center gap-3 px-3 py-2 text-sm"
                                    >
                                        <span className="w-24 shrink-0 text-xs text-muted-foreground">
                                            {t(part.record.label)}
                                        </span>
                                        <RecordLink
                                            record={part.record}
                                            className="flex-1 truncate"
                                        />
                                        <button
                                            type="button"
                                            className="text-muted-foreground hover:text-foreground"
                                            aria-label={t(
                                                'Remove from campaign',
                                            )}
                                            onClick={() =>
                                                router.delete(
                                                    unlink(part.link_id).url,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <X className="size-4" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <RecordSearch
                            excludeId={recordId}
                            excludeIds={parts.map((part) => part.record.id)}
                            placeholder={t(
                                'Add an event or content (search by title)',
                            )}
                            onPick={(record) =>
                                router.post(
                                    link(recordId).url,
                                    {
                                        target_id: record.id,
                                        type: 'part_of',
                                        reverse: true,
                                    },
                                    { preserveScroll: true },
                                )
                            }
                        />
                    </section>

                    <CampaignCalendar
                        campaignId={campaign.id}
                        entries={calendar}
                        startDate={campaign.start_date}
                        endDate={campaign.end_date}
                    />

                    <CampaignTasks
                        campaignId={campaign.id}
                        tasks={tasks}
                        parts={parts}
                        members={members}
                    />

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
                        fieldLabels={campaignFieldLabels}
                    />

                    {can.delete && (
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="outline" size="sm">
                                    {t('Delete campaign')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>
                                    {t('Delete this campaign?')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t(
                                        'Its events and content are kept; only the campaign and its comments are deleted.',
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
                                                destroy(campaign.id).url,
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

ShowCampaign.layout = {
    breadcrumbs: [{ title: 'Campaigns', href: index() }],
};
