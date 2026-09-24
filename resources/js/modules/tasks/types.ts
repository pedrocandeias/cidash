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
    type: string | null;
    status: TaskStatus;
    priority: Priority;
    start_date: string | null;
    /** Date and time (ISO 8601). */
    deadline: string | null;
    assignees: { id: number; name: string }[];
    co_assignees: { id: number; name: string }[];
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
    type: 'Task type',
    description: 'Description',
    assignees: 'People responsible',
    co_assignees: 'Co-responsible',
    start_date: 'Start date',
    deadline: 'Deadline',
    priority: 'Priority',
    status: 'Status',
};

export function isOverdue(task: TaskSummary): boolean {
    return (
        task.deadline !== null &&
        new Date(task.deadline) < new Date() &&
        task.status !== 'done' &&
        task.status !== 'cancelled'
    );
}
