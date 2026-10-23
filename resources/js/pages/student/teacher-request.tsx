import { Form, Head } from '@inertiajs/react';
import { BookOpenCheck, ClipboardList, Clock, UsersRound } from 'lucide-react';
import { useState } from 'react';
import TeacherRequestController from '@/actions/App/Http/Controllers/Student/TeacherRequestController';
import { PageHeader } from '@/components/data/page-header';
import { StatusBadge } from '@/components/data/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import student from '@/routes/student';
import type { TeacherAccessRequest } from '@/types';

export default function TeacherRequestPage({
    latest,
    canSubmit,
    cooldownEndsAt,
    maxLength,
}: {
    latest: TeacherAccessRequest | null;
    canSubmit: boolean;
    cooldownEndsAt: string | null;
    maxLength: number;
}) {
    const { t, formatDate } = useTranslation();
    const [length, setLength] = useState(0);

    const benefits = [
        {
            icon: BookOpenCheck,
            text: t('Build a question bank with seven question types.'),
        },
        {
            icon: ClipboardList,
            text: t('Create timed, auto-graded tests.'),
        },
        {
            icon: UsersRound,
            text: t('Invite students into groups and follow their results.'),
        },
    ];

    return (
        <>
            <Head title={t('Become a teacher')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Become a teacher')}
                    description={t(
                        'Teachers create questions and tests for their students. Tell us a little about yourself and an administrator will review your request.',
                    )}
                />

                <div className="grid gap-4 lg:grid-cols-5">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>{t('What teachers can do')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-3 text-sm">
                                {benefits.map(({ icon: Icon, text }) => (
                                    <li key={text} className="flex gap-3">
                                        <Icon
                                            className="mt-0.5 size-4 shrink-0 text-primary"
                                            aria-hidden="true"
                                        />
                                        {text}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-4 lg:col-span-3">
                        {latest && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        {t('Your latest request')}
                                    </CardTitle>
                                    <CardDescription>
                                        {t('Sent :date', {
                                            date: latest.created_at
                                                ? formatDate(latest.created_at)
                                                : '—',
                                        })}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3 text-sm">
                                    {latest.status === 'pending' && (
                                        <StatusBadge
                                            tone="warning"
                                            icon={Clock}
                                            label={t('Waiting for review')}
                                        />
                                    )}
                                    {latest.status === 'rejected' && (
                                        <>
                                            <StatusBadge
                                                tone="danger"
                                                label={t('Not approved :date', {
                                                    date: latest.reviewed_at
                                                        ? formatDate(
                                                              latest.reviewed_at,
                                                          )
                                                        : '',
                                                })}
                                            />
                                            {latest.review_note && (
                                                <p className="rounded-md bg-muted p-3 break-words text-muted-foreground">
                                                    {latest.review_note}
                                                </p>
                                            )}
                                        </>
                                    )}
                                    {cooldownEndsAt && (
                                        <p className="text-muted-foreground">
                                            {t(
                                                'You can send a new request after :date.',
                                                {
                                                    date: formatDate(
                                                        cooldownEndsAt,
                                                    ),
                                                },
                                            )}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {canSubmit && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('Request access')}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Form
                                        {...TeacherRequestController.store.form()}
                                        resetOnSuccess
                                        className="grid gap-4"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="message">
                                                        {t(
                                                            'Why do you want to teach on BrainCheck?',
                                                        )}
                                                    </Label>
                                                    <Textarea
                                                        id="message"
                                                        name="message"
                                                        rows={6}
                                                        required
                                                        minLength={20}
                                                        maxLength={maxLength}
                                                        onChange={(event) =>
                                                            setLength(
                                                                event.target
                                                                    .value
                                                                    .length,
                                                            )
                                                        }
                                                    />
                                                    <p className="tabular text-right text-xs text-muted-foreground">
                                                        {length} / {maxLength}
                                                    </p>
                                                    <InputError
                                                        message={errors.message}
                                                    />
                                                </div>
                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                    className="justify-self-start"
                                                >
                                                    {processing && <Spinner />}
                                                    {t('Send request')}
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

TeacherRequestPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Become a teacher', href: student.teacherRequest.show() },
    ],
};
