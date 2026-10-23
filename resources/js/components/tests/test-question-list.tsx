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
import { Archive, Eye, GripVertical, Trash2 } from 'lucide-react';
import { useReducedMotion } from 'motion/react';
import { StatusBadge } from '@/components/data/status-badge';
import InputError from '@/components/input-error';
import {
    DifficultyBadge,
    QuestionTypeBadge,
} from '@/components/questions/question-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { POINTS_PATTERN } from '@/lib/test-builder';
import { cn } from '@/lib/utils';
import teacher from '@/routes/teacher';
import type { Difficulty, Option, TestQuestionRow } from '@/types';

/**
 * The test's questions in order: drag & drop (pointer, touch, keyboard on the
 * handle), a points override per question, preview and remove. Question
 * ULIDs are unique in a test, so they are the sortable ids.
 */
export function TestQuestionList({
    questions,
    onChange,
    disabled,
    difficulties,
    errorFor,
}: {
    questions: TestQuestionRow[];
    onChange: (questions: TestQuestionRow[]) => void;
    /** Locked tests: the list can't change. */
    disabled: boolean;
    difficulties: Option<Difficulty>[];
    errorFor: (index: number) => string | undefined;
}) {
    const { t } = useTranslation();
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
        useSensor(TouchSensor, {
            activationConstraint: { delay: 150, tolerance: 5 },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        if (!over || active.id === over.id) {
            return;
        }

        const from = questions.findIndex((q) => q.ulid === active.id);
        const to = questions.findIndex((q) => q.ulid === over.id);

        if (from >= 0 && to >= 0) {
            onChange(arrayMove(questions, from, to));
        }
    };

    const update = (index: number, row: TestQuestionRow) =>
        onChange(questions.map((current, i) => (i === index ? row : current)));

    const difficultyLabel = (value: Difficulty) =>
        difficulties.find((option) => option.value === value)?.label ?? value;

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={onDragEnd}
        >
            <SortableContext
                items={questions.map((q) => q.ulid)}
                strategy={verticalListSortingStrategy}
            >
                <ol className="space-y-2" aria-label={t('Questions')}>
                    {questions.map((question, index) => (
                        <SortableQuestion
                            key={question.ulid}
                            id={question.ulid}
                            disabled={disabled}
                            handleLabel={t('Reorder question :number', {
                                number: index + 1,
                            })}
                        >
                            <span className="tabular mt-2 w-6 shrink-0 text-right text-sm text-muted-foreground">
                                {index + 1}.
                            </span>
                            <div className="min-w-0 flex-1 space-y-2">
                                <p className="line-clamp-2 break-words">
                                    {question.excerpt}
                                </p>
                                <div className="flex flex-wrap items-center gap-1">
                                    <QuestionTypeBadge type={question.type} />
                                    <DifficultyBadge
                                        difficulty={question.difficulty}
                                        label={difficultyLabel(
                                            question.difficulty,
                                        )}
                                    />
                                    {question.archived && (
                                        <StatusBadge
                                            tone="warning"
                                            icon={Archive}
                                            label={t('Archived')}
                                        />
                                    )}
                                </div>
                            </div>
                            <div className="w-24 shrink-0">
                                <Input
                                    inputMode="decimal"
                                    className="tabular"
                                    value={question.points_override ?? ''}
                                    placeholder={question.points}
                                    disabled={disabled}
                                    aria-label={t(
                                        'Points for question :number (default :points)',
                                        {
                                            number: index + 1,
                                            points: question.points,
                                        },
                                    )}
                                    aria-invalid={
                                        errorFor(index) ||
                                        (question.points_override &&
                                            !POINTS_PATTERN.test(
                                                question.points_override,
                                            ))
                                            ? true
                                            : undefined
                                    }
                                    onChange={(event) =>
                                        update(index, {
                                            ...question,
                                            points_override:
                                                event.target.value === ''
                                                    ? null
                                                    : event.target.value,
                                        })
                                    }
                                />
                                <InputError
                                    className="mt-1"
                                    message={errorFor(index)}
                                />
                            </div>
                            <Button asChild variant="ghost" size="icon">
                                <a
                                    href={teacher.questions.preview.url(
                                        question.ulid,
                                    )}
                                    target="_blank"
                                    rel="noopener"
                                    aria-label={t('Preview question :number', {
                                        number: index + 1,
                                    })}
                                >
                                    <Eye aria-hidden="true" />
                                </a>
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                disabled={disabled}
                                aria-label={t('Remove question :number', {
                                    number: index + 1,
                                })}
                                onClick={() =>
                                    onChange(
                                        questions.filter((_, i) => i !== index),
                                    )
                                }
                            >
                                <Trash2 aria-hidden="true" />
                            </Button>
                        </SortableQuestion>
                    ))}
                </ol>
            </SortableContext>
        </DndContext>
    );
}

function SortableQuestion({
    id,
    disabled,
    handleLabel,
    children,
}: {
    id: string;
    disabled: boolean;
    handleLabel: string;
    children: React.ReactNode;
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
    } = useSortable({ id, disabled });

    return (
        <li
            ref={setNodeRef}
            style={{
                transform: CSS.Translate.toString(transform),
                transition: reduceMotion ? undefined : transition,
            }}
            className={cn(
                'flex items-start gap-2 rounded-lg border bg-card p-3',
                isDragging && 'relative z-10 opacity-80',
            )}
        >
            {!disabled && (
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
