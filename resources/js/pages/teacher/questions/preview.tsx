import { Head, Link, useHttp } from '@inertiajs/react';
import { ArrowLeft, Check, Clock, RotateCcw, X } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/data/page-header';
import { StatusBadge } from '@/components/data/status-badge';
import { QuestionTypeBadge } from '@/components/questions/question-fields';
import { RichText } from '@/components/rich-text';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import {
    QuestionTypePlayer,
    QuestionTypeReview,
} from '@/question-types/components';
import { questionTypeModule } from '@/question-types/registry';
import { dashboard } from '@/routes';
import teacher from '@/routes/teacher';
import type { GradeResult, QuestionPreview, RichTextDoc } from '@/types';

type Graded = {
    result: GradeResult;
    review: unknown;
    explanation: RichTextDoc | null;
};

/**
 * "Preview as student": the same Player and payload students get. Checking
 * the answer is for the owner only (it reveals the correct answer).
 */
export default function QuestionPreviewPage({
    question,
    canGrade,
}: {
    question: QuestionPreview;
    canGrade: boolean;
}) {
    const { t } = useTranslation();
    const initial = () =>
        questionTypeModule(question.type).initialAnswer(question.player);
    const [answer, setAnswer] = useState<unknown>(initial);
    const [graded, setGraded] = useState<Graded | null>(null);
    // The answer travels as JSON (any shape); PreviewGradeRequest decodes it.
    const http = useHttp<{ answer: string }, Graded>({ answer: '' });
    const [error, setError] = useState<string | null>(null);

    const check = async () => {
        setError(null);
        // Send the current answer (state updates of the hook would arrive too late).
        http.transform(() => ({ answer: JSON.stringify(answer) }));

        try {
            const response = await http.post(
                teacher.questions.preview.grade.url(question.ulid),
            );
            setGraded(response);
        } catch {
            setError(
                t('The answer could not be checked. Make sure it is complete.'),
            );
        }
    };

    const reset = () => {
        setAnswer(initial());
        setGraded(null);
        setError(null);
    };

    return (
        <>
            <Head title={t('Preview')} />

            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Preview as student')}
                    description={t(
                        'This is exactly what students see while answering.',
                    )}
                    actions={
                        canGrade && (
                            <Button asChild variant="ghost">
                                <Link
                                    href={teacher.questions.edit(question.ulid)}
                                >
                                    <ArrowLeft aria-hidden="true" />
                                    {t('Back to editing')}
                                </Link>
                            </Button>
                        )
                    }
                />

                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
                        <QuestionTypeBadge type={question.type} />
                        <span className="tabular text-sm text-muted-foreground">
                            {t(':points points', { points: question.points })}
                        </span>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <RichText doc={question.body} className="text-lg" />
                        {question.image_url && (
                            <img
                                src={question.image_url}
                                alt={t('Question image')}
                                className="max-h-96 rounded-lg border object-contain"
                            />
                        )}

                        {graded === null ? (
                            <QuestionTypePlayer
                                type={question.type}
                                payload={question.player}
                                value={answer}
                                onChange={setAnswer}
                                idPrefix={`preview-${question.ulid}`}
                            />
                        ) : (
                            <QuestionTypeReview
                                type={question.type}
                                review={graded.review}
                            />
                        )}

                        {error && (
                            <Alert variant="destructive">
                                <AlertDescription>{error}</AlertDescription>
                            </Alert>
                        )}

                        {canGrade && (
                            <div className="flex flex-wrap items-center gap-3">
                                {graded === null ? (
                                    <Button
                                        type="button"
                                        onClick={check}
                                        disabled={http.processing}
                                    >
                                        {http.processing && <Spinner />}
                                        {t('Check answer')}
                                    </Button>
                                ) : (
                                    <>
                                        <GradeBadge result={graded.result} />
                                        <span
                                            className="tabular font-medium"
                                            aria-live="polite"
                                        >
                                            {t(':awarded of :points points', {
                                                awarded:
                                                    graded.result
                                                        .awarded_points,
                                                points: question.points,
                                            })}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={reset}
                                        >
                                            <RotateCcw aria-hidden="true" />
                                            {t('Try again')}
                                        </Button>
                                    </>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {graded?.explanation && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Explanation')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <RichText doc={graded.explanation} />
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
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

QuestionPreviewPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Question bank', href: teacher.questions.index() },
    ],
};
