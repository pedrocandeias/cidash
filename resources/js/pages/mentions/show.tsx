import { Head, router } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import ActivityFeed from '@/components/core/activity-feed';
import AttachmentsPanel from '@/components/core/attachments-panel';
import type { AttachmentItem } from '@/components/core/attachments-panel';
import type { ActivityItem } from '@/components/core/activity-feed';
import CommentsThread from '@/components/core/comments-thread';
import type { CommentItem } from '@/components/core/comments-thread';
import CreateTaskButton from '@/components/core/create-task-button';
import RelationsPanel from '@/components/core/relations-panel';
import type { RelationItem } from '@/components/core/relations-panel';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime, useTranslation } from '@/lib/i18n';
import type { Member } from '@/modules/tasks/types';
import { index, update } from '@/routes/mentions';

type Props = {
    mention: {
        id: string;
        headline: string;
        excerpt: string | null;
        outlet: string | null;
        url: string;
        published_at: string | null;
        matched_keyword: string;
        rule: string | null;
        category: string | null;
        status: string;
        relevance: string | null;
    };
    members: Member[];
    recordId: string;
    comments: CommentItem[];
    activity: ActivityItem[];
    relations: RelationItem[];
    attachments: AttachmentItem[];
};

const statusLabels: Record<string, string> = {
    new: 'To triage',
    relevant: 'Relevant',
    irrelevant: 'Not relevant',
    archived: 'Archived',
};
const relevanceLabels: Record<string, string> = {
    none: 'Not set',
    low: 'Low',
    medium: 'Medium',
    high: 'High',
};

export default function ShowMention({
    mention,
    members,
    recordId,
    comments,
    activity,
    relations,
    attachments,
}: Props) {
    const { t, locale } = useTranslation();
    const patch = (data: Record<string, string | null>) =>
        router.patch(update(mention.id).url, data, { preserveScroll: true });

    return (
        <>
            <Head title={mention.headline} />

            <div className="grid gap-10 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="space-y-8">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span>{mention.outlet}</span>
                            {mention.published_at && (
                                <span>
                                    ·{' '}
                                    {formatDateTime(
                                        mention.published_at,
                                        locale,
                                    )}
                                </span>
                            )}
                            <Badge variant="secondary">
                                {mention.matched_keyword}
                            </Badge>
                            {mention.rule && (
                                <span>
                                    · {t('Rule: :name', { name: mention.rule })}
                                </span>
                            )}
                        </div>
                        <Heading title={mention.headline} />
                        {mention.excerpt && (
                            <p className="-mt-6 text-sm">{mention.excerpt}</p>
                        )}
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={mention.url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLink />
                                {t('Open original article')}
                            </a>
                        </Button>
                    </div>

                    <div className="flex flex-wrap gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="status">{t('Triage')}</Label>
                            <Select
                                value={mention.status}
                                onValueChange={(review_status) =>
                                    patch({ review_status })
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
                        <div className="grid gap-2">
                            <Label htmlFor="relevance">{t('Relevance')}</Label>
                            <Select
                                value={mention.relevance ?? 'none'}
                                onValueChange={(relevance) =>
                                    patch({
                                        relevance:
                                            relevance === 'none'
                                                ? null
                                                : relevance,
                                    })
                                }
                            >
                                <SelectTrigger id="relevance" className="w-40">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(relevanceLabels).map(
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
                    </div>

                    <CommentsThread recordId={recordId} comments={comments} />
                </div>

                <aside className="space-y-10">
                    <CreateTaskButton
                        sourceId={recordId}
                        sourceTitle={mention.headline}
                        members={members}
                    />
                    <AttachmentsPanel
                        recordId={recordId}
                        attachments={attachments}
                    />
                    <RelationsPanel recordId={recordId} relations={relations} />
                    <ActivityFeed
                        activity={activity}
                        fieldLabels={{
                            review_status: 'Triage',
                            relevance: 'Relevance',
                        }}
                    />
                </aside>
            </div>
        </>
    );
}

ShowMention.layout = {
    breadcrumbs: [{ title: 'Media mentions', href: index() }],
};
