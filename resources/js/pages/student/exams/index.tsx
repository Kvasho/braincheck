import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, Play, RotateCcw } from 'lucide-react';
import { EmptyState } from '@/components/data/empty-state';
import { PageHeader } from '@/components/data/page-header';
import {
    Countdown,
    ExamCardDetails,
} from '@/components/exams/exam-card-details';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import student from '@/routes/student';
import type { ExamCard, ExamSection, Option } from '@/types';

const ALL = '__all__';

export default function StudentExams({
    exams,
    groups,
    filters,
    serverNow,
}: {
    exams: ExamCard[];
    groups: Option[];
    filters: { group: string | null };
    serverNow: string;
}) {
    const { t } = useTranslation();

    const sections: { key: ExamSection; title: string }[] = [
        { key: 'in_progress', title: t('In progress') },
        { key: 'available', title: t('Available now') },
        { key: 'upcoming', title: t('Upcoming') },
        { key: 'closed', title: t('Closed or completed') },
    ];

    return (
        <>
            <Head title={t('My exams')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('My exams')}
                    description={t(
                        'Tests your teachers assigned to your groups.',
                    )}
                />

                {groups.length > 1 && (
                    <div className="grid gap-1.5 sm:w-64">
                        <Label htmlFor="filter-group">{t('Group')}</Label>
                        <Select
                            value={filters.group ?? ALL}
                            onValueChange={(group) =>
                                router.get(
                                    student.exams.index.url(),
                                    group === ALL ? {} : { group },
                                    { preserveScroll: true, replace: true },
                                )
                            }
                        >
                            <SelectTrigger id="filter-group">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    {t('All groups')}
                                </SelectItem>
                                {groups.map((group) => (
                                    <SelectItem
                                        key={group.value}
                                        value={group.value}
                                    >
                                        {group.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}

                {exams.length === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
                        title={t('No exams yet')}
                        description={t(
                            'When a teacher assigns a test to one of your groups, it appears here.',
                        )}
                        action={
                            <Button asChild variant="outline">
                                <Link href={student.groups.index()}>
                                    {t('My groups')}
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    sections.map((section) => {
                        const items = exams.filter(
                            (exam) => exam.section === section.key,
                        );

                        if (items.length === 0) {
                            return null;
                        }

                        return (
                            <section
                                key={section.key}
                                aria-labelledby={`section-${section.key}`}
                                className="space-y-3"
                            >
                                <h2
                                    id={`section-${section.key}`}
                                    className="text-lg font-semibold"
                                >
                                    {section.title}
                                </h2>
                                <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    {items.map((exam) => (
                                        <li key={exam.ulid}>
                                            <ExamListCard
                                                exam={exam}
                                                serverNow={serverNow}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        );
                    })
                )}
            </div>
        </>
    );
}

function ExamListCard({
    exam,
    serverNow,
}: {
    exam: ExamCard;
    serverNow: string;
}) {
    const { t } = useTranslation();

    return (
        <Card className="h-full">
            <CardHeader>
                <CardTitle>
                    <Link
                        href={student.exams.show(exam.ulid)}
                        className="underline-offset-4 hover:underline"
                    >
                        {exam.title}
                    </Link>
                </CardTitle>
                {exam.best_percent !== null && (
                    <CardDescription className="tabular">
                        {t('Best score: :percent%', {
                            percent: exam.best_percent,
                        })}
                    </CardDescription>
                )}
            </CardHeader>
            <CardContent className="flex-1">
                <ExamCardDetails exam={exam} />
            </CardContent>
            <CardFooter className="flex flex-wrap items-center justify-between gap-2">
                {exam.section === 'in_progress' && exam.attempt && (
                    <>
                        {exam.attempt.deadline_at && (
                            <Countdown
                                target={exam.attempt.deadline_at}
                                serverNow={serverNow}
                                label="left"
                            />
                        )}
                        <Button asChild>
                            <Link
                                href={student.attempts.show(exam.attempt.ulid)}
                            >
                                <RotateCcw aria-hidden="true" />
                                {t('Resume')}
                            </Link>
                        </Button>
                    </>
                )}
                {exam.section === 'available' && (
                    <Button asChild>
                        <Link href={student.exams.show(exam.ulid)}>
                            <Play aria-hidden="true" />
                            {t('Open')}
                        </Link>
                    </Button>
                )}
                {exam.section === 'upcoming' && exam.opens_at && (
                    <Countdown
                        target={exam.opens_at}
                        serverNow={serverNow}
                        label="opens"
                    />
                )}
            </CardFooter>
        </Card>
    );
}

StudentExams.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'My exams', href: student.exams.index() },
    ],
};
