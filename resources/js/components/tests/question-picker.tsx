import { useHttp } from '@inertiajs/react';
import { FileQuestion, Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import { useRef, useState } from 'react';
import type { DataTableColumn } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import { EmptyState } from '@/components/data/empty-state';
import { SEARCH_DEBOUNCE_MS } from '@/components/data/filter-bar';
import { Pagination } from '@/components/data/pagination';
import {
    CategorySelect,
    DifficultyBadge,
    QuestionTypeBadge,
} from '@/components/questions/question-fields';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import teacher from '@/routes/teacher';
import type {
    Difficulty,
    Paginated,
    QuestionOptions,
    QuestionRow,
    QuestionType,
    TestQuestionRow,
} from '@/types';

type PickerFilters = {
    search: string;
    type: QuestionType | null;
    difficulty: Difficulty | null;
    category: string | null;
    page: number;
};

const ALL = '__all__';
const NO_FILTERS: PickerFilters = {
    search: '',
    type: null,
    difficulty: null,
    category: null,
    page: 1,
};

/**
 * Pick questions from the teacher's own bank (archived ones can't be added).
 * Questions already in the test are shown as added.
 */
export function QuestionPicker({
    trigger,
    attached,
    options,
    remaining,
    onAdd,
}: {
    /** The button that opens the picker. */
    trigger: ReactNode;
    /** ULIDs already in the test. */
    attached: string[];
    options: QuestionOptions;
    /** How many more questions fit. */
    remaining: number;
    onAdd: (questions: TestQuestionRow[]) => void;
}) {
    const { t } = useTranslation();
    const http = useHttp<
        Record<string, never>,
        { questions: Paginated<QuestionRow> }
    >({});
    const [open, setOpen] = useState(false);
    const [filters, setFilters] = useState<PickerFilters>(NO_FILTERS);
    const [result, setResult] = useState<Paginated<QuestionRow> | null>(null);
    const [selected, setSelected] = useState<QuestionRow[]>([]);
    const [failed, setFailed] = useState(false);
    const searchTimer = useRef<number | undefined>(undefined);

    const load = async (next: PickerFilters) => {
        setFailed(false);

        try {
            const response = await http.get(
                teacher.tests.questionPicker.url({
                    query: {
                        search: next.search === '' ? undefined : next.search,
                        type: next.type ?? undefined,
                        difficulty: next.difficulty ?? undefined,
                        category: next.category ?? undefined,
                        page: next.page,
                        per_page: 10,
                    },
                }),
            );
            setResult(response.questions);
        } catch {
            setFailed(true);
        }
    };

    const change = (changes: Partial<PickerFilters>) => {
        const next = { ...filters, page: 1, ...changes };
        setFilters(next);
        void load(next);
    };

    const changeSearch = (search: string) => {
        const next = { ...filters, search, page: 1 };
        setFilters(next);
        window.clearTimeout(searchTimer.current);
        searchTimer.current = window.setTimeout(
            () => void load(next),
            SEARCH_DEBOUNCE_MS,
        );
    };

    const toggle = (question: QuestionRow, checked: boolean) =>
        setSelected(
            checked
                ? [...selected, question]
                : selected.filter((q) => q.ulid !== question.ulid),
        );

    const handleOpenChange = (next: boolean) => {
        if (next) {
            setSelected([]);
            setFilters(NO_FILTERS);
            void load(NO_FILTERS);
        }

        setOpen(next);
    };

    const add = () => {
        onAdd(
            selected.slice(0, remaining).map((question) => ({
                ulid: question.ulid,
                excerpt: question.excerpt,
                type: question.type,
                difficulty: question.difficulty,
                points: question.points,
                points_override: null,
                archived: false,
            })),
        );
        setOpen(false);
    };

    const difficultyLabel = (value: Difficulty) =>
        options.difficulties.find((option) => option.value === value)?.label ??
        value;

    const isSelected = (question: QuestionRow) =>
        selected.some((q) => q.ulid === question.ulid);

    const columns: DataTableColumn<QuestionRow>[] = [
        {
            key: 'select',
            header: t('Select'),
            className: 'w-10',
            cell: (question) => {
                const isAttached = attached.includes(question.ulid);

                return (
                    <input
                        type="checkbox"
                        className="size-4 accent-primary"
                        disabled={isAttached}
                        checked={isAttached || isSelected(question)}
                        aria-label={t('Select question: :excerpt', {
                            excerpt: question.excerpt,
                        })}
                        onChange={(event) =>
                            toggle(question, event.target.checked)
                        }
                    />
                );
            },
        },
        {
            key: 'excerpt',
            header: t('Question'),
            primary: true,
            className: 'max-w-md',
            cell: (question) => (
                <div className="min-w-0 space-y-1">
                    <p className="line-clamp-2 break-words">
                        {question.excerpt}
                    </p>
                    {attached.includes(question.ulid) && (
                        <p className="text-xs text-muted-foreground">
                            {t('Already in the test')}
                        </p>
                    )}
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
            cell: (question) => (
                <DifficultyBadge
                    difficulty={question.difficulty}
                    label={difficultyLabel(question.difficulty)}
                />
            ),
        },
        {
            key: 'points',
            header: t('Points'),
            align: 'end',
            cell: (question) => (
                <span className="tabular">{question.points}</span>
            ),
        },
    ];

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{t('Add questions')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Questions from your bank. Archived questions are not listed.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-3 md:grid-cols-4" role="search">
                    <div className="grid gap-1.5 md:col-span-4">
                        <Label htmlFor="picker-search">{t('Search')}</Label>
                        <Input
                            id="picker-search"
                            type="search"
                            value={filters.search}
                            placeholder={t('Search question text')}
                            onChange={(event) =>
                                changeSearch(event.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="picker-type">{t('Type')}</Label>
                        <Select
                            value={filters.type ?? ALL}
                            onValueChange={(type) =>
                                change({
                                    type:
                                        type === ALL
                                            ? null
                                            : (type as QuestionType),
                                })
                            }
                        >
                            <SelectTrigger id="picker-type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>{t('All')}</SelectItem>
                                {options.types.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="picker-difficulty">
                            {t('Difficulty')}
                        </Label>
                        <Select
                            value={filters.difficulty ?? ALL}
                            onValueChange={(difficulty) =>
                                change({
                                    difficulty:
                                        difficulty === ALL
                                            ? null
                                            : (difficulty as Difficulty),
                                })
                            }
                        >
                            <SelectTrigger id="picker-difficulty">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>{t('All')}</SelectItem>
                                {options.difficulties.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5 md:col-span-2">
                        <Label htmlFor="picker-category">{t('Category')}</Label>
                        <CategorySelect
                            id="picker-category"
                            allowAll
                            value={filters.category}
                            categories={options.categories}
                            onChange={(category) => change({ category })}
                        />
                    </div>
                </div>

                {failed && (
                    <Alert variant="destructive">
                        <AlertDescription>
                            {t('The questions could not be loaded.')}
                        </AlertDescription>
                    </Alert>
                )}

                <div aria-busy={http.processing} className="min-h-40">
                    {result === null ? (
                        <div className="flex justify-center py-10">
                            <Spinner />
                        </div>
                    ) : result.data.length === 0 ? (
                        <EmptyState
                            icon={FileQuestion}
                            title={t('No questions found')}
                            description={t(
                                'Create questions in your bank or change the filters.',
                            )}
                        />
                    ) : (
                        <DataTable
                            caption={t('Add questions')}
                            columns={columns}
                            rows={result.data}
                            rowKey={(question) => question.ulid}
                            loading={http.processing}
                        />
                    )}
                </div>

                {result && (
                    <Pagination
                        meta={result.meta}
                        onPageChange={(page) => change({ page })}
                    />
                )}

                <DialogFooter className="items-center gap-2">
                    {selected.length > remaining && (
                        <p className="text-sm text-muted-foreground">
                            {t('Only :count more questions fit in a test.', {
                                count: remaining,
                            })}
                        </p>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOpen(false)}
                    >
                        {t('Cancel')}
                    </Button>
                    <Button
                        type="button"
                        disabled={selected.length === 0 || remaining === 0}
                        onClick={add}
                    >
                        <Plus aria-hidden="true" />
                        {t('Add :count questions', {
                            count: Math.min(selected.length, remaining),
                        })}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
