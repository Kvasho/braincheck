import { Head, Link, router } from '@inertiajs/react';
import { CheckCheck, EyeOff, Eye, PenLine } from 'lucide-react';
import type { DataTableColumn } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import { EmptyState } from '@/components/data/empty-state';
import { FilterBar } from '@/components/data/filter-bar';
import { PageHeader } from '@/components/data/page-header';
import { Pagination } from '@/components/data/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useListing } from '@/hooks/use-listing';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import teacher from '@/routes/teacher';
import type {
    GradingQueueItem,
    ListingState,
    Option,
    Paginated,
} from '@/types';

type GradingFilters = ListingState & {
    test: string | null;
    group: string | null;
};

/**
 * Answers waiting for manual grading in the teacher's own tests, oldest
 * submission first. Anonymous mode (default) shows "Student #N".
 */
export default function GradingQueue({
    answers,
    counts,
    filters,
    options,
    anonymous,
}: {
    answers: Paginated<GradingQueueItem>;
    counts: { ulid: string; title: string; pending: number }[];
    filters: GradingFilters;
    options: { groups: Option[] };
    anonymous: boolean;
}) {
    const { t, formatDate } = useTranslation();
    const { update, loading } = useListing({
        query: filters,
        only: ['answers', 'filters', 'counts'],
    });

    const columns: DataTableColumn<GradingQueueItem>[] = [
        {
            key: 'question',
            header: t('Question'),
            primary: true,
            className: 'max-w-md',
            cell: (item) => (
                <div className="min-w-0 space-y-1">
                    <Link
                        href={teacher.grading.answers.show({
                            attempt: item.attempt,
                            position: item.position,
                        })}
                        className="line-clamp-2 font-medium break-words underline-offset-4 hover:underline"
                    >
                        {item.excerpt ||
                            t('Question :number', { number: item.position })}
                    </Link>
                    <p className="text-xs text-muted-foreground">
                        {item.test.title}
                    </p>
                </div>
            ),
        },
        { key: 'student', header: t('Student'), cell: (item) => item.student },
        {
            key: 'submitted',
            header: t('Submitted'),
            sortKey: 'submitted_at',
            cell: (item) =>
                item.submitted_at ? formatDate(item.submitted_at, 'PPp') : '—',
        },
    ];

    return (
        <>
            <Head title={t('Grading')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Grading')}
                    description={t('Free-text answers waiting for your grade.')}
                    actions={
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.put(
                                    teacher.grading.anonymity.update.url(),
                                    { anonymous: !anonymous },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {anonymous ? (
                                <Eye aria-hidden="true" />
                            ) : (
                                <EyeOff aria-hidden="true" />
                            )}
                            {anonymous
                                ? t('Show names')
                                : t('Grade anonymously')}
                        </Button>
                    }
                />

                {counts.length > 0 && (
                    <ul
                        className="flex flex-wrap gap-2"
                        aria-label={t('Waiting per test')}
                    >
                        {counts.map((count) => (
                            <li key={count.ulid}>
                                <button
                                    type="button"
                                    onClick={() =>
                                        update({
                                            test:
                                                filters.test === count.ulid
                                                    ? null
                                                    : count.ulid,
                                        })
                                    }
                                    aria-pressed={filters.test === count.ulid}
                                    className="rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <Badge
                                        variant={
                                            filters.test === count.ulid
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {count.title}{' '}
                                        <span className="tabular">
                                            · {count.pending}
                                        </span>
                                    </Badge>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}

                <FilterBar
                    filters={[
                        {
                            key: 'test',
                            label: t('Test'),
                            value: filters.test,
                            options: counts.map((count) => ({
                                value: count.ulid,
                                label: count.title,
                            })),
                            onChange: (test) => update({ test }),
                        },
                        {
                            key: 'group',
                            label: t('Group'),
                            value: filters.group,
                            options: options.groups,
                            onChange: (group) => update({ group }),
                        },
                    ]}
                    onReset={() => update({ test: null, group: null })}
                />

                <DataTable
                    caption={t('Answers waiting for grading')}
                    columns={columns}
                    rows={answers.data}
                    rowKey={(item) => `${item.attempt}-${item.position}`}
                    loading={loading}
                    sort={{ key: filters.sort, direction: filters.direction }}
                    onSort={(sort, direction) => update({ sort, direction })}
                    rowActions={(item) => (
                        <div className="flex justify-end">
                            <Button asChild size="sm">
                                <Link
                                    href={teacher.grading.answers.show({
                                        attempt: item.attempt,
                                        position: item.position,
                                    })}
                                >
                                    <PenLine aria-hidden="true" />
                                    {t('Grade')}
                                </Link>
                            </Button>
                        </div>
                    )}
                    empty={
                        <EmptyState
                            icon={CheckCheck}
                            title={t('Nothing to grade')}
                            description={t('All free-text answers are graded.')}
                        />
                    }
                />

                <Pagination
                    meta={answers.meta}
                    onPageChange={(page) => update({ page })}
                />
            </div>
        </>
    );
}

GradingQueue.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Grading', href: teacher.grading.index() },
    ],
};
