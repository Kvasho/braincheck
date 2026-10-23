import { Head, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Clock,
    CloudOff,
    Flag,
    Loader,
    Check,
    TriangleAlert,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/data/confirm-dialog';
import { QuestionTypeBadge } from '@/components/questions/question-fields';
import { RichText } from '@/components/rich-text';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useExamAutosave } from '@/hooks/use-exam-autosave';
import { useExamEvents } from '@/hooks/use-exam-events';
import { formatDuration, useServerClock } from '@/hooks/use-server-clock';
import { useSingleTab } from '@/hooks/use-single-tab';
import { useTranslation } from '@/hooks/use-translation';
import ExamLayout from '@/layouts/exam-layout';
import type { SaveStatus } from '@/lib/exam-autosave';
import { cn } from '@/lib/utils';
import { QuestionTypePlayer } from '@/question-types/components';
import { questionTypeModule } from '@/question-types/registry';
import student from '@/routes/student';
import type { PlayerAttempt } from '@/types';

const WARN_AT_SECONDS = [300, 60] as const;

/** Keyboard shortcuts stay out of the way while typing an answer. */
function isTyping(target: EventTarget | null): boolean {
    return (
        target instanceof HTMLElement &&
        (target.isContentEditable ||
            ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))
    );
}

/**
 * The exam player. The server is the authority on time, questions and
 * answers: the timer only displays the server's deadline (corrected by the
 * clock offset at load), every answer is autosaved and validated by the
 * server, and submitting (or the deadline) is decided there.
 */
export default function AttemptPlayer({ attempt }: { attempt: PlayerAttempt }) {
    const { t } = useTranslation();
    const questions = attempt.questions;
    const [index, setIndex] = useState(0);
    const [answers, setAnswers] = useState<Record<number, unknown>>(() =>
        Object.fromEntries(
            questions.map((question) => {
                const saved = attempt.saved.find(
                    (item) => item.position === question.position,
                );

                return [
                    question.position,
                    saved?.value ??
                        questionTypeModule(question.type).initialAnswer(
                            question.player,
                        ),
                ];
            }),
        ),
    );
    const [answered, setAnswered] = useState<Set<number>>(
        () =>
            new Set(
                attempt.saved
                    .filter((item) => item.answered)
                    .map((item) => item.position),
            ),
    );
    const [flagged, setFlagged] = useState<Set<number>>(
        () =>
            new Set(
                attempt.saved
                    .filter((item) => item.flagged)
                    .map((item) => item.position),
            ),
    );
    const [confirming, setConfirming] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [finished, setFinished] = useState(false);
    const resultUrl = student.attempts.result.url(attempt.ulid);

    const onExpired = useCallback(
        (url: string | null) => {
            setFinished(true);
            toast.info(
                t('Time is up. Your answers up to the deadline were saved.'),
            );
            router.visit(url ?? resultUrl);
        },
        [t, resultUrl],
    );

    const { autosave, status, unsaved } = useExamAutosave(
        attempt.ulid,
        onExpired,
    );
    const positionRef = useRef(questions[0]?.position ?? 1);
    const currentPosition = useCallback(() => positionRef.current, []);
    const events = useExamEvents(attempt.ulid, currentPosition);
    const [duplicateTab, dismissDuplicate] = useSingleTab(attempt.ulid);

    const current = questions[index];

    useEffect(() => {
        positionRef.current = current?.position ?? 1;
    }, [current]);

    const goTo = useCallback(
        (next: number) => {
            if (next < 0 || next >= questions.length) {
                return;
            }

            if (current) {
                autosave.saveNow(current.position);
            }

            setIndex(next);
        },
        [autosave, current, questions.length],
    );

    const toggleFlag = useCallback(() => {
        if (!current) {
            return;
        }

        const next = !flagged.has(current.position);
        setFlagged((set) => {
            const copy = new Set(set);

            if (next) {
                copy.add(current.position);
            } else {
                copy.delete(current.position);
            }

            return copy;
        });
        autosave.queue(
            current.position,
            { hasAnswer: false, flagged: next },
            true,
        );
    }, [autosave, current, flagged]);

    const changeAnswer = (position: number, value: unknown) => {
        setAnswers((all) => ({ ...all, [position]: value }));
        setAnswered((set) => new Set(set).add(position));
        autosave.queue(position, { hasAnswer: true, answer: value });
    };

    const submit = useCallback(async () => {
        if (submitting || finished) {
            return;
        }

        setSubmitting(true);
        await autosave.flush();
        await events.flush();
        setFinished(true);
        router.post(
            student.attempts.submit.url(attempt.ulid),
            {},
            {
                onFinish: () => setSubmitting(false),
            },
        );
    }, [attempt.ulid, autosave, events, finished, submitting]);

    // Keyboard: ← → move, F flags (not while typing).
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (
                isTyping(event.target) ||
                event.metaKey ||
                event.ctrlKey ||
                event.altKey
            ) {
                return;
            }

            if (event.key === 'ArrowLeft') {
                goTo(index - 1);
            } else if (event.key === 'ArrowRight') {
                goTo(index + 1);
            } else if (event.key === 'f' || event.key === 'F') {
                toggleFlag();
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [goTo, index, toggleFlag]);

    // Unsaved changes: warn before leaving the page.
    useEffect(() => {
        if (unsaved === 0 || finished) {
            return;
        }

        const warn = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, [unsaved, finished]);

    const unansweredCount = questions.filter(
        (q) => !answered.has(q.position),
    ).length;

    return (
        <ExamLayout
            header={
                <>
                    <SaveIndicator status={status} />
                    {attempt.deadline_at && (
                        <ExamTimer
                            deadline={attempt.deadline_at}
                            serverNow={attempt.server_now}
                            onElapsed={() => void submit()}
                        />
                    )}
                    <Button
                        size="sm"
                        onClick={() => setConfirming(true)}
                        disabled={submitting || finished}
                    >
                        {t('Submit')}
                    </Button>
                </>
            }
        >
            <Head title={attempt.test.title} />

            <div className="space-y-6">
                <h1 className="text-xl font-semibold">{attempt.test.title}</h1>

                <nav aria-label={t('Questions')}>
                    <ol className="flex flex-wrap gap-2">
                        {questions.map((question, i) => {
                            const isCurrent = i === index;
                            const isFlagged = flagged.has(question.position);
                            const isAnswered = answered.has(question.position);

                            return (
                                <li key={question.position}>
                                    <button
                                        type="button"
                                        onClick={() => goTo(i)}
                                        aria-current={
                                            isCurrent ? 'step' : undefined
                                        }
                                        aria-label={[
                                            t('Question :number', {
                                                number: question.position,
                                            }),
                                            isAnswered
                                                ? t('answered')
                                                : t('not answered'),
                                            isFlagged ? t('flagged') : '',
                                        ]
                                            .filter(Boolean)
                                            .join(', ')}
                                        className={cn(
                                            'tabular relative flex size-10 items-center justify-center rounded-md border text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            isAnswered
                                                ? 'border-primary bg-background font-semibold text-primary'
                                                : 'bg-background text-muted-foreground',
                                            isCurrent && 'ring-2 ring-primary',
                                        )}
                                    >
                                        {question.position}
                                        {isFlagged && (
                                            <Flag
                                                className="absolute -top-1.5 -right-1.5 size-3.5 fill-warning text-warning"
                                                aria-hidden="true"
                                            />
                                        )}
                                    </button>
                                </li>
                            );
                        })}
                    </ol>
                    <p className="mt-2 text-xs text-muted-foreground">
                        {t('Keys: ← → to move, F to flag.')}
                    </p>
                </nav>

                {current && (
                    <Card>
                        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
                            <CardTitle className="tabular text-base">
                                {t('Question :number of :count', {
                                    number: current.position,
                                    count: questions.length,
                                })}
                            </CardTitle>
                            <div className="flex items-center gap-2">
                                <QuestionTypeBadge type={current.type} />
                                <span className="tabular text-sm text-muted-foreground">
                                    {t(':points points', {
                                        points: current.points,
                                    })}
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <RichText doc={current.body} className="text-lg" />
                            {current.image_url && (
                                <img
                                    src={current.image_url}
                                    alt={t('Question image')}
                                    className="max-h-96 rounded-lg border object-contain"
                                />
                            )}
                            <QuestionTypePlayer
                                key={current.position}
                                type={current.type}
                                payload={current.player}
                                value={answers[current.position]}
                                disabled={finished}
                                onChange={(value) =>
                                    changeAnswer(current.position, value)
                                }
                                idPrefix={`q${current.position}`}
                            />

                            <div className="flex flex-wrap items-center justify-between gap-2 border-t pt-4">
                                <Button
                                    variant={
                                        flagged.has(current.position)
                                            ? 'secondary'
                                            : 'ghost'
                                    }
                                    onClick={toggleFlag}
                                    aria-pressed={flagged.has(current.position)}
                                >
                                    <Flag aria-hidden="true" />
                                    {flagged.has(current.position)
                                        ? t('Flagged for review')
                                        : t('Flag for review')}
                                </Button>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={() => goTo(index - 1)}
                                        disabled={index === 0}
                                    >
                                        <ChevronLeft aria-hidden="true" />
                                        {t('Previous')}
                                    </Button>
                                    {index < questions.length - 1 ? (
                                        <Button onClick={() => goTo(index + 1)}>
                                            {t('Next')}
                                            <ChevronRight aria-hidden="true" />
                                        </Button>
                                    ) : (
                                        <Button
                                            onClick={() => setConfirming(true)}
                                            disabled={submitting || finished}
                                        >
                                            {t('Submit')}
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t('Submit your answers?')}
                description={
                    <span className="space-y-1">
                        <span className="tabular block">
                            {t(':count unanswered', { count: unansweredCount })}
                        </span>
                        <span className="tabular block">
                            {t(':count flagged for review', {
                                count: flagged.size,
                            })}
                        </span>
                        <span className="block">
                            {t(
                                'You cannot change your answers after submitting.',
                            )}
                        </span>
                    </span>
                }
                confirmLabel={t('Submit')}
                processing={submitting}
                onConfirm={() => void submit()}
            />

            {duplicateTab && (
                <div
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="duplicate-tab-title"
                    className="fixed inset-0 z-50 flex items-center justify-center bg-overlay p-4"
                >
                    <Card className="max-w-md">
                        <CardHeader>
                            <CardTitle
                                id="duplicate-tab-title"
                                className="flex items-center gap-2"
                            >
                                <TriangleAlert
                                    className="size-5 text-warning"
                                    aria-hidden="true"
                                />
                                {t('This exam is already open in another tab')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'Answers from two tabs overwrite each other. Close the other tab and continue here.',
                                )}
                            </p>
                            <Button onClick={dismissDuplicate}>
                                {t('Continue in this tab')}
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            )}
        </ExamLayout>
    );
}

function SaveIndicator({ status }: { status: SaveStatus }) {
    const { t } = useTranslation();

    const content = {
        saved: {
            icon: Check,
            label: t('Saved'),
            className: 'text-muted-foreground',
        },
        saving: {
            icon: Loader,
            label: t('Saving…'),
            className: 'text-muted-foreground',
        },
        offline: {
            icon: CloudOff,
            label: t('Offline – retrying'),
            className: 'text-warning',
        },
        rejected: {
            icon: TriangleAlert,
            label: t('An answer was not accepted'),
            className: 'text-destructive',
        },
    }[status];
    const Icon = content.icon;

    return (
        <span
            className={cn(
                'hidden items-center gap-1 text-xs sm:inline-flex',
                content.className,
            )}
            role="status"
            aria-live="polite"
        >
            <Icon
                className={cn(
                    'size-3.5',
                    status === 'saving' && 'motion-safe:animate-spin',
                )}
                aria-hidden="true"
            />
            {content.label}
        </span>
    );
}

/**
 * Remaining time on the server's clock. Warns at 5 and 1 minute(s); at 0 it
 * submits (the server decides anyway: late answers are refused).
 */
function ExamTimer({
    deadline,
    serverNow,
    onElapsed,
}: {
    deadline: string;
    serverNow: string;
    onElapsed: () => void;
}) {
    const { t } = useTranslation();
    const now = useServerClock(serverNow);
    const remaining = Math.max(
        0,
        Math.floor((new Date(deadline).getTime() - now) / 1000),
    );
    const [warned, setWarned] = useState<number[]>(() =>
        WARN_AT_SECONDS.filter((threshold) => remaining <= threshold),
    );
    const [elapsed, setElapsed] = useState(remaining === 0);

    // Adjusting state during render (not in an effect) when a threshold is crossed.
    const crossed = WARN_AT_SECONDS.find(
        (threshold) => remaining <= threshold && !warned.includes(threshold),
    );

    if (crossed !== undefined) {
        setWarned([...warned, crossed]);
    }

    if (remaining === 0 && !elapsed) {
        setElapsed(true);
    }

    const latestWarning = warned.length === 0 ? null : Math.min(...warned);

    useEffect(() => {
        if (latestWarning === 300) {
            toast.warning(t('5 minutes left.'));
        } else if (latestWarning === 60) {
            toast.warning(t('1 minute left.'));
        }
    }, [latestWarning, t]);

    const onElapsedRef = useRef(onElapsed);

    useEffect(() => {
        onElapsedRef.current = onElapsed;
    }, [onElapsed]);

    // Once, when the time runs out.
    useEffect(() => {
        if (elapsed) {
            onElapsedRef.current();
        }
    }, [elapsed]);

    return (
        <span
            role="timer"
            aria-label={t('Time left: :time', {
                time: formatDuration(remaining),
            })}
            className={cn(
                'tabular inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm font-semibold transition-colors motion-reduce:transition-none',
                remaining <= 60
                    ? 'border border-destructive text-destructive'
                    : remaining <= 300
                      ? 'border border-warning text-warning'
                      : 'text-foreground',
            )}
        >
            <Clock className="size-4" aria-hidden="true" />
            {formatDuration(remaining)}
        </span>
    );
}
