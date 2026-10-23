import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    Flag,
    RefreshCcw,
    Send,
    TriangleAlert,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { MetricHelp } from '@/components/analytics/metric-help';
import { SeriesBarChart } from '@/components/charts/charts';
import { ConfirmDialog } from '@/components/data/confirm-dialog';
import type { DataTableColumn } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import { EmptyState } from '@/components/data/empty-state';
import { FilterBar } from '@/components/data/filter-bar';
import { PageHeader } from '@/components/data/page-header';
import { Pagination } from '@/components/data/pagination';
import { StatusBadge } from '@/components/data/status-badge';
import { QuestionTypeBadge } from '@/components/questions/question-fields';
import { Badge } from '@/components/ui/badge';
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
import { formatDuration } from '@/hooks/use-server-clock';
import { useListing } from '@/hooks/use-listing';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import teacher from '@/routes/teacher';
import type {
    AnalyticsAttemptRow,
    AnalyticsOverview,
    GroupComparison,
    ListingState,
    Option,
    Paginated,
    QuestionStat,
} from '@/types';

type AnalyticsFilters = ListingState & {
    group: string | null;
    from: string | null;
    to: string | null;
};

const percentOf = (share: string | null) =>
    share === null ? '—' : `${(Number(share) * 100).toFixed(1)}%`;

/**
 * Test analytics (owner; admins in the admin area). Real, graded attempts
 * only. See docs/analytics.md for every metric.
 */
export default function TestAnalyticsPage({
    test,
    overview,
    groups,
    questions,
    attemptRows,
    filters,
    options,
    area,
    canRelease,
}: {
    test: {
        ulid: string;
        title: string;
        results_visibility: string;
        results_released_at: string | null;
    };
    overview: AnalyticsOverview;
    groups: GroupComparison[];
    questions: QuestionStat[];
    attemptRows: Paginated<AnalyticsAttemptRow>;
    filters: AnalyticsFilters;
    options: { groups: Option[] };
    area: 'teacher' | 'admin';
    canRelease: boolean;
}) {
    const { t, formatDate } = useTranslation();
    const routes = area === 'admin' ? admin.tests : teacher.tests;
    const { update, loading } = useListing({
        query: filters,
        only: ['attemptRows', 'filters', 'overview'],
    });
    const [releasing, setReleasing] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [open, setOpen] = useState<string | null>(null);

    const attemptColumns: DataTableColumn<AnalyticsAttemptRow>[] = [
        {
            key: 'student',
            header: t('Student'),
            primary: true,
            cell: (row) => (
                <Link
                    href={routes.analytics.attempts.show({
                        test: test.ulid,
                        attempt: row.ulid,
                    })}
                    className="font-medium underline-offset-4 hover:underline"
                >
                    {row.student.name}
                </Link>
            ),
        },
        {
            key: 'score',
            header: t('Score'),
            sortKey: 'score_percent',
            align: 'end',
            cell: (row) => (
                <span className="tabular">
                    {row.percent === null ? '—' : `${row.percent}%`}
                </span>
            ),
        },
        {
            key: 'status',
            header: t('Status'),
            cell: (row) => (
                <Badge variant="secondary">
                    {row.status === 'in_progress'
                        ? t('In progress')
                        : row.grading_status === 'pending_manual'
                          ? t('Awaiting review')
                          : row.status === 'timed_out'
                            ? t('Timed out')
                            : t('Submitted')}
                </Badge>
            ),
        },
        {
            key: 'duration',
            header: t('Duration'),
            align: 'end',
            cell: (row) => (
                <span className="tabular">
                    {row.duration_seconds === null
                        ? '—'
                        : formatDuration(row.duration_seconds)}
                </span>
            ),
        },
        {
            key: 'events',
            header: t('Integrity events'),
            align: 'end',
            cell: (row) =>
                row.severity === 'none' ? (
                    <span className="tabular text-muted-foreground">0</span>
                ) : (
                    <StatusBadge
                        tone={row.severity === 'many' ? 'danger' : 'warning'}
                        icon={Flag}
                        label={t(':count events', { count: row.events })}
                    />
                ),
        },
        {
            key: 'submitted',
            header: t('Submitted'),
            sortKey: 'submitted_at',
            cell: (row) =>
                row.submitted_at ? formatDate(row.submitted_at, 'PPp') : '—',
        },
    ];

    const stats: { label: string; value: string; help?: string }[] = [
        { label: t('Graded attempts'), value: String(overview.attempt_count) },
        { label: t('Students'), value: String(overview.students) },
        {
            label: t('Average'),
            value: overview.average === null ? '—' : `${overview.average}%`,
        },
        {
            label: t('Median'),
            value: overview.median === null ? '—' : `${overview.median}%`,
            help: t(
                'The middle score: half the attempts scored less, half more.',
            ),
        },
        {
            label: t('Standard deviation'),
            value: overview.standard_deviation ?? '—',
            help: t(
                'How spread out the scores are, in percentage points. Small: most scores are close to the average.',
            ),
        },
        { label: t('Pass rate'), value: percentOf(overview.pass_rate) },
        {
            label: t('Average duration'),
            value:
                overview.average_duration_seconds === null
                    ? '—'
                    : formatDuration(overview.average_duration_seconds),
        },
    ];

    return (
        <>
            <Head title={t('Analytics')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Analytics: :title', { title: test.title })}
                    description={t(
                        'Real, graded attempts only. Teacher previews never count.',
                    )}
                    actions={
                        <>
                            {area === 'teacher' && (
                                <Button asChild variant="ghost">
                                    <Link href={teacher.tests.edit(test.ulid)}>
                                        <ArrowLeft aria-hidden="true" />
                                        {t('Test builder')}
                                    </Link>
                                </Button>
                            )}
                            <Button asChild variant="outline">
                                <a href={routes.results.export.url(test.ulid)}>
                                    <Download aria-hidden="true" />
                                    {t('Export CSV')}
                                </a>
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        routes.analytics.recompute.url(
                                            test.ulid,
                                        ),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <RefreshCcw aria-hidden="true" />
                                {t('Recompute')}
                            </Button>
                            {canRelease &&
                                test.results_visibility === 'manual' &&
                                test.results_released_at === null && (
                                    <Button onClick={() => setReleasing(true)}>
                                        <Send aria-hidden="true" />
                                        {t('Release results')}
                                    </Button>
                                )}
                        </>
                    }
                />

                {test.results_visibility === 'manual' &&
                    test.results_released_at && (
                        <p className="text-sm text-muted-foreground">
                            {t('Results were released :date.', {
                                date: formatDate(
                                    test.results_released_at,
                                    'PPp',
                                ),
                            })}
                        </p>
                    )}

                <FilterBar
                    filters={[
                        {
                            key: 'group',
                            label: t('Group'),
                            value: filters.group,
                            options: options.groups,
                            onChange: (group) => update({ group }),
                        },
                    ]}
                    onReset={() =>
                        update({ group: null, from: null, to: null })
                    }
                >
                    <div className="grid gap-1.5">
                        <Label htmlFor="from">{t('From')}</Label>
                        <Input
                            id="from"
                            type="date"
                            value={filters.from ?? ''}
                            onChange={(event) =>
                                update({ from: event.target.value || null })
                            }
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="to">{t('To')}</Label>
                        <Input
                            id="to"
                            type="date"
                            value={filters.to ?? ''}
                            onChange={(event) =>
                                update({ to: event.target.value || null })
                            }
                        />
                    </div>
                </FilterBar>

                <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                    {stats.map((stat) => (
                        <Card key={stat.label} className="gap-2 py-4">
                            <CardContent className="space-y-1 px-4">
                                <dt className="flex items-center gap-1 text-xs text-muted-foreground">
                                    {stat.label}
                                    {stat.help && (
                                        <MetricHelp
                                            label={t('About :metric', {
                                                metric: stat.label,
                                            })}
                                            text={stat.help}
                                        />
                                    )}
                                </dt>
                                <dd className="tabular text-xl font-semibold">
                                    {stat.value}
                                </dd>
                            </CardContent>
                        </Card>
                    ))}
                </dl>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Score distribution')}
                            </CardTitle>
                            <CardDescription>
                                {t('Graded attempts per 10 % band.')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <SeriesBarChart
                                points={overview.distribution.map((bucket) => ({
                                    key: String(bucket.from),
                                    label: `${bucket.from}–${bucket.to}`,
                                    value: bucket.count,
                                }))}
                                summary={t(
                                    'Number of graded attempts per 10 % score band.',
                                )}
                                caption={t('Score distribution')}
                                labelHeader={t('Score %')}
                                valueHeader={t('Attempts')}
                            />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('Groups')}
                            </CardTitle>
                            <CardDescription>
                                {t('Average score % per assigned group.')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <SeriesBarChart
                                layout="horizontal-bars"
                                points={groups
                                    .filter((group) => group.average !== null)
                                    .map((group) => ({
                                        key: group.ulid,
                                        label: group.name,
                                        value: Number(group.average),
                                        detail: t(':count graded attempts', {
                                            count: group.responses,
                                        }),
                                    }))}
                                domain={[0, 100]}
                                format={(value) => `${value}%`}
                                summary={t(
                                    'Average score % per assigned group.',
                                )}
                                caption={t('Groups')}
                                labelHeader={t('Group')}
                                valueHeader={t('Average %')}
                                empty={
                                    <EmptyState
                                        icon={Users}
                                        title={t('No group results yet')}
                                    />
                                }
                            />
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('Questions')}
                        </CardTitle>
                        <CardDescription>
                            {questions[0]
                                ? t('Computed :date.', {
                                      date: formatDate(
                                          questions[0].computed_at,
                                          'PPp',
                                      ),
                                  })
                                : t('Appears once attempts are graded.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        {questions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('No graded attempts yet.')}
                            </p>
                        ) : (
                            <table className="w-full text-sm">
                                <caption className="sr-only">
                                    {t('Question statistics')}
                                </caption>
                                <thead className="text-left text-xs text-muted-foreground">
                                    <tr className="border-b">
                                        <th scope="col" className="p-2">
                                            {t('Question')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="p-2 text-right"
                                        >
                                            <span className="inline-flex items-center gap-1">
                                                {t('Difficulty')}
                                                <MetricHelp
                                                    label={t(
                                                        'About difficulty',
                                                    )}
                                                    text={t(
                                                        'Share of points students got on this question (0–100 %). High: easy; low: hard.',
                                                    )}
                                                />
                                            </span>
                                        </th>
                                        <th
                                            scope="col"
                                            className="p-2 text-right"
                                        >
                                            <span className="inline-flex items-center gap-1">
                                                {t('Discrimination')}
                                                <MetricHelp
                                                    label={t(
                                                        'About discrimination',
                                                    )}
                                                    text={t(
                                                        'How well the question separates strong from weak students: the top 27 % minus the bottom 27 %. Below 0.2 the question needs review.',
                                                    )}
                                                />
                                            </span>
                                        </th>
                                        <th
                                            scope="col"
                                            className="p-2 text-right"
                                        >
                                            {t('Average points')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="p-2 text-right"
                                        >
                                            {t('Manual grades')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="p-2 text-right"
                                        >
                                            {t('Responses')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {questions.map((question) => (
                                        <QuestionRow
                                            key={question.question}
                                            question={question}
                                            open={open === question.question}
                                            onToggle={() =>
                                                setOpen(
                                                    open === question.question
                                                        ? null
                                                        : question.question,
                                                )
                                            }
                                        />
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>

                <div className="space-y-3">
                    <h2 className="text-lg font-semibold">{t('Attempts')}</h2>
                    <DataTable
                        caption={t('Attempts')}
                        columns={attemptColumns}
                        rows={attemptRows.data}
                        rowKey={(row) => row.ulid}
                        loading={loading}
                        sort={{
                            key: filters.sort,
                            direction: filters.direction,
                        }}
                        onSort={(sort, direction) =>
                            update({ sort, direction })
                        }
                        empty={
                            <EmptyState
                                icon={Users}
                                title={t('No attempts yet')}
                            />
                        }
                    />
                    <Pagination
                        meta={attemptRows.meta}
                        onPageChange={(page) => update({ page })}
                    />
                </div>
            </div>

            <ConfirmDialog
                open={releasing}
                onOpenChange={setReleasing}
                title={t('Release the results?')}
                description={t(
                    'Students see their graded scores from now on. This can not be undone.',
                )}
                confirmLabel={t('Release results')}
                processing={processing}
                onConfirm={() =>
                    router.post(
                        teacher.tests.results.release.url(test.ulid),
                        {},
                        {
                            onStart: () => setProcessing(true),
                            onFinish: () => setProcessing(false),
                            onSuccess: () => setReleasing(false),
                        },
                    )
                }
            />
        </>
    );
}

function QuestionRow({
    question,
    open,
    onToggle,
}: {
    question: QuestionStat;
    open: boolean;
    onToggle: () => void;
}) {
    const { t } = useTranslation();

    return (
        <>
            <tr className="border-b align-top">
                <td className="max-w-md p-2">
                    <div className="space-y-1">
                        <p className="line-clamp-2 break-words">
                            {question.excerpt}
                        </p>
                        <div className="flex flex-wrap items-center gap-1">
                            <QuestionTypeBadge type={question.type} />
                            {question.needs_review && (
                                <StatusBadge
                                    tone="warning"
                                    icon={TriangleAlert}
                                    label={t('Review this question')}
                                />
                            )}
                            {question.distractors && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={onToggle}
                                    aria-expanded={open}
                                >
                                    {open
                                        ? t('Hide options')
                                        : t('Show options')}
                                </Button>
                            )}
                        </div>
                    </div>
                </td>
                <td className="tabular p-2 text-right">
                    {percentOf(question.difficulty)}
                </td>
                <td className="tabular p-2 text-right">
                    {question.discrimination ?? '—'}
                </td>
                <td className="tabular p-2 text-right">
                    {question.average_points ?? '—'}
                </td>
                <td className="tabular p-2 text-right">
                    {question.manual_grades}
                </td>
                <td className="tabular p-2 text-right">{question.responses}</td>
            </tr>
            {open && question.distractors && (
                <tr className="border-b bg-muted/20">
                    <td colSpan={6} className="p-3">
                        <table className="w-full text-xs">
                            <caption className="sr-only">
                                {t('Option analysis')}
                            </caption>
                            <thead className="text-left text-muted-foreground">
                                <tr>
                                    <th scope="col" className="p-1">
                                        {t('Option')}
                                    </th>
                                    <th scope="col" className="p-1 text-right">
                                        {t('Picked')}
                                    </th>
                                    <th scope="col" className="p-1 text-right">
                                        {t('Top 27 %')}
                                    </th>
                                    <th scope="col" className="p-1 text-right">
                                        {t('Bottom 27 %')}
                                    </th>
                                    <th scope="col" className="p-1">
                                        {t('Notes')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {question.distractors.map((option) => (
                                    <tr key={option.id}>
                                        <td className="p-1 break-words">
                                            {option.text}{' '}
                                            {option.correct && (
                                                <Badge variant="success">
                                                    {t('Correct')}
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="tabular p-1 text-right">
                                            {option.picks}
                                        </td>
                                        <td className="tabular p-1 text-right">
                                            {option.upper}
                                        </td>
                                        <td className="tabular p-1 text-right">
                                            {option.lower}
                                        </td>
                                        <td className="p-1">
                                            {option.flags.includes(
                                                'never_picked',
                                            ) && (
                                                <span>
                                                    {t('Nobody picks it')}{' '}
                                                </span>
                                            )}
                                            {option.flags.includes(
                                                'strong_pick',
                                            ) && (
                                                <span className="font-medium">
                                                    {t(
                                                        'Strong students pick it: check the wording',
                                                    )}
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </td>
                </tr>
            )}
        </>
    );
}

TestAnalyticsPage.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
