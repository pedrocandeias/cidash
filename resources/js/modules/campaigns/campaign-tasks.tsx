import { Form, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import AssigneesField from '@/components/core/assignees-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime, useTranslation } from '@/lib/i18n';
import { useOptions } from '@/lib/options';
import { cn } from '@/lib/utils';
import type { RecordSummary } from '@/components/core/relations-panel';
import type { Member, TaskStatus } from '@/modules/tasks/types';
import { statusLabels } from '@/modules/tasks/types';
import { store } from '@/routes/campaigns/tasks';
import { show, update } from '@/routes/tasks';

export type CampaignTask = {
    id: string;
    title: string;
    type: string | null;
    status: TaskStatus;
    deadline: string | null;
    assignees: string | null;
    about: string | null;
};

const NONE = 'none';

function NewTaskDialog({
    campaignId,
    parts,
    members,
}: {
    campaignId: string;
    parts: { record: RecordSummary }[];
    members: Member[];
}) {
    const { t } = useTranslation();
    const formats = useOptions('content_format');
    const taskTypes = useOptions('task_type');
    const [open, setOpen] = useState(false);
    const [withContent, setWithContent] = useState(false);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                setWithContent(false);
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Plus />
                    {t('New task')}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogTitle>{t('New task in this campaign')}</DialogTitle>
                <Form
                    {...store.form(campaignId)}
                    transform={(data) => ({
                        ...data,
                        assignees: data.assignees ?? [],
                        type: data.type === NONE ? null : data.type,
                        about: data.about === NONE ? null : data.about,
                        create_content: withContent,
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="campaign-task-title">
                                    {t('Title')}
                                </Label>
                                <Input
                                    id="campaign-task-title"
                                    name="title"
                                    required
                                    placeholder={t(
                                        withContent
                                            ? 'e.g. Video of the open day'
                                            : 'e.g. Book the photographer',
                                    )}
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="campaign-task-type">
                                    {t('Task type')}
                                </Label>
                                <Select name="type" defaultValue={NONE}>
                                    <SelectTrigger id="campaign-task-type">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE}>
                                            {t('No type')}
                                        </SelectItem>
                                        {taskTypes.active.map((option) => (
                                            <SelectItem
                                                key={option.key}
                                                value={option.key}
                                            >
                                                {t(option.label)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={withContent}
                                    onCheckedChange={(checked) =>
                                        setWithContent(checked === true)
                                    }
                                />
                                {t(
                                    'The task is to create content: create it too, in this campaign',
                                )}
                            </label>

                            {withContent ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="campaign-task-format">
                                        {t('Format')}
                                    </Label>
                                    <Select
                                        name="format"
                                        defaultValue={formats.active[0]?.key}
                                    >
                                        <SelectTrigger id="campaign-task-format">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {formats.active.map((option) => (
                                                <SelectItem
                                                    key={option.key}
                                                    value={option.key}
                                                >
                                                    {t(option.label)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.format} />
                                </div>
                            ) : (
                                parts.length > 0 && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="campaign-task-about">
                                            {t('About')}
                                        </Label>
                                        <Select
                                            name="about"
                                            defaultValue={NONE}
                                        >
                                            <SelectTrigger id="campaign-task-about">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NONE}>
                                                    {t('The campaign')}
                                                </SelectItem>
                                                {parts.map((part) => (
                                                    <SelectItem
                                                        key={part.record.id}
                                                        value={part.record.id}
                                                    >
                                                        {t(part.record.label)}
                                                        {' · '}
                                                        {part.record.title}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )
                            )}

                            <div className="grid gap-4">
                                <AssigneesField
                                    id="campaign-task-assignees"
                                    members={members}
                                    error={errors.assignees}
                                />
                                <div className="grid gap-2">
                                    <Label htmlFor="campaign-task-deadline">
                                        {t('Deadline')}
                                    </Label>
                                    <Input
                                        id="campaign-task-deadline"
                                        name="deadline"
                                        type="datetime-local"
                                    />
                                    <InputError message={errors.deadline} />
                                </div>
                            </div>

                            <Button
                                disabled={processing}
                                className="justify-self-start"
                            >
                                {t('Create task')}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * The campaign's to-dos: its own tasks and those of its events and content.
 */
export default function CampaignTasks({
    campaignId,
    tasks,
    parts,
    members,
}: {
    campaignId: string;
    tasks: CampaignTask[];
    parts: { record: RecordSummary }[];
    members: Member[];
}) {
    const { t, locale } = useTranslation();
    const taskTypes = useOptions('task_type');
    const counted = tasks.filter((task) => task.status !== 'cancelled');
    const done = counted.filter((task) => task.status === 'done').length;

    return (
        <section className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-base font-medium">{t('Campaign tasks')}</h2>
                <NewTaskDialog
                    campaignId={campaignId}
                    parts={parts}
                    members={members}
                />
            </div>

            {counted.length > 0 && (
                <div className="space-y-1">
                    <div
                        className="h-2 overflow-hidden rounded-xs bg-muted"
                        role="progressbar"
                        aria-valuemin={0}
                        aria-valuemax={counted.length}
                        aria-valuenow={done}
                        aria-label={t('Campaign tasks')}
                    >
                        <div
                            className="h-full bg-primary"
                            style={{
                                width: `${(done / counted.length) * 100}%`,
                            }}
                        />
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {t(':done of :total done', {
                            done,
                            total: counted.length,
                        })}
                    </p>
                </div>
            )}

            {tasks.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t(
                        'No tasks yet. Tasks of its events and content also appear here.',
                    )}
                </p>
            ) : (
                <ul className="divide-y rounded-lg border">
                    {tasks.map((task) => {
                        const closed = ['done', 'cancelled'].includes(
                            task.status,
                        );

                        return (
                            <li
                                key={task.id}
                                className="flex flex-wrap items-center gap-3 px-4 py-2 text-sm"
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
                                <div className="min-w-48 flex-1">
                                    <Link
                                        href={show(task.id)}
                                        className={cn(
                                            'font-medium hover:underline',
                                            closed &&
                                                'text-muted-foreground line-through',
                                        )}
                                    >
                                        {task.title}
                                    </Link>
                                    {(task.type || task.about) && (
                                        <span className="block text-xs text-muted-foreground">
                                            {[
                                                task.type &&
                                                    t(
                                                        taskTypes.label(
                                                            task.type,
                                                        ),
                                                    ),
                                                task.about,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                    )}
                                </div>
                                {!['todo', 'done'].includes(task.status) && (
                                    <span className="text-xs text-muted-foreground">
                                        {t(statusLabels[task.status])}
                                    </span>
                                )}
                                <span className="w-32 truncate text-muted-foreground">
                                    {task.assignees ?? t('Unassigned')}
                                </span>
                                <span
                                    className={cn(
                                        'w-32 text-right',
                                        task.deadline &&
                                            new Date(task.deadline) <
                                                new Date() &&
                                            !closed
                                            ? 'font-medium text-critical'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {task.deadline &&
                                        formatDateTime(task.deadline, locale)}
                                </span>
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}
