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
import { useAutoAnimate } from '@formkit/auto-animate/react';
import { GripVertical, Plus, Trash2 } from 'lucide-react';
import { useReducedMotion } from 'motion/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { ITEM_TEXT_MAX } from './types';

export type EditorRow = { id?: string; text: string };

/** A React key for a row (rows have no stable id of their own until saved). */
export function nextKey(): string {
    return crypto.randomUUID();
}

/**
 * Editable list of rows (options, items, distractors): add, remove, edit and
 * reorder by drag & drop (pointer, touch) or keyboard (focus the handle, Space,
 * arrow keys, Space). Rows get stable React keys of their own, so nothing extra
 * is sent to the server.
 */
export function SortableRows<TRow extends EditorRow>({
    rows,
    onChange,
    min,
    max,
    newRow,
    label,
    addLabel,
    rowLabel,
    errorFor,
    renderControl,
    sortable = true,
}: {
    rows: TRow[];
    onChange: (rows: TRow[]) => void;
    min: number;
    max: number;
    newRow: () => TRow;
    /** Accessible name of the list. */
    label: string;
    addLabel: string;
    /** Accessible name of the text field of row `index`. */
    rowLabel: (index: number) => string;
    errorFor: (index: number) => string | undefined;
    /** E.g. the "correct" radio / checkbox. */
    renderControl?: (row: TRow, index: number) => ReactNode;
    sortable?: boolean;
}) {
    const { t } = useTranslation();
    const [keys, setKeys] = useState<string[]>(() => rows.map(() => nextKey()));
    const [animated] = useAutoAnimate<HTMLUListElement>();

    // Rows replaced from outside (e.g. after a server response): new keys.
    if (keys.length !== rows.length) {
        setKeys(rows.map(() => nextKey()));
    }

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
        useSensor(TouchSensor, {
            activationConstraint: { delay: 150, tolerance: 5 },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const update = (index: number, row: TRow) =>
        onChange(rows.map((current, i) => (i === index ? row : current)));

    const remove = (index: number) => {
        setKeys(keys.filter((_, i) => i !== index));
        onChange(rows.filter((_, i) => i !== index));
    };

    const add = () => {
        setKeys([...keys, nextKey()]);
        onChange([...rows, newRow()]);
    };

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        if (!over || active.id === over.id) {
            return;
        }

        const from = keys.indexOf(String(active.id));
        const to = keys.indexOf(String(over.id));

        if (from < 0 || to < 0) {
            return;
        }

        setKeys(arrayMove(keys, from, to));
        onChange(arrayMove(rows, from, to));
    };

    return (
        <div className="space-y-3">
            <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={onDragEnd}
            >
                <SortableContext
                    items={keys}
                    strategy={verticalListSortingStrategy}
                >
                    <ul ref={animated} className="space-y-2" aria-label={label}>
                        {rows.map((row, index) => (
                            <SortableRow
                                key={keys[index] ?? index}
                                id={keys[index] ?? String(index)}
                                sortable={sortable}
                                handleLabel={t('Reorder row :number', {
                                    number: index + 1,
                                })}
                            >
                                {renderControl?.(row, index)}
                                <div className="min-w-0 flex-1">
                                    <Input
                                        value={row.text}
                                        maxLength={ITEM_TEXT_MAX}
                                        aria-label={rowLabel(index)}
                                        aria-invalid={
                                            errorFor(index) ? true : undefined
                                        }
                                        onChange={(event) =>
                                            update(index, {
                                                ...row,
                                                text: event.target.value,
                                            })
                                        }
                                    />
                                    <InputError
                                        className="mt-1"
                                        message={errorFor(index)}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    disabled={rows.length <= min}
                                    aria-label={t('Remove row :number', {
                                        number: index + 1,
                                    })}
                                    onClick={() => remove(index)}
                                >
                                    <Trash2 aria-hidden="true" />
                                </Button>
                            </SortableRow>
                        ))}
                    </ul>
                </SortableContext>
            </DndContext>

            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={rows.length >= max}
                onClick={add}
            >
                <Plus aria-hidden="true" />
                {addLabel}
            </Button>
            <p className="tabular text-xs text-muted-foreground">
                {t(':count of :max', { count: rows.length, max })}
            </p>
        </div>
    );
}

function SortableRow({
    id,
    sortable,
    handleLabel,
    children,
}: {
    id: string;
    sortable: boolean;
    handleLabel: string;
    children: ReactNode;
}) {
    const reduceMotion = useReducedMotion();
    const {
        setNodeRef,
        setActivatorNodeRef,
        attributes,
        listeners,
        transform,
        transition,
        isDragging,
    } = useSortable({ id, disabled: !sortable });

    return (
        <li
            ref={setNodeRef}
            style={{
                transform: CSS.Translate.toString(transform),
                transition: reduceMotion ? undefined : transition,
            }}
            className={cn(
                'flex items-start gap-2 rounded-lg border bg-card p-2',
                isDragging && 'relative z-10 opacity-80',
            )}
        >
            {sortable && (
                <button
                    type="button"
                    ref={setActivatorNodeRef}
                    {...attributes}
                    {...listeners}
                    aria-label={handleLabel}
                    className="mt-1.5 cursor-grab touch-none rounded p-1 text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing"
                >
                    <GripVertical className="size-4" aria-hidden="true" />
                </button>
            )}
            {children}
        </li>
    );
}
