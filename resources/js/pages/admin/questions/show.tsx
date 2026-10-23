import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { PageHeader } from '@/components/data/page-header';
import { QuestionTypeBadge } from '@/components/questions/question-fields';
import { RichText } from '@/components/rich-text';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import {
    QuestionTypePlayer,
    QuestionTypeReview,
} from '@/question-types/components';
import { questionTypeModule } from '@/question-types/registry';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import type { QuestionPreview, QuestionRow, RichTextDoc } from '@/types';

/**
 * Moderation view: the question as students see it, plus the correct answer.
 * Read-only: nothing here can change the question.
 */
export default function AdminQuestionShow({
    question,
    preview,
    review,
    explanation,
}: {
    question: QuestionRow;
    preview: QuestionPreview;
    review: unknown;
    explanation: RichTextDoc | null;
}) {
    const { t, locale } = useTranslation();
    const [answer, setAnswer] = useState<unknown>(() =>
        questionTypeModule(preview.type).initialAnswer(preview.player),
    );

    return (
        <>
            <Head title={t('Question')} />

            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Question')}
                    description={t('By :teacher · version :version', {
                        teacher: question.owner?.name ?? '—',
                        version: question.version,
                    })}
                />

                <div className="flex flex-wrap gap-2">
                    <QuestionTypeBadge type={question.type} />
                    {question.archived && (
                        <Badge variant="secondary">{t('Archived')}</Badge>
                    )}
                    {question.category && (
                        <Badge variant="outline">
                            {question.category.name[locale]}
                        </Badge>
                    )}
                    {question.tags.map((tag) => (
                        <Badge key={tag.ulid} variant="secondary">
                            {tag.name}
                        </Badge>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('As students see it')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <RichText doc={preview.body} className="text-lg" />
                        {preview.image_url && (
                            <img
                                src={preview.image_url}
                                alt={t('Question image')}
                                className="max-h-96 rounded-lg border object-contain"
                            />
                        )}
                        <QuestionTypePlayer
                            type={preview.type}
                            payload={preview.player}
                            value={answer}
                            onChange={setAnswer}
                            idPrefix={`admin-${preview.ulid}`}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Correct answer')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <QuestionTypeReview
                            type={preview.type}
                            review={review}
                        />
                    </CardContent>
                </Card>

                {explanation && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Explanation')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <RichText doc={explanation} />
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

AdminQuestionShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Questions', href: admin.questions.index() },
    ],
};
