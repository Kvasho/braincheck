import { Head, Link, router, useHttp } from '@inertiajs/react';
import { ArrowLeft, Eye, Save, SkipForward } from 'lucide-react';
import { useEffect, useState } from 'react';
import { PageHeader } from '@/components/data/page-header';
import InputError from '@/components/input-error';
import { RichText } from '@/components/rich-text';
import { QuestionTypeBadge } from '@/components/questions/question-fields';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { QuestionTypeReview } from '@/question-types/components';
import { dashboard } from '@/routes';
import teacher from '@/routes/teacher';
import type { GradingAnswer } from '@/types';

const FEEDBACK_MAX = 2000;

/**
 * Grade one answer. Left: question and reference answer (from the
 * snapshot). Right: the student's answer as plain text (React escapes it;
 * line breaks kept), then points (0 … max, step 0.25) and feedback.
 * Auto-graded answers show the review and an override form (reason
 * required, audited). Ctrl/⌘ + Enter: save & next.
 */
export default function GradeAnswer({
    answer,
    attempt,
    student,
    anonymous,
    next,
}: {
    answer: GradingAnswer;
    attempt: {
        ulid: string;
        test: { ulid: string; title: string };
        submitted_at: string | null;
        status: string;
        question_count: number;
    };
    student: { label: string; name: string | null };
    anonymous: boolean;
    next: { attempt: string; position: number } | null;
}) {
    const { t, formatDate } = useTranslation();
    const [points, setPoints] = useState(answer.awarded_points ?? '');
    const [feedback, setFeedback] = useState(answer.feedback ?? '');
    const [reason, setReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [revealed, setRevealed] = useState<string | null>(student.name);
    const identity = useHttp<Record<string, never>, { name: string }>({});
    const [loaded, setLoaded] = useState(answer);

    // A new answer (save & next): start from its values.
    if (loaded !== answer) {
        setLoaded(answer);
        setPoints(answer.awarded_points ?? '');
        setFeedback(answer.feedback ?? '');
        setReason('');
        setErrors({});
        setRevealed(student.name);
    }

    const target = { attempt: answer.attempt, position: answer.position };

    const visit = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
        onSuccess: () => setErrors({}),
        onError: (next: Record<string, string>) => setErrors(next),
    };

    const save = (andNext: boolean) =>
        router.put(
            teacher.grading.answers.update.url(target),
            { points, feedback, version: answer.version, next: andNext },
            visit,
        );

    useEffect(() => {
        if (!answer.manual) {
            return;
        }

        const onKey = (event: KeyboardEvent) => {
            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                event.preventDefault();
                save(true);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    });

    const reveal = async () => {
        const response = await identity
            .post(teacher.grading.identity.store.url(attempt.ulid))
            .catch(() => null);

        if (response) {
            setRevealed(response.name);
        }
    };

    return (
        <>
            <Head title={t('Grading')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={attempt.test.title}
                    description={t('Question :number of :count', {
                        number: answer.position,
                        count: attempt.question_count,
                    })}
                    actions={
                        <>
                            <Button asChild variant="ghost">
                                <Link href={teacher.grading.index()}>
                                    <ArrowLeft aria-hidden="true" />
                                    {t('Grading')}
                                </Link>
                            </Button>
                            {next && (
                                <Button asChild variant="outline">
                                    <Link
                                        href={teacher.grading.answers.show(
                                            next,
                                        )}
                                    >
                                        <SkipForward aria-hidden="true" />
                                        {t('Skip')}
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="flex flex-wrap items-center gap-3 text-sm">
                    <span className="font-medium">
                        {revealed ?? student.label}
                    </span>
                    {revealed === null && anonymous && (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => void reveal()}
                            disabled={identity.processing}
                        >
                            <Eye aria-hidden="true" />
                            {t('Reveal name')}
                        </Button>
                    )}
                    {attempt.submitted_at && (
                        <span className="text-muted-foreground">
                            {t('Submitted :date.', {
                                date: formatDate(attempt.submitted_at, 'PPp'),
                            })}
                        </span>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
                            <CardTitle className="text-base">
                                {t('Question')}
                            </CardTitle>
                            <div className="flex items-center gap-2">
                                <QuestionTypeBadge type={answer.type} />
                                <span className="tabular text-sm text-muted-foreground">
                                    {t(':points points', {
                                        points: answer.points,
                                    })}
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <RichText doc={answer.body} />
                            {answer.manual && (
                                <div className="space-y-2 rounded-lg border bg-muted/30 p-4">
                                    <p className="text-sm font-medium">
                                        {t('Reference answer')}
                                    </p>
                                    <p className="text-sm whitespace-pre-wrap">
                                        {answer.reference_answer ??
                                            t('No reference answer.')}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Student answer')}
                            </CardTitle>
                            {answer.graded && (
                                <CardDescription className="tabular">
                                    {t(':awarded of :points points', {
                                        awarded: answer.awarded_points ?? '0',
                                        points: answer.points,
                                    })}
                                </CardDescription>
                            )}
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {answer.manual ? (
                                <p
                                    className="min-h-24 rounded-lg border p-4 break-words whitespace-pre-wrap"
                                    data-testid="student-text"
                                >
                                    {answer.text === null ||
                                    answer.text === '' ? (
                                        <span className="text-muted-foreground">
                                            {t('No answer.')}
                                        </span>
                                    ) : (
                                        answer.text
                                    )}
                                </p>
                            ) : (
                                <QuestionTypeReview
                                    type={answer.type}
                                    review={answer.review}
                                />
                            )}

                            {errors.version && (
                                <Alert variant="destructive">
                                    <AlertDescription>
                                        {errors.version}
                                    </AlertDescription>
                                </Alert>
                            )}

                            <div className="grid gap-4 sm:grid-cols-[10rem_1fr]">
                                <div className="grid gap-2">
                                    <Label htmlFor="points">
                                        {t('Points')}
                                    </Label>
                                    <Input
                                        id="points"
                                        type="number"
                                        inputMode="decimal"
                                        min={0}
                                        max={answer.points}
                                        step={0.25}
                                        className="tabular"
                                        value={points}
                                        onChange={(event) =>
                                            setPoints(event.target.value)
                                        }
                                        aria-describedby="points-max"
                                    />
                                    <p
                                        id="points-max"
                                        className="tabular text-xs text-muted-foreground"
                                    >
                                        {t('0 to :max', { max: answer.points })}
                                    </p>
                                    <InputError message={errors.points} />
                                </div>

                                {answer.manual ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="feedback">
                                            {t('Feedback (optional)')}
                                        </Label>
                                        <Textarea
                                            id="feedback"
                                            rows={4}
                                            maxLength={FEEDBACK_MAX}
                                            value={feedback}
                                            onChange={(event) =>
                                                setFeedback(event.target.value)
                                            }
                                        />
                                        <InputError message={errors.feedback} />
                                    </div>
                                ) : (
                                    <div className="grid gap-2">
                                        <Label htmlFor="reason">
                                            {t(
                                                'Reason for the change (required)',
                                            )}
                                        </Label>
                                        <Textarea
                                            id="reason"
                                            rows={3}
                                            maxLength={500}
                                            value={reason}
                                            onChange={(event) =>
                                                setReason(event.target.value)
                                            }
                                        />
                                        <InputError message={errors.reason} />
                                    </div>
                                )}
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {answer.manual ? (
                                    <>
                                        <Button
                                            onClick={() => save(true)}
                                            disabled={
                                                processing || points === ''
                                            }
                                        >
                                            {processing ? (
                                                <Spinner />
                                            ) : (
                                                <Save aria-hidden="true" />
                                            )}
                                            {t('Save & next')}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            onClick={() => save(false)}
                                            disabled={
                                                processing || points === ''
                                            }
                                        >
                                            {t('Save')}
                                        </Button>
                                        <span className="self-center text-xs text-muted-foreground">
                                            {t('Ctrl + Enter: save & next')}
                                        </span>
                                    </>
                                ) : (
                                    <Button
                                        onClick={() =>
                                            router.put(
                                                teacher.grading.answers.override.url(
                                                    target,
                                                ),
                                                { points, reason },
                                                visit,
                                            )
                                        }
                                        disabled={
                                            processing ||
                                            points === '' ||
                                            reason.trim().length < 3
                                        }
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <Save aria-hidden="true" />
                                        )}
                                        {t('Change points')}
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

GradeAnswer.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Grading', href: teacher.grading.index() },
    ],
};
