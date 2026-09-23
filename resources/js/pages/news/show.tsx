import { Head, Link, router } from '@inertiajs/react';
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
import { index, show, update } from '@/routes/news';

type Props = {
    news: {
        id: string;
        headline: string;
        summary: string | null;
        outlet: string | null;
        url: string;
        published_at: string | null;
        retrieved_at: string;
        status: string;
        relevance: string | null;
    };
    sameStory: { id: string; headline: string; outlet: string | null }[];
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

export default function ShowNews({
    news,
    sameStory,
    members,
    recordId,
    comments,
    activity,
    relations,
    attachments,
}: Props) {
    const { t, locale } = useTranslation();
    const patch = (data: Record<string, string | null>) =>
        router.patch(update(news.id).url, data, { preserveScroll: true });

    return (
        <>
            <Head title={news.headline} />

            <div className="grid gap-10 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="space-y-8">
                    <div className="space-y-2">
                        <p className="text-sm text-muted-foreground">
                            {news.outlet} ·{' '}
                            {formatDateTime(
                                news.published_at ?? news.retrieved_at,
                                locale,
                            )}
                        </p>
                        <Heading title={news.headline} />
                        {news.summary && (
                            <p className="-mt-6 text-sm">{news.summary}</p>
                        )}
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={news.url}
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
                                value={news.status}
                                onValueChange={(status) => patch({ status })}
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
                                value={news.relevance ?? 'none'}
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

                    {sameStory.length > 0 && (
                        <section className="space-y-2">
                            <h2 className="text-base font-medium">
                                {t('Same story in other outlets')}
                            </h2>
                            <ul className="space-y-1 text-sm">
                                {sameStory.map((other) => (
                                    <li key={other.id}>
                                        <span className="text-muted-foreground">
                                            {other.outlet} ·{' '}
                                        </span>
                                        <Link
                                            href={show(other.id)}
                                            className="hover:underline"
                                        >
                                            {other.headline}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    <CommentsThread recordId={recordId} comments={comments} />
                </div>

                <aside className="space-y-10">
                    <CreateTaskButton
                        sourceId={recordId}
                        sourceTitle={news.headline}
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
                            status: 'Triage',
                            relevance: 'Relevance',
                        }}
                    />
                </aside>
            </div>
        </>
    );
}

ShowNews.layout = {
    breadcrumbs: [{ title: 'News coverage', href: index() }],
};
