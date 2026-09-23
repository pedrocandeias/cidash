import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useTranslation } from '@/lib/i18n';
import TaskFields, { taskFormTransform } from '@/modules/tasks/task-fields';
import type { Member } from '@/modules/tasks/types';
import { store } from '@/routes/tasks';

/**
 * "Create task from this record": the task keeps the record as its origin.
 */
export default function CreateTaskButton({
    sourceId,
    sourceTitle,
    members,
}: {
    sourceId: string;
    sourceTitle: string;
    members: Member[];
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    {t('Create task')}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogTitle>{t('New task')}</DialogTitle>
                <DialogDescription>
                    {t('From: :title', { title: sourceTitle })}
                </DialogDescription>
                <Form
                    {...store.form()}
                    transform={(data) => ({
                        ...taskFormTransform(data),
                        source_id: sourceId,
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <TaskFields
                                members={members}
                                errors={errors}
                                idPrefix="task-"
                            />
                            <Button disabled={processing}>
                                {t('Create task')}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
