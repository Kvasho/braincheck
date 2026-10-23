import { Head, Link } from '@inertiajs/react';
import {
    ChartNoAxesColumn,
    CircleCheck,
    CircleX,
    Hourglass,
    EyeOff,
} from 'lucide-react';
import { SeriesBarChart, SeriesLineChart } from '@/components/charts/charts';
import type { DataTableColumn } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import { EmptyState } from '@/components/data/empty-state';
import { PageHeader } from '@/components/data/page-header';
import { Pagination } from '@/components/data/pagination';
import { StatusBadge } from '@/components/data/status-badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useListing } from '@/hooks/use-listing';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import student from '@/routes/student';
import type {
    ListingState,
    Paginated,
    ProgressPoint,
    Strength,
    StudentResultRow,
} from '@/types';

/**
 * The student's results. Scores and charts only from results the test
 * settings let the student see.
 */
export default function StudentResultsPage({
    results,
    filters,
    progress,
    strengths,
}: {
    results: Paginated<StudentResultRow>;
    filters: ListingState;
    progress: ProgressPoint[];
    strengths: Strength[];
}) {
    const { t, formatDate, locale } = useTranslation();
    const { update, loading } = useListing({
        query: filters,
        only: ['results', 'filters'],
    });
    const percent = (value: number) => `${value}%`;

    const columns: DataTableColumn<StudentResultRow>[] = [
        {
            key: 'test',
            header: t('Test'),
            primary: true,
            cell: (row) => (
                <Link
                    href={student.attempts.result(row.ulid)}
                    className="font-medium underline-offset-4 hover:underline"
                >
                    {row.test}
                </Link>
            ),
        },
        {
            key: 'submitted',
            header: t('Submitted'),
            sortKey: 'submitted_at',
            cell: (row) =>
                row.submitted_at ? formatDate(row.submitted_at, 'PPp') : '—',
        },
        {
            key: 'result',
            header: t('Result'),
            align: 'end',
            cell: (row) =>
                row.visible ? (
                    <span className="tabular font-medium">{row.percent}%</span>
                ) : row.awaiting_review ? (
                    <StatusBadge
                        tone="info"
                        icon={Hourglass}
                        label={t('Awaiting review')}
                    />
                ) : (
                    <StatusBadge
                        tone="neutral"
                        icon={EyeOff}
                        label={t('Not shown')}
                    />
                ),
        },
        {
            key: 'passed',
            header: t('Passed'),
            cell: (row) =>
                row.passed === null ? (
                    '—'
                ) : row.passed ? (
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
                ),
        },
    ];

    return (
        <>
            <Head title={t('Results')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Results')}
                    description={t(
                        'Your submitted exams. Scores appear as each test allows.',
                    )}
                />

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Progress')}
                            </CardTitle>
                            <CardDescription>
                                {t(
                                    'Score % of each visible result, over time.',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <SeriesLineChart
                                points={progress.map((point, index) => ({
                                    key: String(index),
                                    label: point.date
                                        ? formatDate(point.date, 'd MMM')
                                        : '',
                                    value: Number(point.percent),
                                    detail: point.test,
                                }))}
                                domain={[0, 100]}
                                format={percent}
                                summary={t(
                                    ':count results, shown as score % over time.',
                                    { count: progress.length },
                                )}
                                caption={t('Progress')}
                                labelHeader={t('Date')}
                                valueHeader={t('Score %')}
                                empty={
                                    <p className="text-sm text-muted-foreground">
                                        {t('No visible results yet.')}
                                    </p>
                                }
                            />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Strengths by category')}
                            </CardTitle>
                            <CardDescription>
                                {t('Average score % per category.')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <SeriesBarChart
                                layout="horizontal-bars"
                                points={strengths.map((row, index) => ({
                                    key: String(index),
                                    label: row.category
                                        ? row.category[locale]
                                        : t('No category'),
                                    value: Number(row.average),
                                    detail: t(':count results', {
                                        count: row.count,
                                    }),
                                }))}
                                domain={[0, 100]}
                                format={percent}
                                summary={t('Average score % per category.')}
                                caption={t('Strengths by category')}
                                labelHeader={t('Category')}
                                valueHeader={t('Average %')}
                                empty={
                                    <p className="text-sm text-muted-foreground">
                                        {t('No visible results yet.')}
                                    </p>
                                }
                            />
                        </CardContent>
                    </Card>
                </div>

                <DataTable
                    caption={t('Results')}
                    columns={columns}
                    rows={results.data}
                    rowKey={(row) => row.ulid}
                    loading={loading}
                    sort={{ key: filters.sort, direction: filters.direction }}
                    onSort={(sort, direction) => update({ sort, direction })}
                    empty={
                        <EmptyState
                            icon={ChartNoAxesColumn}
                            title={t('No results yet')}
                            description={t(
                                'Results of your submitted exams appear here.',
                            )}
                        />
                    }
                />

                <Pagination
                    meta={results.meta}
                    onPageChange={(page) => update({ page })}
                />
            </div>
        </>
    );
}

StudentResultsPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Results', href: student.results.index() },
    ],
};
