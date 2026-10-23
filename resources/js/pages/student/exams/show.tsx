import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Expand, Play, RotateCcw, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/data/page-header';
import {
    Countdown,
    ExamCardDetails,
} from '@/components/exams/exam-card-details';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import student from '@/routes/student';
import type { ExamCard } from '@/types';

/**
 * Exam intro: rules, what is recorded (transparency) and Start. Start is a
 * POST; the server re-checks everything and resumes an attempt in progress.
 */
export default function ExamIntro({
    exam,
    serverNow,
}: {
    exam: ExamCard;
    serverNow: string;
}) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const start = async (fullscreen: boolean) => {
        if (fullscreen && document.documentElement.requestFullscreen) {
            // Optional, never forced: the student may decline or leave it.
            await document.documentElement
                .requestFullscreen()
                .catch(() => undefined);
        }

        router.post(
            student.exams.attempts.store.url(exam.ulid),
            {},
            {
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (errors) => setError(errors.test ?? null),
            },
        );
    };

    return (
        <>
            <Head title={exam.title} />

            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={exam.title}
                    description={exam.description ?? undefined}
                    actions={
                        <Button asChild variant="ghost">
                            <Link href={student.exams.index()}>
                                <ArrowLeft aria-hidden="true" />
                                {t('My exams')}
                            </Link>
                        </Button>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('Rules')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <ExamCardDetails exam={exam} />
                        <ul className="list-disc space-y-1 pl-5 text-sm">
                            <li>
                                {exam.time_limit_minutes
                                    ? t(
                                          'The timer starts when you press Start and does not stop, even if you close the page.',
                                      )
                                    : t(
                                          'There is no time limit, but the test may close at a set time.',
                                      )}
                            </li>
                            <li>
                                {t('You need :percent% to pass.', {
                                    percent: exam.pass_percent,
                                })}
                            </li>
                            <li>
                                {t(
                                    'Your answers save automatically while you work.',
                                )}
                            </li>
                            <li>
                                {t(
                                    'When the time is up, your saved answers are submitted automatically.',
                                )}
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Alert>
                    <ShieldCheck aria-hidden="true" />
                    <AlertTitle>
                        {t('What is recorded during the exam')}
                    </AlertTitle>
                    <AlertDescription>
                        {t(
                            'When you switch tabs or windows, copy or paste, or leave fullscreen, the time and the current question are recorded (never what you copied or typed). Your teacher can see these notes; they never change your score.',
                        )}
                    </AlertDescription>
                </Alert>

                {error && (
                    <Alert variant="destructive">
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-wrap items-center gap-3">
                    {exam.section === 'in_progress' && exam.attempt ? (
                        <>
                            <Button asChild>
                                <Link
                                    href={student.attempts.show(
                                        exam.attempt.ulid,
                                    )}
                                >
                                    <RotateCcw aria-hidden="true" />
                                    {t('Resume')}
                                </Link>
                            </Button>
                            {exam.attempt.deadline_at && (
                                <Countdown
                                    target={exam.attempt.deadline_at}
                                    serverNow={serverNow}
                                    label="left"
                                />
                            )}
                        </>
                    ) : exam.section === 'available' ? (
                        <>
                            <Button
                                onClick={() => void start(false)}
                                disabled={processing}
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <Play aria-hidden="true" />
                                )}
                                {t('Start')}
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => void start(true)}
                                disabled={processing}
                            >
                                <Expand aria-hidden="true" />
                                {t('Take in fullscreen')}
                            </Button>
                        </>
                    ) : exam.section === 'upcoming' && exam.opens_at ? (
                        <Countdown
                            target={exam.opens_at}
                            serverNow={serverNow}
                            label="opens"
                        />
                    ) : (
                        <InputError
                            message={t('This test cannot be started any more.')}
                        />
                    )}
                </div>
            </div>
        </>
    );
}

ExamIntro.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'My exams', href: student.exams.index() },
    ],
};
