export type PressStatus =
    | 'received'
    | 'in_progress'
    | 'awaiting_input'
    | 'answered'
    | 'declined'
    | 'closed';

export type PressSummary = {
    id: string;
    subject: string;
    journalist: string | null;
    media_outlet: string | null;
    received_at: string;
    deadline: string | null;
    status: PressStatus;
    answered_at: string | null;
    responsible: string | null;
};

export type PressDetails = PressSummary & {
    request: string | null;
    contact: string | null;
    response_notes: string | null;
    responsible_user_id: number | null;
};

export type Known = { journalists: string[]; outlets: string[] };

export const statusLabels: Record<PressStatus, string> = {
    received: 'Received',
    in_progress: 'In progress',
    awaiting_input: 'Awaiting input',
    answered: 'Answered',
    declined: 'Declined',
    closed: 'Closed',
};

const openStatuses: PressStatus[] = [
    'received',
    'in_progress',
    'awaiting_input',
];

/** Traffic light for open requests: red if overdue or < 24 h, amber if < 72 h. */
export function urgency(request: PressSummary): 'red' | 'amber' | null {
    if (!request.deadline || !openStatuses.includes(request.status)) {
        return null;
    }

    const hours = (new Date(request.deadline).getTime() - Date.now()) / 36e5;

    return hours < 24 ? 'red' : hours < 72 ? 'amber' : null;
}

export const pressFieldLabels: Record<string, string> = {
    subject: 'Subject',
    request: 'Request',
    journalist: 'Journalist',
    media_outlet: 'Media outlet',
    contact: 'Contact',
    received_at: 'Received',
    deadline: 'Deadline',
    responsible_user_id: 'Responsible',
    status: 'Status',
    response_notes: 'Response notes',
};
