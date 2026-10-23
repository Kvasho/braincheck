import { Head, Link, router } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    Copy,
    Eye,
    FileQuestion,
    ImageIcon,
    Pencil,
    Plus,
    Tags,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/data/confirm-dialog';
import type { DataTableColumn } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import { EmptyState } from '@/components/data/empty-state';
import { FilterBar } from '@/components/data/filter-bar';
import { MultiSelectFilter } from '@/components/data/multi-select-filter';
import { PageHeader } from '@/components/data/page-header';
import { Pagination } from '@/components/data/pagination';
import { StatusBadge } from '@/components/data/status-badge';
import {
    CategorySelect,
    DifficultyBadge,
    QuestionTypeBadge,
    TagPicker,
} from '@/components/questions/question-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useListing } from '@/hooks/use-listing';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import teacher from '@/routes/teacher';
import type {
    Difficulty,
    ListingState,
    Paginated,
    QuestionOptions,
    QuestionRow,
    QuestionType,
} from '@/types';

export type QuestionFilters = ListingState & {
    category: string | null;
    tags: string[];
    type: QuestionType | null;
    difficulty: Difficulty | null;
    archived: 'only' | 'with' | null;
};

type Bulk = 'tag' | 'untag' | 'archive' | null;

export default function QuestionsIndex({
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
    const [selected, setSelected] = useState<string[]>([]);
    const [bulk, setBulk] = useState<Bulk>(null);
    const [bulkTags, setBulkTags] = useState<string[]>([]);
    const [archiving, setArchiving] = useState<QuestionRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const difficultyLabel = (value: Difficulty) =>
        options.difficulties.find((option) => option.value === value)?.label ??
        value;

    const pageUlids = questions.data
        .filter((q) => !q.archived)
        .map((q) => q.ulid);
    const allSelected =
        pageUlids.length > 0 &&
        pageUlids.every((ulid) => selected.includes(ulid));

    const visit = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
        onSuccess: () => {
            setBulk(null);
            setArchiving(null);
            setSelected([]);
            setBulkTags([]);
        },
    };

    const columns: DataTableColumn<QuestionRow>[] = [
        {
            key: 'select',
            header: t('Select'),
            className: 'w-10',
            cell: (question) =>
                question.archived ? null : (
                    <input
                        type="checkbox"
                        className="size-4 accent-primary"
                        checked={selected.includes(question.ulid)}
                        aria-label={t('Select question: :excerpt', {
                            excerpt: question.excerpt,
                        })}
                        onChange={(event) =>
                            setSelected(
                                event.target.checked
                                    ? [...selected, question.ulid]
                                    : selected.filter(
                                          (ulid) => ulid !== question.ulid,
                                      ),
                            )
                        }
                    />
                ),
        },
        {
            key: 'excerpt',
            header: t('Question'),
            primary: true,
            className: 'max-w-md',
            cell: (question) => (
                <div className="min-w-0 space-y-1">
                    <Link
                        href={
                            question.archived
                                ? teacher.questions.preview(question.ulid)
                                : teacher.questions.edit(question.ulid)
                        }
                        className="line-clamp-2 font-medium break-words underline-offset-4 hover:underline"
                    >
                        {question.excerpt}
                    </Link>
                    <div className="flex flex-wrap gap-1">
                        {question.archived && (
                            <StatusBadge
                                tone="neutral"
                                icon={Archive}
                                label={t('Archived')}
                            />
                        )}
                        {question.has_image && (
                            <Badge variant="outline">
                                <ImageIcon aria-hidden="true" />
                                {t('Image')}
                            </Badge>
                        )}
                        {question.tags.map((tag) => (
                            <Badge key={tag.ulid} variant="secondary">
                                {tag.name}
                            </Badge>
                        ))}
                    </div>
                </div>
            ),
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
            key: 'tests',
            header: t('In tests'),
            align: 'end',
            cell: (question) => (
                <span className="tabular">{question.tests_count}</span>
            ),
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
            <Head title={t('Question bank')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Question bank')}
                    description={t(
                        'Your questions. Only you can see and change them.',
                    )}
                    actions={
                        <Button asChild>
                            <Link href={teacher.questions.create()}>
                                <Plus aria-hidden="true" />
                                {t('New question')}
                            </Link>
                        </Button>
                    }
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
                    onReset={() =>
                        update({
                            search: null,
                            type: null,
                            difficulty: null,
                            archived: null,
                            category: null,
                            tags: [],
                        })
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
                    <MultiSelectFilter
                        id="filter-tags"
                        label={t('Tags')}
                        values={filters.tags}
                        options={options.tags}
                        onChange={(tags) => update({ tags })}
                    />
                </FilterBar>

                {selected.length > 0 && (
                    <div
                        className="flex flex-wrap items-center gap-2 rounded-xl border bg-muted/40 p-3"
                        role="region"
                        aria-label={t('Bulk actions')}
                    >
                        <span className="tabular text-sm font-medium">
                            {t(':count selected', { count: selected.length })}
                        </span>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setBulk('tag')}
                        >
                            <Tags aria-hidden="true" />
                            {t('Add tags')}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setBulk('untag')}
                        >
                            {t('Remove tags')}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setBulk('archive')}
                        >
                            <Archive aria-hidden="true" />
                            {t('Archive')}
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setSelected([])}
                        >
                            {t('Clear selection')}
                        </Button>
                    </div>
                )}

                <div className="flex items-center gap-2 text-sm">
                    <input
                        id="select-page"
                        type="checkbox"
                        className="size-4 accent-primary"
                        checked={allSelected}
                        disabled={pageUlids.length === 0}
                        onChange={(event) =>
                            setSelected(
                                event.target.checked
                                    ? [...new Set([...selected, ...pageUlids])]
                                    : selected.filter(
                                          (ulid) => !pageUlids.includes(ulid),
                                      ),
                            )
                        }
                    />
                    <label htmlFor="select-page">
                        {t('Select all on this page')}
                    </label>
                </div>

                <DataTable
                    caption={t('Question bank')}
                    columns={columns}
                    rows={questions.data}
                    rowKey={(question) => question.ulid}
                    loading={loading}
                    sort={{ key: filters.sort, direction: filters.direction }}
                    onSort={(sort, direction) => update({ sort, direction })}
                    rowActions={(question) => (
                        <div className="flex justify-end gap-1">
                            <Button asChild variant="ghost" size="icon">
                                <Link
                                    href={teacher.questions.preview(
                                        question.ulid,
                                    )}
                                    aria-label={t('Preview')}
                                >
                                    <Eye aria-hidden="true" />
                                </Link>
                            </Button>
                            {question.archived ? (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Restore')}
                                    onClick={() =>
                                        router.post(
                                            teacher.questions.restore.url(
                                                question.ulid,
                                            ),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <ArchiveRestore aria-hidden="true" />
                                </Button>
                            ) : (
                                <>
                                    <Button asChild variant="ghost" size="icon">
                                        <Link
                                            href={teacher.questions.edit(
                                                question.ulid,
                                            )}
                                            aria-label={t('Edit')}
                                        >
                                            <Pencil aria-hidden="true" />
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Duplicate')}
                                        onClick={() =>
                                            router.post(
                                                teacher.questions.duplicate.url(
                                                    question.ulid,
                                                ),
                                            )
                                        }
                                    >
                                        <Copy aria-hidden="true" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Archive')}
                                        onClick={() => setArchiving(question)}
                                    >
                                        <Archive aria-hidden="true" />
                                    </Button>
                                </>
                            )}
                        </div>
                    )}
                    empty={
                        <EmptyState
                            icon={FileQuestion}
                            title={t('No questions found')}
                            description={t(
                                'Create your first question or change the filters.',
                            )}
                            action={
                                <Button asChild variant="outline">
                                    <Link href={teacher.questions.create()}>
                                        {t('New question')}
                                    </Link>
                                </Button>
                            }
                        />
                    }
                />

                <Pagination
                    meta={questions.meta}
                    onPageChange={(page) => update({ page })}
                />
            </div>

            <ConfirmDialog
                open={archiving !== null}
                onOpenChange={(next) => !next && setArchiving(null)}
                title={t('Archive this question?')}
                description={
                    (archiving?.tests_count ?? 0) > 0
                        ? t(
                              'It stays in the :count test(s) that use it until you remove it there. You can restore it any time.',
                              { count: archiving?.tests_count ?? 0 },
                          )
                        : t('You can restore it any time.')
                }
                confirmLabel={t('Archive')}
                processing={processing}
                onConfirm={() =>
                    archiving &&
                    router.delete(
                        teacher.questions.destroy.url(archiving.ulid),
                        visit,
                    )
                }
            />

            <ConfirmDialog
                open={bulk !== null}
                onOpenChange={(next) => !next && setBulk(null)}
                title={
                    bulk === 'archive'
                        ? t('Archive :count questions?', {
                              count: selected.length,
                          })
                        : bulk === 'tag'
                          ? t('Add tags to :count questions', {
                                count: selected.length,
                            })
                          : t('Remove tags from :count questions', {
                                count: selected.length,
                            })
                }
                confirmLabel={bulk === 'archive' ? t('Archive') : t('Apply')}
                canConfirm={bulk === 'archive' || bulkTags.length > 0}
                processing={processing}
                onConfirm={() =>
                    router.post(
                        teacher.questions.bulk.url(),
                        {
                            action: bulk,
                            questions: selected,
                            ...(bulk === 'archive' ? {} : { tags: bulkTags }),
                        },
                        visit,
                    )
                }
            >
                {bulk !== 'archive' && (
                    <div className="grid gap-2">
                        <Label htmlFor="bulk-tags">{t('Tags')}</Label>
                        <TagPicker
                            id="bulk-tags"
                            value={bulkTags}
                            options={options.tags}
                            onChange={setBulkTags}
                        />
                    </div>
                )}
            </ConfirmDialog>
        </>
    );
}

QuestionsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Question bank', href: teacher.questions.index() },
    ],
};
