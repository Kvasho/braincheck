import type { DragEndEvent } from '@dnd-kit/core';
import {
    closestCenter,
    DndContext,
    KeyboardSensor,
    PointerSensor,
    TouchSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ArrowDown, ArrowUp, GripVertical } from 'lucide-react';
import { useReducedMotion } from 'motion/react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { Item, PlayerProps } from '../shared/types';
import type { OrderingAnswer, OrderingPayload } from './types';

/**
 * Put the items in order: drag (pointer, touch), keyboard drag (focus the
 * handle, Space, arrows, Space) or the up / down buttons.
 */
export default function OrderingPlayer({
    payload,
    value,
    onChange,
    disabled,
}: PlayerProps<OrderingPayload, OrderingAnswer>) {
    const { t } = useTranslation();
    const byId = new Map(payload.items.map((item) => [item.id, item]));
    const order =
        value.order.length > 0
            ? value.order
            : payload.items.map((item) => item.id);
    const items = order
        .map((id) => byId.get(id))
        .filter((item): item is Item => item !== undefined);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
        useSensor(TouchSensor, {
            activationConstraint: { delay: 150, tolerance: 5 },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const move = (from: number, to: number) => {
        if (to < 0 || to >= order.length) {
            return;
        }

        onChange({ order: arrayMove(order, from, to) });
    };

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        if (over && active.id !== over.id) {
            move(
                order.indexOf(String(active.id)),
                order.indexOf(String(over.id)),
            );
        }
    };

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={onDragEnd}
        >
            <SortableContext
                items={order}
                strategy={verticalListSortingStrategy}
            >
                <ol
                    className="space-y-2"
                    aria-label={t('Put the items in the right order')}
                >
                    {items.map((item, index) => (
                        <OrderingItem
                            key={item.id}
                            item={item}
                            position={index}
                            count={items.length}
                            disabled={disabled}
                            onMove={move}
                        />
                    ))}
                </ol>
            </SortableContext>
        </DndContext>
    );
}

function OrderingItem({
    item,
    position,
    count,
    disabled,
    onMove,
}: {
    item: Item;
    position: number;
    count: number;
    disabled?: boolean;
    onMove: (from: number, to: number) => void;
}) {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion();
    const {
        setNodeRef,
        setActivatorNodeRef,
        attributes,
        listeners,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: item.id, disabled });

    return (
        <li
            ref={setNodeRef}
            style={{
                transform: CSS.Translate.toString(transform),
                transition: reduceMotion ? undefined : transition,
            }}
            className={cn(
                'flex items-center gap-2 rounded-lg border bg-card p-2',
                isDragging && 'relative z-10 opacity-80',
            )}
        >
            <button
                type="button"
                ref={setActivatorNodeRef}
                {...attributes}
                {...listeners}
                aria-label={t('Move :item', { item: item.text })}
                className="cursor-grab touch-none rounded p-1 text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing"
            >
                <GripVertical className="size-4" aria-hidden="true" />
            </button>
            <span className="tabular w-6 text-right text-sm text-muted-foreground">
                {position + 1}.
            </span>
            <span className="min-w-0 flex-1 break-words">{item.text}</span>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                disabled={disabled || position === 0}
                aria-label={t('Move up: :item', { item: item.text })}
                onClick={() => onMove(position, position - 1)}
            >
                <ArrowUp aria-hidden="true" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                disabled={disabled || position === count - 1}
                aria-label={t('Move down: :item', { item: item.text })}
                onClick={() => onMove(position, position + 1)}
            >
                <ArrowDown aria-hidden="true" />
            </Button>
        </li>
    );
}
