import { Link } from '@inertiajs/react';
import {
    ClipboardCheck,
    ClipboardList,
    FileQuestion,
    TriangleAlert,
    UsersRound,
} from 'lucide-react';
import { StatCard } from '@/components/dashboard/stat-card';
import { RoleDashboard } from '@/components/role-dashboard';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import teacher from '@/routes/teacher';

type TeacherDashboardData = {
    cards: {
        active_tests: number;
        submissions_week: number;
        pending_grading: number;
        pass_rate: string | null;
    };
    closing_soon: { ulid: string; title: string; closes_at: string | null }[];
    recent: {
        attempt: string;
        test: { ulid: string; title: string };
        student: string;
        percent: string | null;
        submitted_at: string | null;
    }[];
    needs_review: {
        test: { ulid: string; title: string };
        excerpt: string;
        discrimination: string | null;
    }[];
};

export default function TeacherDashboard({
    dashboard: data,
}: {
    dashboard?: TeacherDashboardData;
}) {
    const { t, formatDate } = useTranslation();

    return (
        <RoleDashboard
            title="Teacher dashboard"
            intro="Build questions and tests, manage your groups and grade attempts."
            sections={[
                {
                    title: 'Question bank',
                    description: 'Write reusable questions of many types.',
                    href: teacher.questions.index(),
                    icon: FileQuestion,
                },
                {
                    title: 'Tests',
                    description: 'Assemble tests from your questions.',
                    href: teacher.tests.index(),
                    icon: ClipboardList,
                },
                {
                    title: 'Groups',
                    description: 'Invite students and assign tests.',
                    href: teacher.groups.index(),
                    icon: UsersRound,
                },
                {
                    title: 'Grading',
                    description: 'Review answers that need a teacher.',
                    href: teacher.grading.index(),
                    icon: ClipboardCheck,
                },
            ]}
        >
            {data && (
                <div className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard
                            label={t('Open tests')}
                            value={String(data.cards.active_tests)}
                            href={teacher.tests.index()}
                        />
                        <StatCard
                            label={t('Submissions this week')}
                            value={String(data.cards.submissions_week)}
                        />
                        <StatCard
                            label={t('Waiting for grading')}
                            value={String(data.cards.pending_grading)}
                            href={teacher.grading.index()}
                        />
                        <StatCard
                            label={t('Average pass rate')}
                            value={
                                data.cards.pass_rate === null
                                    ? '—'
                                    : `${(Number(data.cards.pass_rate) * 100).toFixed(1)}%`
                            }
                        />
                    </div>

                    <div className="grid gap-4 lg:grid-cols-3">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('Closing soon')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {data.closing_soon.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            'No test closes in the next 7 days.',
                                        )}
                                    </p>
                                ) : (
                                    <ul className="space-y-2 text-sm">
                                        {data.closing_soon.map((test) => (
                                            <li
                                                key={test.ulid}
                                                className="flex justify-between gap-2"
                                            >
                                                <Link
                                                    href={teacher.tests.analytics.show(
                                                        test.ulid,
                                                    )}
                                                    className="underline-offset-4 hover:underline"
                                                >
                                                    {test.title}
                                                </Link>
                                                <span className="shrink-0 text-muted-foreground">
                                                    {test.closes_at
                                                        ? formatDate(
                                                              test.closes_at,
                                                              'PPp',
                                                          )
                                                        : ''}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('Recent submissions')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {data.recent.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('No submissions yet.')}
                                    </p>
                                ) : (
                                    <ul className="space-y-2 text-sm">
                                        {data.recent.map((row) => (
                                            <li
                                                key={row.attempt}
                                                className="flex justify-between gap-2"
                                            >
                                                <Link
                                                    href={teacher.tests.analytics.attempts.show(
                                                        {
                                                            test: row.test.ulid,
                                                            attempt:
                                                                row.attempt,
                                                        },
                                                    )}
                                                    className="min-w-0 truncate underline-offset-4 hover:underline"
                                                >
                                                    {row.student} ·{' '}
                                                    {row.test.title}
                                                </Link>
                                                <span className="tabular shrink-0 text-muted-foreground">
                                                    {row.percent === null
                                                        ? t('ungraded')
                                                        : `${row.percent}%`}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <TriangleAlert
                                        className="size-4 text-warning"
                                        aria-hidden="true"
                                    />
                                    {t('Questions needing review')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {data.needs_review.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('No question is flagged.')}
                                    </p>
                                ) : (
                                    <ul className="space-y-2 text-sm">
                                        {data.needs_review.map((row, index) => (
                                            <li key={index}>
                                                <Link
                                                    href={teacher.tests.analytics.show(
                                                        row.test.ulid,
                                                    )}
                                                    className="line-clamp-2 underline-offset-4 hover:underline"
                                                >
                                                    {row.excerpt}
                                                </Link>
                                                <span className="tabular text-xs text-muted-foreground">
                                                    {row.test.title} · D ={' '}
                                                    {row.discrimination ?? '—'}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            )}
        </RoleDashboard>
    );
}

TeacherDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
