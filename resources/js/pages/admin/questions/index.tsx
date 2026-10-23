import { Head, Link } from '@inertiajs/react';
import { FileQuestion } from 'lucide-react';
import type { DataTableColumn } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import { EmptyState } from '@/components/data/empty-state';
import { FilterBar } from '@/components/data/filter-bar';
import { PageHeader } from '@/components/data/page-header';
import { Pagination } from '@/components/data/pagination';
import {
    CategorySelect,
    DifficultyBadge,
    QuestionTypeBadge,
} from '@/components/questions/question-fields';
import { Label } from '@/components/ui/label';
import { useListing } from '@/hooks/use-listing';
import { useTranslation } from '@/hooks/use-translation';
import type { QuestionFilters } from '@/pages/teacher/questions/index';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import type {
    Difficulty,
    Paginated,
    QuestionOptions,
    QuestionRow,
} from '@/types';

/**
 * Read-only browse of every teacher's questions (moderation).
 */
export default function AdminQuestionsIndex({
    questions,
    filters,
    options,
}: {
    questions: Paginated<QuestionRow>;
    filters: QuestionFilters;
    options: QuestionOptions;
}) {
    const { t, formatDate, locale } = useTranslation();
    const { update, loading } = useListing({
        query: filters,
        only: ['questions', 'filters'],
    });

    const difficultyLabel = (value: Difficulty) =>
        options.difficulties.find((option) => option.value === value)?.label ??
        value;

    const columns: DataTableColumn<QuestionRow>[] = [
        {
            key: 'excerpt',
            header: t('Question'),
            primary: true,
            className: 'max-w-md',
            cell: (question) => (
                <Link
                    href={admin.questions.show(question.ulid)}
                    className="line-clamp-2 font-medium break-words underline-offset-4 hover:underline"
                >
                    {question.excerpt}
                </Link>
            ),
        },
        {
            key: 'owner',
            header: t('Teacher'),
            cell: (question) => question.owner?.name ?? '—',
        },
        {
            key: 'type',
            header: t('Type'),
            cell: (question) => <QuestionTypeBadge type={question.type} />,
        },
        {
            key: 'difficulty',
            header: t('Difficulty'),
            sortKey: 'difficulty',
            cell: (question) => (
                <DifficultyBadge
                    difficulty={question.difficulty}
                    label={difficultyLabel(question.difficulty)}
                />
            ),
        },
        {
            key: 'category',
            header: t('Category'),
            cell: (question) => question.category?.name[locale] ?? '—',
        },
        {
            key: 'updated',
            header: t('Updated'),
            sortKey: 'updated_at',
            cell: (question) =>
                question.updated_at ? formatDate(question.updated_at) : '—',
        },
    ];

    return (
        <>
            <Head title={t('Questions')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Questions')}
                    description={t(
                        "Every teacher's questions, read-only, for moderation.",
                    )}
                />

                <FilterBar
                    search={{
                        value: filters.search,
                        onChange: (search) => update({ search }),
                        placeholder: t('Search question text'),
                    }}
                    filters={[
                        {
                            key: 'type',
                            label: t('Type'),
                            value: filters.type,
                            options: options.types,
                            onChange: (type) => update({ type }),
                        },
                        {
                            key: 'difficulty',
                            label: t('Difficulty'),
                            value: filters.difficulty,
                            options: options.difficulties,
                            onChange: (difficulty) => update({ difficulty }),
                        },
                        {
                            key: 'archived',
                            label: t('Archive'),
                            value: filters.archived,
                            options: [
                                { value: 'only', label: t('Archived only') },
                                {
                                    value: 'with',
                                    label: t('Including archived'),
                                },
                            ],
                            onChange: (archived) => update({ archived }),
                        },
                    ]}
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
                    caption={t('Questions')}
                    columns={columns}
                    rows={questions.data}
                    rowKey={(question) => question.ulid}
                    loading={loading}
                    sort={{ key: filters.sort, direction: filters.direction }}
                    onSort={(sort, direction) => update({ sort, direction })}
                    empty={
                        <EmptyState
                            icon={FileQuestion}
                            title={t('No questions found')}
                        />
                    }
                />

                <Pagination
                    meta={questions.meta}
                    onPageChange={(page) => update({ page })}
                />
            </div>
        </>
    );
}

AdminQuestionsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Questions', href: admin.questions.index() },
    ],
};
