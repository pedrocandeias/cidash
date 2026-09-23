export type CampaignStatus = 'planning' | 'active' | 'finished' | 'cancelled';

export type CampaignSummary = {
    id: string;
    name: string;
    status: CampaignStatus;
    start_date: string | null;
    end_date: string | null;
    audiences: string[];
    channels: string[];
    responsibles: { id: number; name: string }[];
    items?: number;
};

export type CampaignDetails = CampaignSummary & {
    description: string | null;
    objectives: string | null;
};

export const statusLabels: Record<CampaignStatus, string> = {
    planning: 'Planning',
    active: 'Active',
    finished: 'Ended',
    cancelled: 'Dropped',
};

export const campaignFieldLabels: Record<string, string> = {
    name: 'Name',
    description: 'Description',
    objectives: 'Objectives',
    audiences: 'Audiences',
    start_date: 'Start',
    end_date: 'End',
    channels: 'Channels',
    status: 'Status',
};
