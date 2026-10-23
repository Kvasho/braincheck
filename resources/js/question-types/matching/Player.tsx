import type { DragEndEvent } from '@dnd-kit/core';
import {
    DndContext,
    KeyboardSensor,
    PointerSensor,
    TouchSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { CSS } from '@dnd-kit/utilities';
import { X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { Item, PlayerProps } from '../shared/types';
import type { MatchingAnswer, MatchingPayload } from './types';

const NONE = '__none__';

/**
 * Match each left entry with one right entry. Two equivalent modes:
 * drag & drop (pointer, touch, keyboard: focus a chip, Space, arrows, Space)
 * and a select per left entry (accessible fallback).
 */
export default function MatchingPlayer({
    payload,
    value,
    onChange,
    disabled,
    idPrefix,
}: PlayerProps<MatchingPayload, MatchingAnswer>) {
    const { t } = useTranslation();
    const [mode, setMode] = useState<'drag' | 'select'>('drag');
    const rightFor = new Map(
        value.pairs.map((pair) => [pair.left, pair.right]),
    );
    const used = new Set(value.pairs.map((pair) => pair.right));

    const assign = (left: string, right: string | null) => {
        const others = value.pairs.filter(
            (pair) => pair.left !== left && pair.right !== right,
        );

        onChange({
            pairs: right === null ? others : [...others, { left, right }],
        });
    };

    return (
        <div className="space-y-4">
            <ToggleGroup
                type="single"
                value={mode}
                onValueChange={(next) =>
                    next && setMode(next as 'drag' | 'select')
                }
                aria-label={t('How to match')}
                className="justify-start"
            >
                <ToggleGroupItem value="drag">
                    {t('Drag and drop')}
                </ToggleGroupItem>
                <ToggleGroupItem value="select">
                    {t('Choose from lists')}
                </ToggleGroupItem>
            </ToggleGroup>

            {mode === 'select' ? (
                <ul className="space-y-3">
                    {payload.left.map((left) => (
                        <li
                            key={left.id}
                            className="grid gap-1.5 sm:grid-cols-2 sm:items-center sm:gap-4"
                        >
                            <Label
                                htmlFor={`${idPrefix}-${left.id}`}
                                className="break-words"
                            >
                                {left.text}
                            </Label>
                            <Select
                                value={rightFor.get(left.id) ?? NONE}
                                disabled={disabled}
                                onValueChange={(next) =>
                                    assign(left.id, next === NONE ? null : next)
                                }
                            >
                                <SelectTrigger id={`${idPrefix}-${left.id}`}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>
                                        {t('Not matched')}
                                    </SelectItem>
                                    {payload.right.map((right) => (
                                        <SelectItem
                                            key={right.id}
                                            value={right.id}
                                            disabled={
                                                used.has(right.id) &&
                                                rightFor.get(left.id) !==
                                                    right.id
                                            }
                                        >
                                            {right.text}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </li>
                    ))}
                </ul>
            ) : (
                <DragMatching
                    payload={payload}
                    rightFor={rightFor}
                    used={used}
                    disabled={disabled}
                    onAssign={assign}
                />
            )}
        </div>
    );
}

function DragMatching({
    payload,
    rightFor,
    used,
    disabled,
    onAssign,
}: {
    payload: MatchingPayload;
    rightFor: Map<string, string>;
    used: Set<string>;
    disabled?: boolean;
    onAssign: (left: string, right: string | null) => void;
}) {
    const { t } = useTranslation();
    const byId = new Map(payload.right.map((item) => [item.id, item]));
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
        useSensor(TouchSensor, {
            activationConstraint: { delay: 150, tolerance: 5 },
        }),
        useSensor(KeyboardSensor),
    );

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        if (over) {
            onAssign(String(over.id), String(active.id));
        }
    };

    return (
        <DndContext sensors={sensors} onDragEnd={onDragEnd}>
            <div className="grid gap-4 md:grid-cols-2">
                <ul className="space-y-2" aria-label={t('Entries to match')}>
                    {payload.left.map((left) => {
                        const matched = rightFor.get(left.id);

                        return (
                            <DropSlot
                                key={left.id}
                                id={left.id}
                                label={left.text}
                            >
                                {matched ? (
                                    <span className="flex items-center gap-2">
                                        <span className="rounded-md bg-primary/10 px-2 py-1">
                                            {byId.get(matched)?.text}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            disabled={disabled}
                                            aria-label={t(
                                                'Remove the match for :item',
                                                { item: left.text },
                                            )}
                                            onClick={() =>
                                                onAssign(left.id, null)
                                            }
                                        >
                                            <X aria-hidden="true" />
                                        </Button>
                                    </span>
                                ) : (
                                    <span className="text-sm text-muted-foreground">
                                        {t('Drop an answer here')}
                                    </span>
                                )}
                            </DropSlot>
                        );
                    })}
                </ul>
                <ul
                    className="flex flex-wrap content-start gap-2"
                    aria-label={t('Answers')}
                >
                    {payload.right
                        .filter((right) => !used.has(right.id))
                        .map((right) => (
                            <DragChip
                                key={right.id}
                                item={right}
                                disabled={disabled}
                            />
                        ))}
                </ul>
            </div>
        </DndContext>
    );
}

function DropSlot({
    id,
    label,
    children,
}: {
    id: string;
    label: string;
    children: React.ReactNode;
}) {
    const { setNodeRef, isOver } = useDroppable({ id });

    return (
        <li
            ref={setNodeRef}
            className={cn(
                'grid gap-2 rounded-lg border p-3 sm:grid-cols-2 sm:items-center',
                isOver && 'border-primary bg-primary/5',
            )}
        >
            <span className="font-medium break-words">{label}</span>
            {children}
        </li>
    );
}

function DragChip({ item, disabled }: { item: Item; disabled?: boolean }) {
    const { t } = useTranslation();
    const { setNodeRef, attributes, listeners, transform, isDragging } =
        useDraggable({ id: item.id, disabled });

    return (
        <li>
            <button
                type="button"
                ref={setNodeRef}
                {...attributes}
                {...listeners}
                aria-label={t('Drag :item', { item: item.text })}
                style={{ transform: CSS.Translate.toString(transform) }}
                className={cn(
                    'cursor-grab touch-none rounded-md border bg-card px-3 py-2 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing',
                    isDragging && 'relative z-10 shadow-lg',
                )}
            >
                {item.text}
            </button>
        </li>
    );
}
