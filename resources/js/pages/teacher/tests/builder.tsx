import { Head, Link, router } from '@inertiajs/react';
import { formatDistance, formatDistanceStrict } from 'date-fns';
import {
    Archive,
    ArrowLeft,
    ChartNoAxesColumn,
    CircleAlert,
    CircleCheck,
    CircleX,
    Copy,
    Eye,
    Lock,
    Plus,
    RefreshCcw,
    Rocket,
    Save,
    Undo2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { ConfirmDialog } from '@/components/data/confirm-dialog';
import { EmptyState } from '@/components/data/empty-state';
import { PageHeader } from '@/components/data/page-header';
import { StatusBadge } from '@/components/data/status-badge';
import InputError from '@/components/input-error';
import { CategorySelect } from '@/components/questions/question-fields';
import { QuestionPicker } from '@/components/tests/question-picker';
import { TestQuestionList } from '@/components/tests/test-question-list';
import { TestStatusBadge } from '@/components/tests/test-status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import {
    formatCents,
    MAX_TEST_QUESTIONS,
    totalCents,
    zonedInputToDate,
} from '@/lib/test-builder';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import teacher from '@/routes/teacher';
import type {
    ChecklistItem,
    QuestionOptions,
    QuestionSelectionMode,
    ResultsVisibility,
    TestBuilderData,
    TestOptions,
    TestQuestionRow,
} from '@/types';

type Tab = 'basics' | 'questions' | 'settings' | 'schedule' | 'review';

/** The builder's editable state. Number fields are strings ('' = empty). */
type BuilderForm = {
    title: string;
    description: string;
    category: string | null;
    time_limit_minutes: string;
    max_attempts: string;
    pass_percent: string;
    shuffle_questions: boolean;
    shuffle_options: boolean;
    selection_mode: QuestionSelectionMode;
    random_question_count: string;
    opens_at: string;
    closes_at: string;
    results_visibility: ResultsVisibility;
    show_correct_answers: boolean;
    issues_certificate: boolean;
    questions: TestQuestionRow[];
};

function formFrom(test: TestBuilderData): BuilderForm {
    const text = (value: number | null) =>
        value === null ? '' : String(value);

    return {
        title: test.title,
        description: test.description ?? '',
        category: test.category,
        time_limit_minutes: text(test.time_limit_minutes),
        max_attempts: text(test.max_attempts),
        pass_percent: String(test.pass_percent),
        shuffle_questions: test.shuffle_questions,
        shuffle_options: test.shuffle_options,
        selection_mode: test.selection_mode,
        random_question_count: text(test.random_question_count),
        opens_at: test.opens_at_local ?? '',
        closes_at: test.closes_at_local ?? '',
        results_visibility: test.results_visibility,
        show_correct_answers: test.show_correct_answers,
        issues_certificate: test.issues_certificate,
        questions: test.questions,
    };
}

function payloadFrom(form: BuilderForm) {
    const number = (value: string) =>
        value.trim() === '' ? null : Number(value);
    const text = (value: string) => (value.trim() === '' ? null : value);

    return {
        title: form.title,
        description: text(form.description),
        category: form.category,
        time_limit_minutes: number(form.time_limit_minutes),
        max_attempts: number(form.max_attempts),
        pass_percent: number(form.pass_percent),
        shuffle_questions: form.shuffle_questions,
        shuffle_options: form.shuffle_options,
        selection_mode: form.selection_mode,
        random_question_count:
            form.selection_mode === 'random_pool'
                ? number(form.random_question_count)
                : null,
        opens_at: text(form.opens_at),
        closes_at: text(form.closes_at),
        results_visibility: form.results_visibility,
        show_correct_answers: form.show_correct_answers,
        issues_certificate: form.issues_certificate,
        questions: form.questions.map((question) => ({
            ulid: question.ulid,
            points_override: question.points_override,
        })),
    };
}

/** Where a server error key belongs (for the error dots on the tabs). */
function tabOf(key: string): Tab {
    if (key.startsWith('questions') || key === 'random_question_count') {
        return 'questions';
    }

    if (['title', 'description', 'category'].includes(key)) {
        return 'basics';
    }

    if (['opens_at', 'closes_at'].includes(key)) {
        return 'schedule';
    }

    if (
        [
            'time_limit_minutes',
            'max_attempts',
            'pass_percent',
            'shuffle_questions',
            'shuffle_options',
            'selection_mode',
            'results_visibility',
            'show_correct_answers',
            'issues_certificate',
        ].includes(key)
    ) {
        return 'settings';
    }

    return 'review';
}

/**
 * The test builder. Changes stay local until "Save" (one PUT with every
 * setting and the ordered question list). Once students have attempted the
 * test it is locked: grading-relevant settings can't change (the server
 * enforces this; the UI only explains it).
 */
export default function TestBuilder({
    test,
    checklist,
    attemptsCount,
    options,
    questionOptions,
}: {
    test: TestBuilderData;
    checklist: ChecklistItem[];
    attemptsCount: number;
    options: TestOptions;
    questionOptions: QuestionOptions;
}) {
    const { t } = useTranslation();
    const [tab, setTab] = useState<Tab>('basics');
    const [saved, setSaved] = useState(test);
    const [form, setForm] = useState<BuilderForm>(() => formFrom(test));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [confirm, setConfirm] = useState<'archive' | 'unpublish' | null>(
        null,
    );

    // A save (or publish) returned fresh data: start from it.
    if (saved !== test) {
        setSaved(test);
        setForm(formFrom(test));
    }

    const dirty =
        JSON.stringify(payloadFrom(form)) !==
        JSON.stringify(payloadFrom(formFrom(test)));
    const locked = test.locked;
    const archived = test.status === 'archived';
    const readOnly = archived;

    useEffect(() => {
        if (!dirty) {
            return;
        }

        const warn = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, [dirty]);

    const set = <K extends keyof BuilderForm>(key: K, value: BuilderForm[K]) =>
        setForm((current) => ({ ...current, [key]: value }));

    const visit = (onSuccess?: () => void) => ({
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
        onSuccess: () => {
            setErrors({});
            setConfirm(null);
            onSuccess?.();
        },
        onError: (next: Record<string, string>) => {
            setErrors(next);
            setConfirm(null);
        },
    });

    const save = () =>
        router.put(
            teacher.tests.update.url(test.ulid),
            payloadFrom(form),
            visit(),
        );

    const errorTabs = new Set(Object.keys(errors).map(tabOf));
    const blocking = checklist.filter(
        (item) => !item.ok && item.severity === 'error',
    );

    const tabs: { key: Tab; label: string }[] = [
        { key: 'basics', label: t('Basics') },
        { key: 'questions', label: t('Questions') },
        { key: 'settings', label: t('Settings') },
        { key: 'schedule', label: t('Schedule') },
        { key: 'review', label: t('Review & publish') },
    ];

    return (
        <>
            <Head title={test.title} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={test.title}
                    actions={
                        <>
                            <Button asChild variant="ghost">
                                <Link href={teacher.tests.index()}>
                                    <ArrowLeft aria-hidden="true" />
                                    {t('Tests')}
                                </Link>
                            </Button>
                            {attemptsCount > 0 && (
                                <Button asChild variant="outline">
                                    <Link
                                        href={teacher.tests.analytics.show(
                                            test.ulid,
                                        )}
                                    >
                                        <ChartNoAxesColumn aria-hidden="true" />
                                        {t('Analytics')}
                                    </Link>
                                </Button>
                            )}
                            {attemptsCount > 0 && (
                                <Button asChild variant="outline">
                                    <Link
                                        href={teacher.tests.regrade.show(
                                            test.ulid,
                                        )}
                                    >
                                        <RefreshCcw aria-hidden="true" />
                                        {t('Regrade')}
                                    </Link>
                                </Button>
                            )}
                            {dirty && (
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setForm(formFrom(test));
                                        setErrors({});
                                    }}
                                >
                                    <Undo2 aria-hidden="true" />
                                    {t('Discard changes')}
                                </Button>
                            )}
                            {!readOnly && (
                                <Button
                                    onClick={save}
                                    disabled={!dirty || processing}
                                >
                                    {processing ? (
                                        <Spinner />
                                    ) : (
                                        <Save aria-hidden="true" />
                                    )}
                                    {t('Save')}
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <TestStatusBadge status={test.status} />
                    {locked && (
                        <StatusBadge
                            tone="warning"
                            icon={Lock}
                            label={t('Locked')}
                        />
                    )}
                    <span
                        className="text-sm text-muted-foreground"
                        role="status"
                        aria-live="polite"
                    >
                        {dirty ? t('Unsaved changes') : t('All changes saved')}
                    </span>
                </div>

                {archived && (
                    <Alert>
                        <Archive aria-hidden="true" />
                        <AlertTitle>{t('This test is archived')}</AlertTitle>
                        <AlertDescription>
                            {t(
                                'Archived tests are read-only. Duplicate it to reuse the questions and settings.',
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {locked && !archived && (
                    <Alert>
                        <Lock aria-hidden="true" />
                        <AlertTitle>{t('This test is locked')}</AlertTitle>
                        <AlertDescription className="space-y-3">
                            <p>
                                {t(
                                    'Students have already taken it (:count attempts), so everything that affects grading is frozen: questions, points, time limit, pass mark, shuffling and the opening time. You can still rename it, allow more attempts, close it later or reveal more.',
                                    { count: attemptsCount },
                                )}
                            </p>
                            <DuplicateButton ulid={test.ulid} />
                        </AlertDescription>
                    </Alert>
                )}

                {Object.keys(errors).length > 0 && (
                    <Alert variant="destructive">
                        <CircleX aria-hidden="true" />
                        <AlertTitle>
                            {t('Please check the highlighted fields')}
                        </AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc pl-4">
                                {Object.entries(errors).map(
                                    ([key, message]) => (
                                        <li key={key}>{message}</li>
                                    ),
                                )}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-[1fr_18rem]">
                    <div className="min-w-0 space-y-6">
                        <div
                            role="tablist"
                            aria-label={t('Test builder')}
                            className="flex gap-1 overflow-x-auto rounded-lg border bg-muted/40 p-1"
                        >
                            {tabs.map((item) => (
                                <button
                                    key={item.key}
                                    type="button"
                                    role="tab"
                                    id={`tab-${item.key}`}
                                    aria-selected={tab === item.key}
                                    aria-controls={`panel-${item.key}`}
                                    onClick={() => setTab(item.key)}
                                    className={cn(
                                        'relative shrink-0 rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        tab === item.key
                                            ? 'bg-background text-foreground shadow-sm'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {item.label}
                                    {errorTabs.has(item.key) && (
                                        <span className="ml-1.5 inline-block size-2 rounded-full bg-destructive">
                                            <span className="sr-only">
                                                {t('(has errors)')}
                                            </span>
                                        </span>
                                    )}
                                </button>
                            ))}
                        </div>

                        <div
                            role="tabpanel"
                            id={`panel-${tab}`}
                            aria-labelledby={`tab-${tab}`}
                        >
                            {tab === 'basics' && (
                                <BasicsPanel
                                    form={form}
                                    set={set}
                                    errors={errors}
                                    options={options}
                                    disabled={readOnly}
                                />
                            )}
                            {tab === 'questions' && (
                                <QuestionsPanel
                                    form={form}
                                    set={set}
                                    errors={errors}
                                    questionOptions={questionOptions}
                                    locked={locked || readOnly}
                                />
                            )}
                            {tab === 'settings' && (
                                <SettingsPanel
                                    form={form}
                                    set={set}
                                    errors={errors}
                                    options={options}
                                    test={test}
                                    disabled={readOnly}
                                />
                            )}
                            {tab === 'schedule' && (
                                <SchedulePanel
                                    form={form}
                                    set={set}
                                    errors={errors}
                                    options={options}
                                    test={test}
                                    disabled={readOnly}
                                />
                            )}
                            {tab === 'review' && (
                                <ReviewPanel
                                    test={test}
                                    checklist={checklist}
                                    dirty={dirty}
                                    processing={processing}
                                    onPreview={() =>
                                        router.post(
                                            teacher.tests.preview.store.url(
                                                test.ulid,
                                            ),
                                            {},
                                            visit(),
                                        )
                                    }
                                    onPublish={() =>
                                        router.post(
                                            teacher.tests.publish.url(
                                                test.ulid,
                                            ),
                                            {},
                                            visit(),
                                        )
                                    }
                                    onUnpublish={() => setConfirm('unpublish')}
                                    onArchive={() => setConfirm('archive')}
                                    blocking={blocking.length}
                                />
                            )}
                        </div>
                    </div>

                    <Summary form={form} checklist={checklist} />
                </div>
            </div>

            <ConfirmDialog
                open={confirm === 'archive'}
                onOpenChange={(next) => !next && setConfirm(null)}
                title={t('Archive this test?')}
                description={t(
                    'Students can no longer start it. Results and certificates stay.',
                )}
                confirmLabel={t('Archive')}
                destructive
                processing={processing}
                onConfirm={() =>
                    router.post(
                        teacher.tests.archive.url(test.ulid),
                        {},
                        visit(),
                    )
                }
            />

            <ConfirmDialog
                open={confirm === 'unpublish'}
                onOpenChange={(next) => !next && setConfirm(null)}
                title={t('Unpublish this test?')}
                description={t(
                    'It goes back to draft. Nobody has attempted it yet, so nothing is lost.',
                )}
                confirmLabel={t('Unpublish')}
                processing={processing}
                onConfirm={() =>
                    router.delete(
                        teacher.tests.unpublish.url(test.ulid),
                        visit(),
                    )
                }
            />
        </>
    );
}

type PanelProps = {
    form: BuilderForm;
    set: <K extends keyof BuilderForm>(key: K, value: BuilderForm[K]) => void;
    errors: Record<string, string>;
};

function Field({
    id,
    label,
    hint,
    error,
    children,
}: {
    id: string;
    label: string;
    hint?: ReactNode;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && (
                <p className="text-xs text-muted-foreground" id={`${id}-hint`}>
                    {hint}
                </p>
            )}
            <InputError message={error} />
        </div>
    );
}

function LockedHint() {
    const { t } = useTranslation();

    return (
        <span className="inline-flex items-center gap-1">
            <Lock className="size-3" aria-hidden="true" />
            {t('Locked: students have already taken this test.')}
        </span>
    );
}

function Toggle({
    id,
    label,
    description,
    checked,
    disabled,
    onChange,
    error,
}: {
    id: string;
    label: string;
    description?: ReactNode;
    checked: boolean;
    disabled?: boolean;
    onChange: (checked: boolean) => void;
    error?: string;
}) {
    return (
        <div className="space-y-1">
            <div className="flex items-start gap-3">
                <input
                    id={id}
                    type="checkbox"
                    className="mt-0.5 size-4 accent-primary"
                    checked={checked}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.checked)}
                />
                <div className="space-y-0.5">
                    <Label htmlFor={id}>{label}</Label>
                    {description && (
                        <p className="text-xs text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
            </div>
            <InputError message={error} />
        </div>
    );
}

function BasicsPanel({
    form,
    set,
    errors,
    options,
    disabled,
}: PanelProps & { options: TestOptions; disabled: boolean }) {
    const { t } = useTranslation();

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('Basics')}</CardTitle>
                <CardDescription>
                    {t('What students see before they start.')}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                <Field id="title" label={t('Title')} error={errors.title}>
                    <Input
                        id="title"
                        value={form.title}
                        maxLength={150}
                        disabled={disabled}
                        onChange={(event) => set('title', event.target.value)}
                    />
                </Field>
                <Field
                    id="description"
                    label={t('Description')}
                    error={errors.description}
                >
                    <Textarea
                        id="description"
                        rows={5}
                        maxLength={2000}
                        value={form.description}
                        disabled={disabled}
                        onChange={(event) =>
                            set('description', event.target.value)
                        }
                    />
                </Field>
                <Field
                    id="category"
                    label={t('Category')}
                    error={errors.category}
                >
                    <CategorySelect
                        id="category"
                        value={form.category}
                        categories={options.categories}
                        onChange={(category) => set('category', category)}
                    />
                </Field>
            </CardContent>
        </Card>
    );
}

function QuestionsPanel({
    form,
    set,
    errors,
    questionOptions,
    locked,
}: PanelProps & { questionOptions: QuestionOptions; locked: boolean }) {
    const { t } = useTranslation();
    const pool = form.selection_mode === 'random_pool';
    const remaining = MAX_TEST_QUESTIONS - form.questions.length;
    const archivedCount = form.questions.filter((q) => q.archived).length;

    const mix = questionOptions.difficulties.map((difficulty) => ({
        ...difficulty,
        count: form.questions.filter((q) => q.difficulty === difficulty.value)
            .length,
    }));

    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                <div className="space-y-1.5">
                    <CardTitle>{t('Questions')}</CardTitle>
                    <CardDescription>
                        {pool
                            ? t(
                                  'Each student gets a random selection from this pool.',
                              )
                            : t(
                                  'Students answer every question. Drag to change the order.',
                              )}
                    </CardDescription>
                </div>
                {!locked && (
                    <QuestionPicker
                        trigger={
                            <Button type="button" disabled={remaining <= 0}>
                                <Plus aria-hidden="true" />
                                {t('Add questions')}
                            </Button>
                        }
                        attached={form.questions.map((q) => q.ulid)}
                        options={questionOptions}
                        remaining={remaining}
                        onAdd={(added) =>
                            set('questions', [...form.questions, ...added])
                        }
                    />
                )}
            </CardHeader>
            <CardContent className="space-y-6">
                {locked && (
                    <p className="text-sm text-muted-foreground">
                        <LockedHint />
                    </p>
                )}

                {archivedCount > 0 && (
                    <Alert>
                        <CircleAlert aria-hidden="true" />
                        <AlertDescription>
                            {t(
                                ':count archived question(s) are still in this test. Students will see them; remove them if that is not intended.',
                                { count: archivedCount },
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                    <Field
                        id="selection_mode"
                        label={t('Question selection')}
                        error={errors.selection_mode}
                    >
                        <Select
                            value={form.selection_mode}
                            disabled={locked}
                            onValueChange={(value) =>
                                set(
                                    'selection_mode',
                                    value as QuestionSelectionMode,
                                )
                            }
                        >
                            <SelectTrigger id="selection_mode">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="fixed">
                                    {t('All questions, fixed set')}
                                </SelectItem>
                                <SelectItem value="random_pool">
                                    {t('Random selection from the pool')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                    {pool && (
                        <Field
                            id="random_question_count"
                            label={t('Questions per student')}
                            hint={t('Pool: :count questions', {
                                count: form.questions.length,
                            })}
                            error={errors.random_question_count}
                        >
                            <Input
                                id="random_question_count"
                                type="number"
                                min={1}
                                max={Math.max(1, form.questions.length)}
                                className="tabular"
                                value={form.random_question_count}
                                disabled={locked}
                                onChange={(event) =>
                                    set(
                                        'random_question_count',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    )}
                    {pool && form.questions.length > 0 && (
                        <div className="sm:col-span-2">
                            <p className="text-sm font-medium">
                                {t('Difficulty mix of the pool')}
                            </p>
                            <ul className="mt-1 flex flex-wrap gap-3 text-sm text-muted-foreground">
                                {mix.map((item) => (
                                    <li key={item.value}>
                                        {item.label}:{' '}
                                        <span className="tabular font-medium text-foreground">
                                            {item.count}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>

                <InputError message={errors.questions} />

                {form.questions.length === 0 ? (
                    <EmptyState
                        icon={Plus}
                        title={t('No questions yet')}
                        description={t(
                            'Add questions from your bank. You need at least one to publish.',
                        )}
                    />
                ) : (
                    <TestQuestionList
                        questions={form.questions}
                        onChange={(questions) => set('questions', questions)}
                        disabled={locked}
                        difficulties={questionOptions.difficulties}
                        errorFor={(index) =>
                            errors[`questions.${index}.points_override`] ??
                            errors[`questions.${index}.ulid`]
                        }
                    />
                )}
            </CardContent>
        </Card>
    );
}

function SettingsPanel({
    form,
    set,
    errors,
    options,
    test,
    disabled,
}: PanelProps & {
    options: TestOptions;
    test: TestBuilderData;
    disabled: boolean;
}) {
    const { t } = useTranslation();
    const locked = test.locked;
    const savedRank =
        options.visibilities.find((v) => v.value === test.results_visibility)
            ?.rank ?? 0;

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('Settings')}</CardTitle>
                <CardDescription>
                    {t(
                        'Time, attempts, grading and what students see afterwards.',
                    )}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="grid gap-6 sm:grid-cols-3">
                    <Field
                        id="time_limit_minutes"
                        label={t('Time limit (minutes)')}
                        hint={
                            locked ? <LockedHint /> : t('Empty: no time limit')
                        }
                        error={errors.time_limit_minutes}
                    >
                        <Input
                            id="time_limit_minutes"
                            type="number"
                            min={1}
                            max={600}
                            className="tabular"
                            value={form.time_limit_minutes}
                            disabled={locked || disabled}
                            onChange={(event) =>
                                set('time_limit_minutes', event.target.value)
                            }
                        />
                    </Field>
                    <Field
                        id="max_attempts"
                        label={t('Attempts per student')}
                        hint={
                            locked
                                ? t('Locked: can only be increased.')
                                : t('Empty: unlimited')
                        }
                        error={errors.max_attempts}
                    >
                        <Input
                            id="max_attempts"
                            type="number"
                            min={locked ? (test.max_attempts ?? 1) : 1}
                            max={20}
                            className="tabular"
                            value={form.max_attempts}
                            disabled={disabled}
                            onChange={(event) =>
                                set('max_attempts', event.target.value)
                            }
                        />
                    </Field>
                    <Field
                        id="pass_percent"
                        label={t('Pass mark (%)')}
                        hint={locked ? <LockedHint /> : undefined}
                        error={errors.pass_percent}
                    >
                        <Input
                            id="pass_percent"
                            type="number"
                            min={0}
                            max={100}
                            className="tabular"
                            value={form.pass_percent}
                            disabled={locked || disabled}
                            onChange={(event) =>
                                set('pass_percent', event.target.value)
                            }
                        />
                    </Field>
                </div>

                <div className="space-y-4">
                    <Toggle
                        id="shuffle_questions"
                        label={t('Shuffle question order')}
                        description={
                            locked ? (
                                <LockedHint />
                            ) : (
                                t('Each student gets their own order.')
                            )
                        }
                        checked={form.shuffle_questions}
                        disabled={locked || disabled}
                        onChange={(checked) =>
                            set('shuffle_questions', checked)
                        }
                        error={errors.shuffle_questions}
                    />
                    <Toggle
                        id="shuffle_options"
                        label={t('Shuffle answer options')}
                        description={
                            locked ? (
                                <LockedHint />
                            ) : (
                                t(
                                    'Choice, ordering and matching items appear in a random order.',
                                )
                            )
                        }
                        checked={form.shuffle_options}
                        disabled={locked || disabled}
                        onChange={(checked) => set('shuffle_options', checked)}
                        error={errors.shuffle_options}
                    />
                </div>

                <Field
                    id="results_visibility"
                    label={t('Results are shown')}
                    hint={
                        locked
                            ? t('Locked: you can only reveal more, not less.')
                            : undefined
                    }
                    error={errors.results_visibility}
                >
                    <Select
                        value={form.results_visibility}
                        disabled={disabled}
                        onValueChange={(value) =>
                            set(
                                'results_visibility',
                                value as ResultsVisibility,
                            )
                        }
                    >
                        <SelectTrigger id="results_visibility">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {options.visibilities.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                    disabled={locked && option.rank < savedRank}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>

                <div className="space-y-4">
                    <Toggle
                        id="show_correct_answers"
                        label={t('Show correct answers in the review')}
                        description={
                            locked && test.show_correct_answers
                                ? t('Locked: already revealed to students.')
                                : t('Only when results are visible.')
                        }
                        checked={form.show_correct_answers}
                        disabled={
                            disabled || (locked && test.show_correct_answers)
                        }
                        onChange={(checked) =>
                            set('show_correct_answers', checked)
                        }
                        error={errors.show_correct_answers}
                    />
                    <Toggle
                        id="issues_certificate"
                        label={t('Issue a certificate when passed')}
                        description={
                            locked && test.issues_certificate
                                ? t(
                                      'Locked: students may already have certificates.',
                                  )
                                : undefined
                        }
                        checked={form.issues_certificate}
                        disabled={
                            disabled || (locked && test.issues_certificate)
                        }
                        onChange={(checked) =>
                            set('issues_certificate', checked)
                        }
                        error={errors.issues_certificate}
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function SchedulePanel({
    form,
    set,
    errors,
    options,
    test,
    disabled,
}: PanelProps & {
    options: TestOptions;
    test: TestBuilderData;
    disabled: boolean;
}) {
    const { t, dateLocale } = useTranslation();
    const [now] = useState(() => Date.now());
    const locked = test.locked;
    const opens = zonedInputToDate(form.opens_at, options.timezone);
    const closes = zonedInputToDate(form.closes_at, options.timezone);
    const timeLimit = Number(form.time_limit_minutes);

    const relative = (date: Date) =>
        formatDistance(date, now, { addSuffix: true, locale: dateLocale });

    const display = (date: Date) =>
        new Intl.DateTimeFormat(dateLocale.code, {
            dateStyle: 'full',
            timeStyle: 'short',
            timeZone: options.timezone,
        }).format(date);

    const windowMinutes =
        opens && closes ? (closes.getTime() - opens.getTime()) / 60000 : null;

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('Schedule')}</CardTitle>
                <CardDescription>
                    {t('Times are in :timezone. Leave empty for no limit.', {
                        timezone: options.timezone,
                    })}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="grid gap-6 sm:grid-cols-2">
                    <Field
                        id="opens_at"
                        label={t('Opens')}
                        hint={locked ? <LockedHint /> : undefined}
                        error={errors.opens_at}
                    >
                        <Input
                            id="opens_at"
                            type="datetime-local"
                            className="tabular"
                            value={form.opens_at}
                            disabled={locked || disabled}
                            onChange={(event) =>
                                set('opens_at', event.target.value)
                            }
                        />
                    </Field>
                    <Field
                        id="closes_at"
                        label={t('Closes')}
                        hint={
                            locked
                                ? t('Locked: can only be moved later.')
                                : undefined
                        }
                        error={errors.closes_at}
                    >
                        <Input
                            id="closes_at"
                            type="datetime-local"
                            className="tabular"
                            min={
                                locked
                                    ? (test.closes_at_local ?? undefined)
                                    : form.opens_at || undefined
                            }
                            value={form.closes_at}
                            disabled={disabled}
                            onChange={(event) =>
                                set('closes_at', event.target.value)
                            }
                        />
                    </Field>
                </div>

                <div className="space-y-2 rounded-lg border bg-muted/30 p-4 text-sm">
                    <p>
                        {opens
                            ? t('Opens :date (:relative).', {
                                  date: display(opens),
                                  relative: relative(opens),
                              })
                            : t('Open as soon as it is published.')}
                    </p>
                    <p>
                        {closes
                            ? t('Closes :date (:relative).', {
                                  date: display(closes),
                                  relative: relative(closes),
                              })
                            : t('No closing time.')}
                    </p>
                    {opens && closes && closes > opens && (
                        <p>
                            {t('Open for :duration.', {
                                duration: formatDistanceStrict(opens, closes, {
                                    locale: dateLocale,
                                }),
                            })}
                        </p>
                    )}
                    {windowMinutes !== null &&
                        timeLimit > 0 &&
                        windowMinutes > 0 &&
                        windowMinutes < timeLimit && (
                            <p className="flex items-center gap-1 font-medium">
                                <CircleAlert
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {t(
                                    'The window is shorter than the time limit: students who start late get less time.',
                                )}
                            </p>
                        )}
                </div>
            </CardContent>
        </Card>
    );
}

function ReviewPanel({
    test,
    checklist,
    dirty,
    processing,
    blocking,
    onPreview,
    onPublish,
    onUnpublish,
    onArchive,
}: {
    test: TestBuilderData;
    checklist: ChecklistItem[];
    dirty: boolean;
    processing: boolean;
    blocking: number;
    onPreview: () => void;
    onPublish: () => void;
    onUnpublish: () => void;
    onArchive: () => void;
}) {
    const { t } = useTranslation();
    const archived = test.status === 'archived';

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('Review & publish')}</CardTitle>
                <CardDescription>
                    {t('The checklist reflects the last saved version.')}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                {dirty && (
                    <Alert>
                        <CircleAlert aria-hidden="true" />
                        <AlertDescription>
                            {t(
                                'Save your changes before previewing or publishing.',
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <ul className="space-y-2" aria-label={t('Publish checklist')}>
                    {checklist.map((item) => (
                        <li key={item.key} className="flex items-start gap-2">
                            {item.ok ? (
                                <CircleCheck
                                    className="mt-0.5 size-4 shrink-0 text-success"
                                    aria-label={t('Done')}
                                />
                            ) : item.severity === 'error' ? (
                                <CircleX
                                    className="mt-0.5 size-4 shrink-0 text-destructive"
                                    aria-label={t('Required')}
                                />
                            ) : (
                                <CircleAlert
                                    className="mt-0.5 size-4 shrink-0 text-warning"
                                    aria-label={t('Warning')}
                                />
                            )}
                            <span
                                className={cn(
                                    'text-sm',
                                    item.ok && 'text-muted-foreground',
                                )}
                            >
                                {item.message}
                            </span>
                        </li>
                    ))}
                </ul>

                <div className="flex flex-wrap gap-2">
                    {!archived && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={
                                dirty ||
                                processing ||
                                test.questions.length === 0
                            }
                            onClick={onPreview}
                        >
                            <Eye aria-hidden="true" />
                            {t('Preview as student')}
                        </Button>
                    )}
                    {test.status === 'draft' && (
                        <Button
                            type="button"
                            disabled={dirty || processing || blocking > 0}
                            onClick={onPublish}
                        >
                            <Rocket aria-hidden="true" />
                            {t('Publish')}
                        </Button>
                    )}
                    {test.status === 'published' && !test.locked && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={onUnpublish}
                        >
                            {t('Unpublish')}
                        </Button>
                    )}
                    <DuplicateButton ulid={test.ulid} />
                    {!archived && (
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={processing}
                            onClick={onArchive}
                        >
                            <Archive aria-hidden="true" />
                            {t('Archive')}
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function DuplicateButton({ ulid }: { ulid: string }) {
    const { t } = useTranslation();

    return (
        <Button
            type="button"
            variant="outline"
            onClick={() => router.post(teacher.tests.duplicate.url(ulid))}
        >
            <Copy aria-hidden="true" />
            {t('Duplicate as new version')}
        </Button>
    );
}

function Summary({
    form,
    checklist,
}: {
    form: BuilderForm;
    checklist: ChecklistItem[];
}) {
    const { t } = useTranslation();
    const pool = form.selection_mode === 'random_pool';
    const total = totalCents(form.questions);
    const perStudent = pool
        ? Math.min(
              Number(form.random_question_count) || 0,
              form.questions.length,
          )
        : form.questions.length;
    const timeLimit = Number(form.time_limit_minutes);
    const warnings = checklist.filter((item) => !item.ok);
    const archived = form.questions.filter((q) => q.archived).length;

    return (
        <aside className="lg:sticky lg:top-4 lg:self-start">
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">{t('Summary')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4 text-sm">
                    <dl className="grid grid-cols-[1fr_auto] gap-x-3 gap-y-2">
                        <dt className="text-muted-foreground">
                            {pool ? t('Questions per student') : t('Questions')}
                        </dt>
                        <dd className="tabular text-right font-medium">
                            {pool
                                ? t(':count of :max', {
                                      count: perStudent,
                                      max: form.questions.length,
                                  })
                                : perStudent}
                        </dd>
                        <dt className="text-muted-foreground">
                            {t('Total points')}
                        </dt>
                        <dd className="tabular text-right font-medium">
                            {pool
                                ? t('Varies')
                                : total === null
                                  ? '—'
                                  : formatCents(total)}
                        </dd>
                        <dt className="text-muted-foreground">
                            {t('Duration')}
                        </dt>
                        <dd className="tabular text-right font-medium">
                            {timeLimit > 0
                                ? t(':minutes min', { minutes: timeLimit })
                                : t('No limit')}
                        </dd>
                    </dl>

                    {(warnings.length > 0 || archived > 0) && (
                        <ul className="space-y-1 border-t pt-3">
                            {archived > 0 && (
                                <li className="flex gap-1.5">
                                    <CircleAlert
                                        className="mt-0.5 size-4 shrink-0 text-warning"
                                        aria-hidden="true"
                                    />
                                    {t(':count archived question(s)', {
                                        count: archived,
                                    })}
                                </li>
                            )}
                            {warnings.map((item) => (
                                <li key={item.key} className="flex gap-1.5">
                                    {item.severity === 'error' ? (
                                        <CircleX
                                            className="mt-0.5 size-4 shrink-0 text-destructive"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        <CircleAlert
                                            className="mt-0.5 size-4 shrink-0 text-warning"
                                            aria-hidden="true"
                                        />
                                    )}
                                    {item.message}
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </aside>
    );
}

TestBuilder.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Tests', href: teacher.tests.index() },
    ],
};
