import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { PageHeader } from '@/components/data/page-header';
import { QuestionTypeBadge } from '@/components/questions/question-fields';
import { RichText } from '@/components/rich-text';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDuration } from '@/hooks/use-server-clock';
import { useTranslation } from '@/hooks/use-translation';
import { QuestionTypeReview } from '@/question-types/components';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import teacher from '@/routes/teacher';
import type { QuestionType, RichTextDoc } from '@/types';

type Detail = {
    ulid: string;
    student: { name: string; email: string };
    status: string;
    grading_status: string;
    score: string | null;
    max_score: string | null;
    percent: string | null;
    passed: boolean | null;
    started_at: string;
    submitted_at: string | null;
    event_overflow: Record<string, number>;
};

/**
 * One attempt for the teacher: every answer with the full review (correct
 * answers shown) and the integrity timeline (server times from the start).
 */
export default function AttemptDetail({
    test,
    area,
    attempt,
    questions,
    timeline,
}: {
    test: { ulid: string; title: string };
    area: 'teacher' | 'admin';
    attempt: Detail;
    questions: {
        position: number;
        type: QuestionType;
        body: RichTextDoc;
        points: string;
        awarded_points: string | null;
        review: unknown;
        feedback: string | null;
    }[];
    timeline: {
        type: string;
        label: string;
        seconds: number;
        meta: Record<string, number> | null;
    }[];
}) {
    const { t, formatDate } = useTranslation();
    const routes = area === 'admin' ? admin.tests : teacher.tests;
    const overflow = Object.entries(attempt.event_overflow);

    return (
        <>
            <Head title={attempt.student.name} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={attempt.student.name}
                    description={test.title}
                    actions={
                        <Button asChild variant="ghost">
                            <Link href={routes.analytics.show(test.ulid)}>
                                <ArrowLeft aria-hidden="true" />
                                {t('Analytics')}
                            </Link>
                        </Button>
                    }
                />

                <Card>
                    <CardContent className="grid gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <p className="text-muted-foreground">
                                {t('Score')}
                            </p>
                            <p className="tabular text-lg font-semibold">
                                {attempt.percent === null
                                    ? '—'
                                    : `${attempt.score} / ${attempt.max_score} (${attempt.percent}%)`}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">
                                {t('Started')}
                            </p>
                            <p>{formatDate(attempt.started_at, 'PPp')}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">
                                {t('Submitted')}
                            </p>
                            <p>
                                {attempt.submitted_at
                                    ? formatDate(attempt.submitted_at, 'PPp')
                                    : '—'}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('Integrity timeline')}
                        </CardTitle>
                        <CardDescription>
                            {t(
                                'Recorded by the browser; times are the server’s, counted from the start. They never change the score.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {timeline.length === 0 && overflow.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('Nothing was recorded.')}
                            </p>
                        ) : (
                            <ol className="space-y-1 text-sm">
                                {timeline.map((event, index) => (
                                    <li key={index} className="flex gap-3">
                                        <span className="tabular w-16 shrink-0 text-muted-foreground">
                                            +
                                            {formatDuration(
                                                Math.max(0, event.seconds),
                                            )}
                                        </span>
                                        <span>{event.label}</span>
                                        {event.meta?.question !== undefined && (
                                            <span className="text-muted-foreground">
                                                {t('Question :number', {
                                                    number: event.meta.question,
                                                })}
                                            </span>
                                        )}
                                    </li>
                                ))}
                                {overflow.map(([type, count]) => (
                                    <li
                                        key={type}
                                        className="text-muted-foreground"
                                    >
                                        {t(
                                            ':count more “:type” events (counted, not stored)',
                                            { count, type },
                                        )}
                                    </li>
                                ))}
                            </ol>
                        )}
                    </CardContent>
                </Card>

                <ol className="space-y-4">
                    {questions.map((question) => (
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
                                            {t(':awarded of :points points', {
                                                awarded:
                                                    question.awarded_points ??
                                                    '0',
                                                points: question.points,
                                            })}
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
                                        <p className="rounded-lg border bg-muted/30 p-3 text-sm break-words whitespace-pre-wrap">
                                            {question.feedback}
                                        </p>
                                    )}
                                    {area === 'teacher' && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                href={teacher.grading.answers.show(
                                                    {
                                                        attempt: attempt.ulid,
                                                        position:
                                                            question.position,
                                                    },
                                                )}
                                            >
                                                {t('Grade or change points')}
                                            </Link>
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        </li>
                    ))}
                </ol>
            </div>
        </>
    );
}

AttemptDetail.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
