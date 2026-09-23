import {
    DndContext,
    PointerSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent } from '@dnd-kit/core';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type KanbanColumn = { id: string; label: string };

function Card({ id, children }: { id: string; children: ReactNode }) {
    const { attributes, listeners, setNodeRef, transform, isDragging } =
        useDraggable({ id });

    return (
        <div
            ref={setNodeRef}
            {...attributes}
            {...listeners}
            // Native dragging of links inside the card would cancel the pointer events dnd-kit relies on.
            onDragStartCapture={(event) => event.preventDefault()}
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
            {children}
        </div>
    );
}

function Column({
    column,
    count,
    children,
}: {
    column: KanbanColumn;
    count: number;
    children: ReactNode;
}) {
    const { setNodeRef, isOver } = useDroppable({ id: column.id });

    return (
        <section
            ref={setNodeRef}
            aria-label={column.label}
            className={cn(
                'flex w-64 shrink-0 flex-col gap-2 rounded-lg bg-muted/50 p-2',
                isOver && 'ring-2 ring-ring',
            )}
        >
            <h2 className="flex items-center justify-between px-1 text-sm font-medium">
                {column.label}
                <span className="text-xs text-muted-foreground">{count}</span>
            </h2>
            {children}
        </section>
    );
}

/**
 * Generic board: drag cards between columns. The parent owns the items and
 * decides what a move does (optimistic update, server call, refusal).
 */
export default function Kanban<T extends { id: string }>({
    columns,
    items,
    columnOf,
    renderCard,
    onMove,
}: {
    columns: KanbanColumn[];
    items: T[];
    columnOf: (item: T) => string;
    renderCard: (item: T) => ReactNode;
    onMove: (item: T, column: string) => void;
}) {
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    );

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        const item = items.find((candidate) => candidate.id === active.id);
        const column = over?.id as string | undefined;

        if (item && column && columnOf(item) !== column) {
            onMove(item, column);
        }
    };

    return (
        <DndContext sensors={sensors} onDragEnd={onDragEnd}>
            <div className="flex gap-3 overflow-x-auto pb-4">
                {columns.map((column) => {
                    const columnItems = items.filter(
                        (item) => columnOf(item) === column.id,
                    );

                    return (
                        <Column
                            key={column.id}
                            column={column}
                            count={columnItems.length}
                        >
                            {columnItems.map((item) => (
                                <Card key={item.id} id={item.id}>
                                    {renderCard(item)}
                                </Card>
                            ))}
                        </Column>
                    );
                })}
            </div>
        </DndContext>
    );
}
