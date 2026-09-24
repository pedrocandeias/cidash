import { localToday } from '@/lib/i18n';

export type TaskStatus =
    | 'todo'
    | 'in_progress'
    | 'blocked'
    | 'done'
    | 'cancelled';
export type Priority = 'low' | 'normal' | 'high' | 'urgent';

export type TaskSummary = {
    id: string;
    title: string;
    status: TaskStatus;
    priority: Priority;
    deadline: string | null;
    assignees: { id: number; name: string }[];
};

export type Member = { id: number; name: string };

export const statusLabels: Record<TaskStatus, string> = {
    todo: 'To do',
    in_progress: 'In progress',
    blocked: 'Blocked',
    done: 'Done',
    cancelled: 'Cancelled',
};

export const priorityLabels: Record<Priority, string> = {
    low: 'Low',
    normal: 'Normal',
    high: 'High',
    urgent: 'Urgent',
};

/** Field names as shown in the history. */
export const taskFieldLabels: Record<string, string> = {
    title: 'Title',
    description: 'Description',
    assignees: 'People responsible',
    deadline: 'Deadline',
    priority: 'Priority',
    status: 'Status',
};

export function isOverdue(task: TaskSummary): boolean {
    const today = localToday();

    return (
        task.deadline !== null &&
        task.deadline < today &&
        task.status !== 'done' &&
        task.status !== 'cancelled'
    );
}
