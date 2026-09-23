export type EventType =
    | 'institutional'
    | 'campaign'
    | 'publication'
    | 'ephemeris'
    | 'deadline';
export type EventStatus = 'tentative' | 'confirmed' | 'cancelled' | 'done';

export type EventDetails = {
    id: string;
    title: string;
    description: string | null;
    type: EventType;
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

export const typeLabels: Record<EventType, string> = {
    institutional: 'Institutional',
    campaign: 'Campaign',
    publication: 'Publication',
    ephemeris: 'Ephemeris',
    deadline: 'Deadline',
};

/** Calendar colours per type (Tailwind palette, readable in light and dark). */
export const typeColors: Record<EventType, string> = {
    institutional: '#2563eb',
    campaign: '#7c3aed',
    publication: '#059669',
    ephemeris: '#d97706',
    deadline: '#dc2626',
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
