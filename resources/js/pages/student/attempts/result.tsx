import { Head, Link } from '@inertiajs/react';
import confetti from 'canvas-confetti';
import {
    CircleCheck,
    CircleX,
    Clock,
    Hourglass,
    RotateCcw,
} from 'lucide-react';
import { useReducedMotion } from 'motion/react';
import { useEffect } from 'react';
import { PageHeader } from '@/components/data/page-header';
import { StatusBadge } from '@/components/data/status-badge';
import { QuestionTypeBadge } from '@/components/questions/question-fields';
import { ScoreRing } from '@/components/results/score-ring';
import { RichText } from '@/components/rich-text';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDuration } from '@/hooks/use-server-clock';
import { useTranslation } from '@/hooks/use-translation';
import { QuestionTypeReview } from '@/question-types/components';
import { dashboard } from '@/routes';
import student from '@/routes/student';
import type { StudentAttemptResult } from '@/types';

/**
 * A student's result, exactly as the test's results visibility allows (the
 * server decides: ResultsVisibilityPolicy). Feedback is plain text.
 */
export default function AttemptResult({
    attempt,
    retry,
}: {
    attempt: StudentAttemptResult;
    retry: string | null;
}) {
    const { t, formatDate } = useTranslation();
    const reduceMotion = useReducedMotion();
    const passed = attempt.result?.passed === true;

    useEffect(() => {
        if (passed && !reduceMotion) {
            void confetti({
                particleCount: 90,
                spread: 70,
                origin: { y: 0.3 },
                disableForReducedMotion: true,
            });
        }
    }, [passed, reduceMotion]);

    return (
        <>
            <Head title={attempt.test.title} />

            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title={attempt.test.title} />

                <Card>
                    <CardContent className="flex flex-wrap items-center gap-6">
                        {attempt.result && attempt.result.percent !== null ? (
                            <ScoreRing
                                percent={Number(attempt.result.percent)}
                                passed={attempt.result.passed}
                            />
                        ) : null}
                        <div className="min-w-0 flex-1 space-y-3">
                            {attempt.result ? (
                                <>
                                    <p className="tabular text-lg font-semibold">
                                        {t(
                                            ':score of :max points (:percent%)',
                                            {
                                                score:
                                                    attempt.result.score ?? '0',
                                                max:
                                                    attempt.result.max_score ??
                                                    '0',
                                                percent:
                                                    attempt.result.percent ??
                                                    '0',
                                            },
                                        )}
                                    </p>
                                    {attempt.result.passed ? (
                                        <StatusBadge
                                            tone="success"
                                            icon={CircleCheck}
                                            label={t('Passed')}
                                        />
                                    ) : (
                                        <StatusBadge
                                            tone="danger"
                                            icon={CircleX}
                                            label={t('Not passed')}
                                        />
                                    )}
                                </>
                            ) : attempt.visibility.awaiting_review ? (
                                <StatusBadge
                                    tone="info"
                                    icon={Hourglass}
                                    label={t('Awaiting teacher review')}
                                />
                            ) : attempt.status === 'timed_out' ? (
                                <StatusBadge
                                    tone="warning"
                                    icon={Clock}
                                    label={t('Time was up')}
                                />
                            ) : (
                                <StatusBadge
                                    tone="success"
                                    icon={CircleCheck}
                                    label={t('Submitted')}
                                />
                            )}

                            <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                                {attempt.submitted_at && (
                                    <>
                                        <dt className="text-muted-foreground">
                                            {t('Submitted')}
                                        </dt>
                                        <dd>
                                            {formatDate(
                                                attempt.submitted_at,
                                                'PPp',
                                            )}
                                        </dd>
                                    </>
                                )}
                                {attempt.duration_seconds !== null && (
                                    <>
                                        <dt className="text-muted-foreground">
                                            {t('Time taken')}
                                        </dt>
                                        <dd className="tabular">
                                            {formatDuration(
                                                attempt.duration_seconds,
                                            )}
                                        </dd>
                                    </>
                                )}
                            </dl>
                        </div>
                    </CardContent>
                </Card>

                {!attempt.visibility.score && (
                    <Alert>
                        <Hourglass aria-hidden="true" />
                        <AlertTitle>
                            {attempt.visibility.awaiting_review
                                ? t(
                                      'Your teacher still has to grade some answers.',
                                  )
                                : attempt.visibility.later
                                  ? t('Results are not shown yet.')
                                  : t('This test does not show results.')}
                        </AlertTitle>
                        <AlertDescription>
                            {attempt.visibility.later
                                ? t(
                                      'They appear here as soon as the test settings allow it.',
                                  )
                                : t('Your answers were submitted.')}
                        </AlertDescription>
                    </Alert>
                )}

                {attempt.visibility.score &&
                    !attempt.visibility.correct_answers && (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'This test shows your points, but not the correct answers.',
                            )}
                        </p>
                    )}

                {attempt.questions.length > 0 && (
                    <ol className="space-y-4">
                        {attempt.questions.map((question) => (
                            <li key={question.position}>
                                <Card>
                                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
                                        <CardTitle className="tabular text-base">
                                            {t('Question :number', {
                                                number: question.position,
                                            })}
                                        </CardTitle>
                                        <div className="flex items-center gap-2">
                                            <QuestionTypeBadge
                                                type={question.type}
                                            />
                                            <span className="tabular text-sm font-medium">
                                                {t(
                                                    ':awarded of :points points',
                                                    {
                                                        awarded:
                                                            question.awarded_points ??
                                                            '0',
                                                        points: question.points,
                                                    },
                                                )}
                                            </span>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <RichText doc={question.body} />
                                        <QuestionTypeReview
                                            type={question.type}
                                            review={question.review}
                                        />
                                        {question.feedback && (
                                            <div className="rounded-lg border bg-muted/30 p-4">
                                                <p className="mb-1 text-sm font-medium">
                                                    {t('Teacher feedback')}
                                                </p>
                                                <p className="text-sm break-words whitespace-pre-wrap">
                                                    {question.feedback}
                                                </p>
                                            </div>
                                        )}
                                        {question.explanation && (
                                            <div className="rounded-lg border p-4">
                                                <p className="mb-1 text-sm font-medium">
                                                    {t('Explanation')}
                                                </p>
                                                <RichText
                                                    doc={question.explanation}
                                                />
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            </li>
                        ))}
                    </ol>
                )}

                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline">
                        <Link href={student.results.index()}>
                            {t('All results')}
                        </Link>
                    </Button>
                    {retry && (
                        <Button asChild>
                            <Link href={student.exams.show(retry)}>
                                <RotateCcw aria-hidden="true" />
                                {t('Try again')}
                            </Link>
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}

AttemptResult.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Results', href: student.results.index() },
    ],
};
