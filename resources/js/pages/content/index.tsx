import {
    DndContext,
    PointerSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent } from '@dnd-kit/core';
import { Form, Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { formatDate, localToday, useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import ContentFields, {
    contentFormTransform,
} from '@/modules/content/content-fields';
import type { ContentSummary, Stage } from '@/modules/content/types';
import {
    daysInStage,
    formatLabels,
    isApproval,
    stageLabels,
    stages,
} from '@/modules/content/types';
import type { Member } from '@/modules/tasks/types';
import { index, show, store, update } from '@/routes/content';

type Props = {
    items: ContentSummary[];
    showArchived: boolean;
    members: Member[];
    can: { approve: boolean };
};

function Card({ item }: { item: ContentSummary }) {
    const { t, locale } = useTranslation();
    const { attributes, listeners, setNodeRef, transform, isDragging } =
        useDraggable({ id: item.id });
    const stuck = item.stage === 'review' && daysInStage(item) >= 3;
    const today = localToday();
    const overdue =
        item.due_at !== null &&
        item.due_at < today &&
        !['published', 'archived'].includes(item.stage);

    return (
        <div
            ref={setNodeRef}
            {...attributes}
            {...listeners}
            style={
                transform
                    ? {
                          transform: `translate(${transform.x}px, ${transform.y}px)`,
                      }
                    : undefined
            }
            className={cn(
                'cursor-grab touch-none space-y-1 rounded-md border bg-background p-3 shadow-xs active:cursor-grabbing',
                isDragging && 'relative z-10 opacity-80 shadow-md',
            )}
        >
            {/* Native link dragging would cancel the pointer events dnd-kit relies on. */}
            <Link
                href={show(item.id)}
                draggable={false}
                className="block text-sm font-medium hover:underline"
            >
                {item.title}
            </Link>
            <p className="text-xs text-muted-foreground">
                {t(formatLabels[item.format] ?? item.format)}
                {item.owner && ` · ${item.owner.name}`}
            </p>
            <div className="flex flex-wrap gap-2 text-xs">
                {item.due_at && (
                    <span
                        className={
                            overdue
                                ? 'font-medium text-red-600'
                                : 'text-muted-foreground'
                        }
                    >
                        {formatDate(item.due_at, locale)}
                    </span>
                )}
                {stuck && (
                    <span className="font-medium text-amber-600">
                        {t(':days days in review', { days: daysInStage(item) })}
                    </span>
                )}
            </div>
        </div>
    );
}

function Column({ stage, items }: { stage: Stage; items: ContentSummary[] }) {
    const { t } = useTranslation();
    const { setNodeRef, isOver } = useDroppable({ id: stage });

    return (
        <section
            ref={setNodeRef}
            aria-label={t(stageLabels[stage])}
            className={cn(
                'flex w-64 shrink-0 flex-col gap-2 rounded-lg bg-muted/50 p-2',
                isOver && 'ring-2 ring-ring',
            )}
        >
            <h2 className="flex items-center justify-between px-1 text-sm font-medium">
                {t(stageLabels[stage])}
                <span className="text-xs text-muted-foreground">
                    {items.length}
                </span>
            </h2>
            {items.map((item) => (
                <Card key={item.id} item={item} />
            ))}
        </section>
    );
}

export default function ContentBoard({
    items: initialItems,
    showArchived,
    members,
    can,
}: Props) {
    const { t } = useTranslation();
    const [items, setItems] = useState(initialItems);
    const [creating, setCreating] = useState(false);
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    );

    useEffect(() => setItems(initialItems), [initialItems]);

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        const item = items.find((candidate) => candidate.id === active.id);
        const to = over?.id as Stage | undefined;

        if (!item || !to || item.stage === to) {
            return;
        }

        if (isApproval(item.stage, to) && !can.approve) {
            toast.error(t('Only editors and managers can approve content.'));

            return;
        }

        const previous = items;
        setItems(
            items.map((candidate) =>
                candidate.id === item.id
                    ? {
                          ...candidate,
                          stage: to,
                          stage_changed_at: new Date().toISOString(),
                      }
                    : candidate,
            ),
        );

        router.patch(
            update(item.id).url,
            { stage: to },
            {
                preserveScroll: true,
                onError: (errors) => {
                    setItems(previous);
                    toast.error(errors.stage ?? t('Something went wrong.'));
                },
            },
        );
    };

    const visibleStages = stages.filter(
        (stage) => showArchived || stage !== 'archived',
    );

    return (
        <>
            <Head title={t('Content')} />

            <div className="space-y-4 px-4 py-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title={t('Content')} />
                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="show-archived"
                                checked={showArchived}
                                onCheckedChange={(checked) =>
                                    router.get(
                                        index().url,
                                        checked ? { archived: 1 } : {},
                                        { preserveScroll: true },
                                    )
                                }
                            />
                            <Label htmlFor="show-archived">
                                {t('Show archived')}
                            </Label>
                        </div>
                        <Dialog open={creating} onOpenChange={setCreating}>
                            <DialogTrigger asChild>
                                <Button>{t('New content')}</Button>
                            </DialogTrigger>
                            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                                <DialogTitle>{t('New content')}</DialogTitle>
                                <Form
                                    {...store.form()}
                                    transform={contentFormTransform}
                                    onSuccess={() => setCreating(false)}
                                    className="space-y-6"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <ContentFields
                                                members={members}
                                                errors={errors}
                                            />
                                            <Button disabled={processing}>
                                                {t('Create content')}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                <DndContext sensors={sensors} onDragEnd={onDragEnd}>
                    <div className="flex gap-3 overflow-x-auto pb-4">
                        {visibleStages.map((stage) => (
                            <Column
                                key={stage}
                                stage={stage}
                                items={items.filter(
                                    (item) => item.stage === stage,
                                )}
                            />
                        ))}
                    </div>
                </DndContext>
            </div>
        </>
    );
}

ContentBoard.layout = {
    breadcrumbs: [{ title: 'Content', href: index() }],
};
