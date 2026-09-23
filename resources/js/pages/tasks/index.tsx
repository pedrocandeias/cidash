import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatDate, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import PriorityBadge from '@/modules/tasks/priority-badge';
import TaskFields, { taskFormTransform } from '@/modules/tasks/task-fields';
import type { Member, TaskSummary } from '@/modules/tasks/types';
import { isOverdue, statusLabels } from '@/modules/tasks/types';
import { index, show, store, update } from '@/routes/tasks';

type Filters = { view: 'mine' | 'team'; status: 'open' | 'done' | 'all' };

type Props = {
    tasks: TaskSummary[];
    filters: Filters;
    members: Member[];
};

function FilterLink({
    active,
    filters,
    children,
}: {
    active: boolean;
    filters: Filters;
    children: React.ReactNode;
}) {
    return (
        <Link
            href={index({ query: filters })}
            preserveScroll
            className={cn(
                'rounded-md px-3 py-1.5 text-sm',
                active
                    ? 'bg-muted font-medium'
                    : 'text-muted-foreground hover:text-foreground',
            )}
        >
            {children}
        </Link>
    );
}

export default function Tasks({ tasks, filters, members }: Props) {
    const { t, locale } = useTranslation();
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title={t('Tasks')} />

            <div className="space-y-6 px-4 py-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading title={t('Tasks')} />

                    <Dialog open={creating} onOpenChange={setCreating}>
                        <DialogTrigger asChild>
                            <Button>{t('New task')}</Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-2xl">
                            <DialogTitle>{t('New task')}</DialogTitle>
                            <Form
                                {...store.form()}
                                transform={taskFormTransform}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setCreating(false)}
                                className="space-y-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <TaskFields
                                            members={members}
                                            errors={errors}
                                        />
                                        <Button disabled={processing}>
                                            {t('Create task')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="flex flex-wrap items-center gap-6">
                    <nav className="flex gap-1" aria-label={t('Tasks')}>
                        <FilterLink
                            active={filters.view === 'mine'}
                            filters={{ ...filters, view: 'mine' }}
                        >
                            {t('Mine')}
                        </FilterLink>
                        <FilterLink
                            active={filters.view === 'team'}
                            filters={{ ...filters, view: 'team' }}
                        >
                            {t('Team')}
                        </FilterLink>
                    </nav>
                    <nav className="flex gap-1" aria-label={t('Status')}>
                        {(['open', 'done', 'all'] as const).map((status) => (
                            <FilterLink
                                key={status}
                                active={filters.status === status}
                                filters={{ ...filters, status }}
                            >
                                {t(
                                    {
                                        open: 'Open',
                                        done: 'Completed',
                                        all: 'All',
                                    }[status],
                                )}
                            </FilterLink>
                        ))}
                    </nav>
                </div>

                {tasks.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('No tasks here.')}
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {tasks.map((task) => (
                            <li
                                key={task.id}
                                className="flex flex-wrap items-center gap-3 px-4 py-3"
                            >
                                <Checkbox
                                    checked={task.status === 'done'}
                                    aria-label={t('Done')}
                                    onCheckedChange={(checked) =>
                                        router.patch(
                                            update(task.id).url,
                                            {
                                                status: checked
                                                    ? 'done'
                                                    : 'todo',
                                            },
                                            { preserveScroll: true },
                                        )
                                    }
                                />
                                <Link
                                    href={show(task.id)}
                                    className={cn(
                                        'min-w-48 flex-1 text-sm font-medium hover:underline',
                                        task.status === 'done' &&
                                            'text-muted-foreground line-through',
                                    )}
                                >
                                    {task.title}
                                </Link>
                                <PriorityBadge priority={task.priority} />
                                {!['todo', 'done'].includes(task.status) && (
                                    <span className="text-xs text-muted-foreground">
                                        {t(statusLabels[task.status])}
                                    </span>
                                )}
                                {filters.view === 'team' && (
                                    <span className="w-32 truncate text-sm text-muted-foreground">
                                        {task.assignee?.name ?? t('Unassigned')}
                                    </span>
                                )}
                                <span
                                    className={cn(
                                        'w-20 text-right text-sm',
                                        isOverdue(task)
                                            ? 'font-medium text-red-600'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {task.deadline &&
                                        formatDate(task.deadline, locale)}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

Tasks.layout = {
    breadcrumbs: [{ title: 'Tasks', href: index() }],
};
