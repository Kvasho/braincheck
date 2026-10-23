import { Link } from '@inertiajs/react';
import { SeriesBarChart } from '@/components/charts/charts';
import { StatCard } from '@/components/dashboard/stat-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import admin from '@/routes/admin';

export type PlatformAnalyticsData = {
    tests_total: number;
    submissions_total: number;
    pass_rate: string | null;
    top_teachers: { name: string; count: number }[];
    top_tests: { ulid: string; title: string; count: number }[];
    per_day: { date: string; count: number }[];
};

/** Platform-wide exam numbers for admins (real attempts only). */
export function PlatformAnalytics({ data }: { data: PlatformAnalyticsData }) {
    const { t, formatDate } = useTranslation();

    return (
        <section className="space-y-4" aria-labelledby="platform-analytics">
            <h2 id="platform-analytics" className="text-lg font-semibold">
                {t('Exams across the platform')}
            </h2>

            <div className="grid gap-4 sm:grid-cols-3">
                <StatCard label={t('Tests')} value={String(data.tests_total)} />
                <StatCard
                    label={t('Submitted attempts')}
                    value={String(data.submissions_total)}
                />
                <StatCard
                    label={t('Average pass rate')}
                    value={
                        data.pass_rate === null
                            ? '—'
                            : `${(Number(data.pass_rate) * 100).toFixed(1)}%`
                    }
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('Submissions per day (30 days)')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <SeriesBarChart
                            points={data.per_day.map((day) => ({
                                key: day.date,
                                label: formatDate(day.date, 'd MMM'),
                                value: day.count,
                            }))}
                            summary={t(
                                ':total submissions in the last 30 days, shown per day.',
                                {
                                    total: data.per_day.reduce(
                                        (sum, day) => sum + day.count,
                                        0,
                                    ),
                                },
                            )}
                            caption={t('Submissions per day (30 days)')}
                            labelHeader={t('Date')}
                            valueHeader={t('Submissions')}
                        />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('Most active (30 days)')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <div>
                            <p className="mb-1 font-medium">{t('Teachers')}</p>
                            {data.top_teachers.length === 0 ? (
                                <p className="text-muted-foreground">—</p>
                            ) : (
                                <ol className="space-y-1">
                                    {data.top_teachers.map((row, index) => (
                                        <li
                                            key={index}
                                            className="flex justify-between gap-2"
                                        >
                                            <span>{row.name}</span>
                                            <span className="tabular text-muted-foreground">
                                                {row.count}
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </div>
                        <div>
                            <p className="mb-1 font-medium">{t('Tests')}</p>
                            {data.top_tests.length === 0 ? (
                                <p className="text-muted-foreground">—</p>
                            ) : (
                                <ol className="space-y-1">
                                    {data.top_tests.map((row) => (
                                        <li
                                            key={row.ulid}
                                            className="flex justify-between gap-2"
                                        >
                                            <Link
                                                href={admin.tests.analytics.show(
                                                    row.ulid,
                                                )}
                                                className="min-w-0 truncate underline-offset-4 hover:underline"
                                            >
                                                {row.title}
                                            </Link>
                                            <span className="tabular text-muted-foreground">
                                                {row.count}
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </section>
    );
}
