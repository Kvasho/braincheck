import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Eye, ImageOff, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/data/page-header';
import InputError from '@/components/input-error';
import {
    CategorySelect,
    TagPicker,
} from '@/components/questions/question-fields';
import { plainToRichText } from '@/components/rich-text';
import { RichTextEditor } from '@/components/rich-text-editor';
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
import { useTranslation } from '@/hooks/use-translation';
import { QuestionTypeEditor } from '@/question-types/components';
import { questionTypeModule, questionTypes } from '@/question-types/registry';
import type { EditorErrors } from '@/question-types/shared/types';
import { dashboard } from '@/routes';
import teacher from '@/routes/teacher';
import type {
    Difficulty,
    QuestionEditorData,
    QuestionOptions,
    QuestionType,
    RichTextDoc,
} from '@/types';
import { QUESTION_TYPES } from '@/types/enums';

type FormData = {
    type: QuestionType | null;
    body: RichTextDoc;
    explanation: RichTextDoc | null;
    category: string | null;
    tags: string[];
    difficulty: Difficulty;
    points: string;
    definition: unknown;
    image: File | null;
    remove_image: boolean;
};

const IMAGE_MAX_BYTES = 4 * 1024 * 1024;

/**
 * Form state for the question editor. Inertia's useForm can't type the
 * recursive rich text documents, so this keeps plain state and submits with
 * the router: the structured fields travel as JSON strings (booleans and
 * numbers survive; StoreQuestionRequest decodes them) and a chosen image
 * switches the request to multipart.
 */
function useQuestionForm(initial: FormData) {
    const [data, setDataState] = useState<FormData>(initial);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const setData = <K extends keyof FormData>(key: K, value: FormData[K]) =>
        setDataState((current) => ({ ...current, [key]: value }));

    const submit = (method: 'post' | 'put', url: string) => {
        const payload = {
            type: data.type,
            body: JSON.stringify(data.body),
            explanation: JSON.stringify(data.explanation),
            definition: JSON.stringify(data.definition),
            tags: JSON.stringify(data.tags),
            category: data.category,
            difficulty: data.difficulty,
            points: data.points,
            remove_image: data.remove_image,
            image: data.image,
        };

        router[method](url, payload, {
            preserveScroll: true,
            forceFormData: data.image !== null,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setErrors({}),
            onError: (next) => setErrors(next),
        });
    };

    return { data, setData, setDataState, errors, processing, submit };
}

export default function QuestionForm({
    question,
    options,
    liveTests,
}: {
    question: QuestionEditorData | null;
    options: QuestionOptions;
    liveTests: number;
}) {
    const { t } = useTranslation();
    const editing = question !== null;
    const [clientErrors, setClientErrors] = useState<EditorErrors>({});
    const [imageError, setImageError] = useState<string | null>(null);

    const form = useQuestionForm({
        type: question?.type ?? null,
        body: question?.body ?? plainToRichText(''),
        explanation: question?.explanation ?? null,
        category: question?.category ?? null,
        tags: question?.tags ?? [],
        difficulty: question?.difficulty ?? 'medium',
        points: question?.points ?? '1.00',
        definition: question?.definition ?? null,
        image: null,
        remove_image: false,
    });

    const type = form.data.type;

    const chooseType = (next: QuestionType) => {
        form.setDataState((data) => ({
            ...data,
            type: next,
            definition: questionTypeModule(next).emptyInput(),
        }));
        setClientErrors({});
    };

    // Server errors arrive as "definition.options.0.text"; editors use relative keys.
    const definitionErrors: EditorErrors = { ...clientErrors };

    for (const [key, message] of Object.entries(form.errors)) {
        if (key.startsWith('definition.')) {
            definitionErrors[key.slice('definition.'.length)] = message;
        }
    }

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (type === null) {
            return;
        }

        const errors = questionTypeModule(type).validate(
            form.data.definition,
            t,
        );
        setClientErrors(errors);

        if (Object.keys(errors).length > 0) {
            return;
        }

        if (editing) {
            form.submit('put', teacher.questions.update.url(question.ulid));
        } else {
            form.submit('post', teacher.questions.store.url());
        }
    };

    const title = editing ? t('Edit question') : t('New question');

    return (
        <>
            <Head title={title} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={title}
                    description={
                        editing
                            ? t('Version :version', {
                                  version: question.version,
                              })
                            : undefined
                    }
                    actions={
                        <>
                            <Button asChild variant="ghost">
                                <Link href={teacher.questions.index()}>
                                    <ArrowLeft aria-hidden="true" />
                                    {t('Question bank')}
                                </Link>
                            </Button>
                            {editing && (
                                <Button asChild variant="outline">
                                    <Link
                                        href={teacher.questions.preview(
                                            question.ulid,
                                        )}
                                    >
                                        <Eye aria-hidden="true" />
                                        {t('Preview as student')}
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                {liveTests > 0 && (
                    <Alert>
                        <TriangleAlert aria-hidden="true" />
                        <AlertTitle>{t('Used in a live test')}</AlertTitle>
                        <AlertDescription>
                            {t(
                                'This question is used in :count published test(s) that already have attempts. Those attempts keep the version they started with; changes apply to new attempts.',
                                { count: liveTests },
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {type === null ? (
                    <TypePicker onChoose={chooseType} />
                ) : (
                    <form
                        onSubmit={submit}
                        className="grid gap-6 lg:grid-cols-3"
                    >
                        <div className="space-y-6 lg:col-span-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('Question')}</CardTitle>
                                    <CardDescription>
                                        {t(questionTypes[type].label)}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    <Label htmlFor="body">
                                        {t('Question text')}
                                    </Label>
                                    <RichTextEditor
                                        id="body"
                                        value={form.data.body}
                                        onChange={(body) =>
                                            form.setData('body', body)
                                        }
                                        invalid={form.errors.body !== undefined}
                                    />
                                    <InputError message={form.errors.body} />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('Answer')}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <QuestionTypeEditor
                                        type={type}
                                        value={form.data.definition}
                                        onChange={(definition) =>
                                            form.setData(
                                                'definition',
                                                definition,
                                            )
                                        }
                                        errors={definitionErrors}
                                        scorings={options.scorings}
                                    />
                                    <InputError
                                        className="mt-2"
                                        message={form.errors.definition}
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        {t('Explanation (optional)')}
                                    </CardTitle>
                                    <CardDescription>
                                        {t(
                                            'Shown to students after the test, if results allow it.',
                                        )}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <RichTextEditor
                                        id="explanation"
                                        rows={3}
                                        value={form.data.explanation}
                                        onChange={(explanation) =>
                                            form.setData(
                                                'explanation',
                                                explanation,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.explanation}
                                    />
                                </CardContent>
                            </Card>
                        </div>

                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('Details')}</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="points">
                                            {t('Points')}
                                        </Label>
                                        <Input
                                            id="points"
                                            inputMode="decimal"
                                            className="tabular"
                                            value={form.data.points}
                                            onChange={(event) =>
                                                form.setData(
                                                    'points',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors.points}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="difficulty">
                                            {t('Difficulty')}
                                        </Label>
                                        <Select
                                            value={form.data.difficulty}
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'difficulty',
                                                    value as Difficulty,
                                                )
                                            }
                                        >
                                            <SelectTrigger id="difficulty">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.difficulties.map(
                                                    (option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={form.errors.difficulty}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="category">
                                            {t('Category')}
                                        </Label>
                                        <CategorySelect
                                            id="category"
                                            value={form.data.category}
                                            categories={options.categories}
                                            onChange={(category) =>
                                                form.setData(
                                                    'category',
                                                    category,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors.category}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="tags">
                                            {t('Tags')}
                                        </Label>
                                        <TagPicker
                                            id="tags"
                                            value={form.data.tags}
                                            options={options.tags}
                                            onChange={(tags) =>
                                                form.setData('tags', tags)
                                            }
                                        />
                                        <InputError
                                            message={form.errors.tags}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        {t('Image (optional)')}
                                    </CardTitle>
                                    <CardDescription>
                                        {t(
                                            'JPEG, PNG or WebP, up to 4 MB. It is converted to WebP and stripped of metadata.',
                                        )}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {question?.image_url &&
                                        !form.data.remove_image &&
                                        !form.data.image && (
                                            <img
                                                src={question.image_url}
                                                alt={t('Current image')}
                                                className="max-h-48 rounded-lg border object-contain"
                                            />
                                        )}
                                    <Input
                                        id="image"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        aria-label={t('Choose an image')}
                                        onChange={(event) => {
                                            const file =
                                                event.target.files?.[0] ?? null;

                                            if (
                                                file &&
                                                file.size > IMAGE_MAX_BYTES
                                            ) {
                                                setImageError(
                                                    t(
                                                        'The image must be at most 4 MB.',
                                                    ),
                                                );
                                                event.target.value = '';

                                                return;
                                            }

                                            setImageError(null);
                                            form.setData('image', file);
                                        }}
                                    />
                                    <InputError
                                        message={
                                            imageError ?? form.errors.image
                                        }
                                    />
                                    {question?.image_url && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                form.setData(
                                                    'remove_image',
                                                    !form.data.remove_image,
                                                )
                                            }
                                        >
                                            <ImageOff aria-hidden="true" />
                                            {form.data.remove_image
                                                ? t('Keep the image')
                                                : t('Remove the image')}
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>

                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing && <Spinner />}
                                    {editing ? t('Save') : t('Create question')}
                                </Button>
                                {!editing && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() =>
                                            form.setData('type', null)
                                        }
                                    >
                                        {t('Change type')}
                                    </Button>
                                )}
                            </div>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}

function TypePicker({ onChoose }: { onChoose: (type: QuestionType) => void }) {
    const { t } = useTranslation();

    return (
        <section aria-labelledby="type-picker" className="space-y-3">
            <h2 id="type-picker" className="text-lg font-semibold">
                {t('Choose a question type')}
            </h2>
            <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {QUESTION_TYPES.map((value) => {
                    const {
                        icon: Icon,
                        label,
                        description,
                    } = questionTypes[value];

                    return (
                        <li key={value}>
                            <button
                                type="button"
                                onClick={() => onChoose(value)}
                                className="flex h-full w-full flex-col items-start gap-2 rounded-xl border bg-card p-4 text-left transition-colors hover:border-primary hover:bg-primary/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <span className="flex size-10 items-center justify-center rounded-lg bg-accent">
                                    <Icon
                                        className="size-5 text-primary"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span className="font-medium">{t(label)}</span>
                                <span className="text-sm text-muted-foreground">
                                    {t(description)}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

QuestionForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Question bank', href: teacher.questions.index() },
    ],
};
