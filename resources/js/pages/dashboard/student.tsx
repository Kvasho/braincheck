import { Link } from '@inertiajs/react';
import {
    BookOpenCheck,
    ChartNoAxesColumn,
    GraduationCap,
    RotateCcw,
    Sparkles,
    UsersRound,
} from 'lucide-react';
import { Sparkline } from '@/components/charts/charts';
import { StatCard } from '@/components/dashboard/stat-card';
import { RoleDashboard } from '@/components/role-dashboard';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import student from '@/routes/student';
import type { ProgressPoint } from '@/types';

type StudentDashboardData = {
    available: number;
    in_progress: {
        title: string;
        attempt: { ulid: string; deadline_at: string | null } | null;
    } | null;
    latest: {
        attempt: string;
        test: string;
        percent: string | null;
        passed: boolean | null;
        submitted_at: string | null;
    }[];
    sparkline: ProgressPoint[];
    xp: number | null;
    level: number | null;
};

export default function StudentDashboard({
    dashboard: data,
}: {
    dashboard?: StudentDashboardData;
}) {
    const { t } = useTranslation();

    return (
        <RoleDashboard
            title="Student dashboard"
            intro="Join groups, take exams and follow your results."
            sections={[
                {
                    title: 'My groups',
                    description: 'Join a group with an invite code.',
                    href: student.groups.index(),
                    icon: UsersRound,
                },
                {
                    title: 'My exams',
                    description: 'Exams assigned to you.',
                    href: student.exams.index(),
                    icon: BookOpenCheck,
                },
                {
                    title: 'Results',
                    description: 'Your scores and feedback.',
                    href: student.results.index(),
                    icon: ChartNoAxesColumn,
                },
                {
                    title: 'Become a teacher',
                    description:
                        'Want to create tests for your own students? Request teacher access.',
                    href: student.teacherRequest.show(),
                    icon: GraduationCap,
                    hint: 'Reviewed by an administrator',
                },
            ]}
        >
            {data && (
                <div className="space-y-4">
                    {data.in_progress?.attempt && (
                        <Card className="border-primary">
                            <CardContent className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        {t('Exam in progress')}
                                    </p>
                                    <p className="font-semibold">
                                        {data.in_progress.title}
                                    </p>
                                </div>
                                <Button asChild>
                                    <Link
                                        href={student.attempts.show(
                                            data.in_progress.attempt.ulid,
                                        )}
                                    >
                                        <RotateCcw aria-hidden="true" />
                                        {t('Resume')}
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard
                            label={t('Exams available now')}
                            value={String(data.available)}
                            href={student.exams.index()}
                        />
                        <StatCard
                            label={t('XP')}
                            value={data.xp === null ? '—' : String(data.xp)}
                        />
                        <StatCard
                            label={t('Level')}
                            value={
                                data.level === null ? '—' : String(data.level)
                            }
                        />
                        <Card className="gap-1 py-4">
                            <CardContent className="space-y-1 px-4">
                                <p className="text-xs text-muted-foreground">
                                    {t('Progress')}
                                </p>
                                {data.sparkline.length >= 2 ? (
                                    <Sparkline
                                        values={data.sparkline.map((point) =>
                                            Number(point.percent),
                                        )}
                                        label={t(
                                            'Your last :count visible scores: :scores',
                                            {
                                                count: data.sparkline.length,
                                                scores: data.sparkline
                                                    .map(
                                                        (point) =>
                                                            `${point.percent}%`,
                                                    )
                                                    .join(', '),
                                            },
                                        )}
                                    />
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {t('Not enough results yet.')}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Latest results')}
                            </CardTitle>
                            {data.xp === null && (
                                <CardDescription className="flex items-center gap-1">
                                    <Sparkles
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    {t('XP and levels are coming soon.')}
                                </CardDescription>
                            )}
                        </CardHeader>
                        <CardContent>
                            {data.latest.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('No visible results yet.')}
                                </p>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {data.latest.map((row) => (
                                        <li
                                            key={row.attempt}
                                            className="flex justify-between gap-2"
                                        >
                                            <Link
                                                href={student.attempts.result(
                                                    row.attempt,
                                                )}
                                                className="underline-offset-4 hover:underline"
                                            >
                                                {row.test}
                                            </Link>
                                            <span className="tabular font-medium">
                                                {row.percent}%
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            )}
        </RoleDashboard>
    );
}

StudentDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
