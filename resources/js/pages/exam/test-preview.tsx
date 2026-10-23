import { Head, Link, useHttp } from '@inertiajs/react';
import { ArrowLeft, Check, Clock, Eye, RotateCcw, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { StatusBadge } from '@/components/data/status-badge';
import { QuestionTypeBadge } from '@/components/questions/question-fields';
import { RichText } from '@/components/rich-text';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import ExamLayout from '@/layouts/exam-layout';
import {
    QuestionTypePlayer,
    QuestionTypeReview,
} from '@/question-types/components';
import { questionTypeModule } from '@/question-types/registry';
import teacher from '@/routes/teacher';
import type { GradeResult, PreviewQuestion, PreviewResult } from '@/types';

/**
 * "Preview as student" for a whole test: the snapshots of a preview attempt
 * in the exam layout, then the graded result with reviews. The attempt
 * never counts anywhere. A simplified player until the Stage 7 exam engine
 * (no autosave, no navigation between pages).
 */
export default function TestPreview({
    test,
    attempt,
    questions,
}: {
    test: { ulid: string; title: string; time_limit_minutes: number | null };
    attempt: { ulid: string; status: string; deadline_at: string | null };
    questions: PreviewQuestion[];
}) {
    const { t } = useTranslation();
    const [answers, setAnswers] = useState<Record<number, unknown>>(() =>
        Object.fromEntries(
            questions.map((question) => [
                question.position,
                questionTypeModule(question.type).initialAnswer(
                    question.player,
                ),
            ]),
        ),
    );
    const [result, setResult] = useState<PreviewResult | null>(null);
    const [error, setError] = useState<string | null>(null);
    const http = useHttp<{ answers: string }, PreviewResult>({ answers: '' });
    const finished = result !== null || attempt.status !== 'in_progress';

    const submit = async () => {
        setError(null);
        http.transform(() => ({ answers: JSON.stringify(answers) }));

        try {
            setResult(
                await http.post(
                    teacher.tests.preview.submit.url({
                        test: test.ulid,
                        attempt: attempt.ulid,
                    }),
                ),
            );
            window.scrollTo({ top: 0 });
        } catch {
            setError(
                t(
                    'The answers could not be submitted. Check that every answer is complete.',
                ),
            );
        }
    };

    const restart = teacher.tests.preview.store.url(test.ulid);

    return (
        <ExamLayout
            header={
                <>
                    <StatusBadge tone="info" icon={Eye} label={t('Preview')} />
                    {attempt.deadline_at && !finished && (
                        <Countdown deadline={attempt.deadline_at} />
                    )}
                </>
            }
        >
            <Head title={t('Preview: :title', { title: test.title })} />

            <div className="space-y-6">
                <div className="space-y-2">
                    <h1 className="text-2xl font-semibold">{test.title}</h1>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            'This preview does not count anywhere and is deleted after 24 hours.',
                        )}
                    </p>
                    <Button asChild variant="ghost" size="sm">
                        <Link href={teacher.tests.edit(test.ulid)}>
                            <ArrowLeft aria-hidden="true" />
                            {t('Back to the builder')}
                        </Link>
                    </Button>
                </div>

                {result && (
                    <Alert variant={result.passed ? 'success' : 'warning'}>
                        <AlertTitle className="tabular">
                            {t(':score of :max points (:percent%)', {
                                score: result.score,
                                max: result.max_score,
                                percent: result.percent,
                            })}
                        </AlertTitle>
                        <AlertDescription>
                            {result.passed
                                ? t('A student with these answers would pass.')
                                : t(
                                      'A student with these answers would not pass.',
                                  )}
                        </AlertDescription>
                    </Alert>
                )}

                {!result && attempt.status !== 'in_progress' && (
                    <Alert>
                        <AlertDescription>
                            {t('This preview is already finished.')}
                        </AlertDescription>
                    </Alert>
                )}

                <ol className="space-y-6">
                    {questions.map((question) => {
                        const graded = result?.questions.find(
                            (q) => q.position === question.position,
                        );

                        return (
                            <li key={question.position}>
                                <Card>
                                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
                                        <CardTitle className="tabular text-base">
                                            {t('Question :number of :count', {
                                                number: question.position,
                                                count: questions.length,
                                            })}
                                        </CardTitle>
                                        <div className="flex items-center gap-2">
                                            <QuestionTypeBadge
                                                type={question.type}
                                            />
                                            <span className="tabular text-sm text-muted-foreground">
                                                {t(':points points', {
                                                    points: question.points,
                                                })}
                                            </span>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-6">
                                        <RichText
                                            doc={question.body}
                                            className="text-lg"
                                        />
                                        {question.image_url && (
                                            <img
                                                src={question.image_url}
                                                alt={t('Question image')}
                                                className="max-h-96 rounded-lg border object-contain"
                                            />
                                        )}

                                        {graded ? (
                                            <>
                                                <QuestionTypeReview
                                                    type={question.type}
                                                    review={graded.review}
                                                />
                                                <div className="flex flex-wrap items-center gap-3">
                                                    <GradeBadge
                                                        result={graded.result}
                                                    />
                                                    <span className="tabular text-sm font-medium">
                                                        {t(
                                                            ':awarded of :points points',
                                                            {
                                                                awarded:
                                                                    graded
                                                                        .result
                                                                        .awarded_points,
                                                                points: graded.points,
                                                            },
                                                        )}
                                                    </span>
                                                </div>
                                                {graded.explanation && (
                                                    <div className="rounded-lg border bg-muted/30 p-4">
                                                        <p className="mb-2 text-sm font-medium">
                                                            {t('Explanation')}
                                                        </p>
                                                        <RichText
                                                            doc={
                                                                graded.explanation
                                                            }
                                                        />
                                                    </div>
                                                )}
                                            </>
                                        ) : (
                                            <QuestionTypePlayer
                                                type={question.type}
                                                payload={question.player}
                                                value={
                                                    answers[question.position]
                                                }
                                                disabled={finished}
                                                onChange={(answer) =>
                                                    setAnswers((current) => ({
                                                        ...current,
                                                        [question.position]:
                                                            answer,
                                                    }))
                                                }
                                                idPrefix={`q${question.position}`}
                                            />
                                        )}
                                    </CardContent>
                                </Card>
                            </li>
                        );
                    })}
                </ol>

                {error && (
                    <Alert variant="destructive">
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-wrap gap-2">
                    {finished ? (
                        <Button asChild variant="outline">
                            <Link href={restart} method="post" as="button">
                                <RotateCcw aria-hidden="true" />
                                {t('Start a new preview')}
                            </Link>
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            onClick={submit}
                            disabled={http.processing}
                        >
                            {http.processing && <Spinner />}
                            {t('Finish and see the result')}
                        </Button>
                    )}
                </div>
            </div>
        </ExamLayout>
    );
}

/** Remaining time (informational in the preview; nothing is enforced). */
function Countdown({ deadline }: { deadline: string }) {
    const { t } = useTranslation();
    const end = new Date(deadline).getTime();
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(timer);
    }, []);

    const left = Math.max(0, Math.floor((end - now) / 1000));
    const minutes = Math.floor(left / 60);
    const seconds = String(left % 60).padStart(2, '0');

    return (
        <span
            role="timer"
            className="tabular inline-flex items-center gap-1 text-sm font-medium"
            aria-label={t('Time left: :time', {
                time: `${minutes}:${seconds}`,
            })}
        >
            <Clock className="size-4" aria-hidden="true" />
            {minutes}:{seconds}
        </span>
    );
}

function GradeBadge({ result }: { result: GradeResult }) {
    const { t } = useTranslation();

    if (result.needs_manual_grading) {
        return (
            <StatusBadge
                tone="info"
                icon={Clock}
                label={t('Graded by the teacher')}
            />
        );
    }

    return result.is_correct ? (
        <StatusBadge tone="success" icon={Check} label={t('Correct')} />
    ) : (
        <StatusBadge tone="danger" icon={X} label={t('Not fully correct')} />
    );
}
