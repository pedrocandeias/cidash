export type Stage =
    | 'idea'
    | 'preparing'
    | 'review'
    | 'approved'
    | 'scheduled'
    | 'published'
    | 'archived';

export type ContentSummary = {
    id: string;
    title: string;
    format: string;
    channels: string[];
    stage: Stage;
    stage_changed_at: string;
    due_at: string | null;
    publish_at: string | null;
    owner: { id: number; name: string } | null;
};

export type ContentDetails = ContentSummary & {
    brief: string | null;
    owner_id: number | null;
    published_url: string | null;
};

export const stages: Stage[] = [
    'idea',
    'preparing',
    'review',
    'approved',
    'scheduled',
    'published',
    'archived',
];

export const stageLabels: Record<Stage, string> = {
    idea: 'Idea',
    preparing: 'Preparing',
    review: 'Review',
    approved: 'Approved',
    scheduled: 'Scheduled',
    published: 'Published',
    archived: 'Archived',
};

export const formatLabels: Record<string, string> = {
    news: 'News',
    article: 'Article',
    press_release: 'Press release',
    social_post: 'Social media post',
    video: 'Video',
    photos: 'Photos',
    newsletter: 'Newsletter',
    podcast: 'Podcast',
    other: 'Other',
};

/** Must match ContentItemRequest::CHANNELS. */
export const channelLabels: Record<string, string> = {
    website: 'Website',
    newsletter: 'Newsletter',
    instagram: 'Instagram',
    facebook: 'Facebook',
    linkedin: 'LinkedIn',
    x: 'X',
    youtube: 'YouTube',
    tiktok: 'TikTok',
    press: 'Press',
    print: 'Print',
    screens: 'Screens',
};

/** Same rule as ContentStage::isApproval on the server. */
export function isApproval(from: Stage, to: Stage): boolean {
    return (
        ['idea', 'preparing', 'review'].includes(from) &&
        ['approved', 'scheduled', 'published'].includes(to)
    );
}

export function daysInStage(item: ContentSummary): number {
    return Math.floor(
        (Date.now() - new Date(item.stage_changed_at).getTime()) / 864e5,
    );
}

export const contentFieldLabels: Record<string, string> = {
    title: 'Title',
    brief: 'Brief',
    format: 'Format',
    channels: 'Channels',
    stage: 'Stage',
    owner_id: 'Owner',
    due_at: 'Deadline',
    publish_at: 'Publication date',
    published_url: 'Published URL',
};
