export type EventStatus = 'tentative' | 'confirmed' | 'cancelled' | 'done';

export type EventDetails = {
    id: string;
    title: string;
    description: string | null;
    type: string;
    start_at: string;
    end_at: string | null;
    all_day: boolean;
    location: string | null;
    organizer: string | null;
    responsible_user_id: number | null;
    priority: 'low' | 'normal' | 'high' | 'urgent';
    status: EventStatus;
    notes: string | null;
    tags: string[];
};

export const statusLabels: Record<EventStatus, string> = {
    tentative: 'Tentative',
    confirmed: 'Confirmed',
    cancelled: 'Called off',
    done: 'Held',
};

export const eventFieldLabels: Record<string, string> = {
    title: 'Title',
    description: 'Description',
    type: 'Type',
    start_at: 'Start',
    end_at: 'End',
    all_day: 'All day',
    location: 'Location',
    organizer: 'Organizer',
    responsible_user_id: 'Responsible',
    priority: 'Priority',
    status: 'Status',
    notes: 'Notes',
};
