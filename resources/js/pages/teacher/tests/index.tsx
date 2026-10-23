import { Head, Link, router } from '@inertiajs/react';
import { Archive, ClipboardList, Copy, Lock, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/data/confirm-dialog';
import type { DataTableColumn } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import { EmptyState } from '@/components/data/empty-state';
import { FilterBar } from '@/components/data/filter-bar';
import { PageHeader } from '@/components/data/page-header';
import { Pagination } from '@/components/data/pagination';
import { CategorySelect } from '@/components/questions/question-fields';
import { TestStatusBadge } from '@/components/tests/test-status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useListing } from '@/hooks/use-listing';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import teacher from '@/routes/teacher';
import type {
    ListingState,
    Paginated,
    TestOptions,
    TestRow,
    TestStatus,
} from '@/types';

type TestFilters = ListingState & {
    status: TestStatus | null;
    category: string | null;
};

export default function TestsIndex({
    tests,
    filters,
    options,
}: {
    tests: Paginated<TestRow>;
    filters: TestFilters;
    options: TestOptions;
}) {
    const { t, formatDate, locale } = useTranslation();
    const { update, loading } = useListing({
        query: filters,
        only: ['tests', 'filters'],
    });
    const [archiving, setArchiving] = useState<TestRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const availability = (test: TestRow) => {
        if (test.opens_at === null && test.closes_at === null) {
            return t('Always open');
        }

        const from = test.opens_at ? formatDate(test.opens_at, 'PPp') : '…';
        const to = test.closes_at ? formatDate(test.closes_at, 'PPp') : '…';

        return `${from} – ${to}`;
    };

    const columns: DataTableColumn<TestRow>[] = [
        {
            key: 'title',
            header: t('Title'),
            sortKey: 'title',
            primary: true,
            className: 'max-w-md',
            cell: (test) => (
                <div className="min-w-0 space-y-1">
                    <Link
                        href={teacher.tests.edit(test.ulid)}
                        className="line-clamp-2 font-medium break-words underline-offset-4 hover:underline"
                    >
                        {test.title}
                    </Link>
                    {test.category && (
                        <p className="text-xs text-muted-foreground">
                            {test.category.name[locale]}
                        </p>
                    )}
                </div>
            ),
        },
        {
            key: 'status',
            header: t('Status'),
            cell: (test) => (
                <div className="flex flex-wrap items-center gap-1">
                    <TestStatusBadge status={test.status} />
                    {test.locked && (
                        <Lock
                            className="size-4 text-muted-foreground"
                            aria-label={t('Locked')}
                        />
                    )}
                </div>
            ),
        },
        {
            key: 'questions',
            header: t('Questions'),
            align: 'end',
            cell: (test) => (
                <span className="tabular">{test.questions_count}</span>
            ),
        },
        {
            key: 'groups',
            header: t('Groups'),
            align: 'end',
            cell: (test) => (
                <span className="tabular">{test.groups_count}</span>
            ),
        },
        {
            key: 'window',
            header: t('Availability'),
            sortKey: 'opens_at',
            cell: (test) => (
                <span className="text-sm">{availability(test)}</span>
            ),
        },
        {
            key: 'attempts',
            header: t('Attempts'),
            align: 'end',
            cell: (test) => (
                <span className="tabular">{test.attempts_count}</span>
            ),
        },
        {
            key: 'updated',
            header: t('Updated'),
            sortKey: 'updated_at',
            cell: (test) =>
                test.updated_at ? formatDate(test.updated_at) : '—',
        },
    ];

    return (
        <>
            <Head title={t('Tests')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Tests')}
                    description={t(
                        'Build tests from your question bank, preview them and publish.',
                    )}
                    actions={
                        <Button asChild>
                            <Link href={teacher.tests.create()}>
                                <Plus aria-hidden="true" />
                                {t('New test')}
                            </Link>
                        </Button>
                    }
                />

                <FilterBar
                    search={{
                        value: filters.search,
                        onChange: (search) => update({ search }),
                        placeholder: t('Search titles'),
                    }}
                    filters={[
                        {
                            key: 'status',
                            label: t('Status'),
                            value: filters.status,
                            options: options.statuses,
                            onChange: (status) => update({ status }),
                        },
                    ]}
                    onReset={() =>
                        update({ search: null, status: null, category: null })
                    }
                >
                    <div className="grid gap-1.5 md:w-56">
                        <Label htmlFor="filter-category">{t('Category')}</Label>
                        <CategorySelect
                            id="filter-category"
                            allowAll
                            value={filters.category}
                            categories={options.categories}
                            onChange={(category) => update({ category })}
                        />
                    </div>
                </FilterBar>

                <DataTable
                    caption={t('Tests')}
                    columns={columns}
                    rows={tests.data}
                    rowKey={(test) => test.ulid}
                    loading={loading}
                    sort={{ key: filters.sort, direction: filters.direction }}
                    onSort={(sort, direction) => update({ sort, direction })}
                    rowActions={(test) => (
                        <div className="flex justify-end gap-1">
                            {test.status !== 'archived' && (
                                <Button asChild variant="ghost" size="icon">
                                    <Link
                                        href={teacher.tests.edit(test.ulid)}
                                        aria-label={t('Edit')}
                                    >
                                        <Pencil aria-hidden="true" />
                                    </Link>
                                </Button>
                            )}
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('Duplicate')}
                                onClick={() =>
                                    router.post(
                                        teacher.tests.duplicate.url(test.ulid),
                                    )
                                }
                            >
                                <Copy aria-hidden="true" />
                            </Button>
                            {test.status !== 'archived' && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Archive')}
                                    onClick={() => setArchiving(test)}
                                >
                                    <Archive aria-hidden="true" />
                                </Button>
                            )}
                        </div>
                    )}
                    empty={
                        <EmptyState
                            icon={ClipboardList}
                            title={t('No tests found')}
                            description={t(
                                'Create your first test or change the filters.',
                            )}
                            action={
                                <Button asChild variant="outline">
                                    <Link href={teacher.tests.create()}>
                                        {t('New test')}
                                    </Link>
                                </Button>
                            }
                        />
                    }
                />

                <Pagination
                    meta={tests.meta}
                    onPageChange={(page) => update({ page })}
                />
            </div>

            <ConfirmDialog
                open={archiving !== null}
                onOpenChange={(next) => !next && setArchiving(null)}
                title={t('Archive this test?')}
                description={t(
                    'Students can no longer start it. Results and certificates stay.',
                )}
                confirmLabel={t('Archive')}
                destructive
                processing={processing}
                onConfirm={() =>
                    archiving &&
                    router.post(
                        teacher.tests.archive.url(archiving.ulid),
                        {},
                        {
                            preserveScroll: true,
                            onStart: () => setProcessing(true),
                            onFinish: () => setProcessing(false),
                            onSuccess: () => setArchiving(null),
                        },
                    )
                }
            />
        </>
    );
}

TestsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Tests', href: teacher.tests.index() },
    ],
};
