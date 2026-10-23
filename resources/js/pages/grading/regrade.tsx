import { Head, router, useHttp } from '@inertiajs/react';
import { RefreshCcw, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/data/confirm-dialog';
import { EmptyState } from '@/components/data/empty-state';
import { PageHeader } from '@/components/data/page-header';
import InputError from '@/components/input-error';
import { QuestionTypeBadge } from '@/components/questions/question-fields';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { QuestionTypeEditor } from '@/question-types/components';
import type { EditorErrors } from '@/question-types/shared/types';
import { dashboard } from '@/routes';
import type { Option, RegradePreview, RegradeVersion } from '@/types';

/**
 * Regrade: fix a question's correct answer in this test's attempt
 * snapshots. Pick the question version, mark the right answer (options
 * themselves can't change), preview the score changes, then confirm with
 * your password. Runs in the background and is audited.
 */
export default function Regrade({
    test,
    versions,
    scorings,
    urls,
}: {
    test: { ulid: string; title: string };
    versions: RegradeVersion[];
    scorings: Option[];
    urls: { preview: string; store: string };
}) {
    const { t } = useTranslation();
    const [selected, setSelected] = useState<RegradeVersion | null>(null);
    const [definition, setDefinition] = useState<unknown>(null);
    const [preview, setPreview] = useState<RegradePreview | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);
    const http = useHttp<
        { question: string; version: number; definition: string },
        RegradePreview
    >({
        question: '',
        version: 0,
        definition: '',
    });

    const choose = (version: RegradeVersion) => {
        setSelected(version);
        setDefinition(version.definition);
        setPreview(null);
        setErrors({});
    };

    const payload = () => ({
        question: selected?.question ?? '',
        version: selected?.version ?? 0,
        definition: JSON.stringify(definition),
    });

    const runPreview = async () => {
        setErrors({});
        http.transform(payload);

        try {
            setPreview(await http.post(urls.preview));
        } catch {
            setErrors(
                Object.fromEntries(
                    Object.entries(http.errors).map(([key, value]) => [
                        key,
                        String(value),
                    ]),
                ),
            );
        }
    };

    const definitionErrors: EditorErrors = Object.fromEntries(
        Object.entries(errors)
            .filter(([key]) => key.startsWith('definition.'))
            .map(([key, value]) => [key.slice('definition.'.length), value]),
    );

    return (
        <>
            <Head title={t('Regrade')} />

            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Regrade: :title', { title: test.title })}
                    description={t(
                        'Fix a correct answer that was wrong. Only this test’s attempts change; the question bank stays as it is.',
                    )}
                />

                {versions.length === 0 ? (
                    <EmptyState
                        icon={RefreshCcw}
                        title={t('Nothing to regrade')}
                        description={t(
                            'No student has attempted this test yet.',
                        )}
                    />
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('1. Question')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y rounded-lg border">
                                {versions.map((version) => {
                                    const isSelected =
                                        selected?.question ===
                                            version.question &&
                                        selected.version === version.version;

                                    return (
                                        <li
                                            key={`${version.question}-${version.version}`}
                                        >
                                            <button
                                                type="button"
                                                onClick={() => choose(version)}
                                                aria-pressed={isSelected}
                                                className="flex w-full flex-wrap items-center gap-3 p-3 text-left hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none aria-pressed:bg-muted/60"
                                            >
                                                <QuestionTypeBadge
                                                    type={version.type}
                                                />
                                                <span className="min-w-0 flex-1 break-words">
                                                    {version.excerpt}
                                                </span>
                                                <span className="tabular text-xs text-muted-foreground">
                                                    {t(
                                                        'Version :version · :count attempts',
                                                        {
                                                            version:
                                                                version.version,
                                                            count: version.uses,
                                                        },
                                                    )}
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                {selected && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('2. Correct answer')}
                            </CardTitle>
                            <CardDescription>
                                {t(
                                    'Change only which answers are correct. Options must stay the same.',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <QuestionTypeEditor
                                key={`${selected.question}-${selected.version}`}
                                type={selected.type}
                                value={definition}
                                onChange={(value) => {
                                    setDefinition(value);
                                    setPreview(null);
                                }}
                                errors={definitionErrors}
                                scorings={scorings}
                            />
                            <InputError
                                message={errors.definition ?? errors.question}
                            />
                            <Button
                                onClick={() => void runPreview()}
                                disabled={http.processing}
                            >
                                {http.processing && <Spinner />}
                                {t('Preview changes')}
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {preview && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('3. Preview')}
                            </CardTitle>
                            <CardDescription className="tabular">
                                {t(
                                    ':attempts attempts, :changed scores change, :flips pass results change.',
                                    {
                                        attempts: preview.summary.affected,
                                        changed: preview.summary.changed,
                                        flips: preview.summary.passed_flips,
                                    },
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="overflow-x-auto rounded-lg border">
                                <table className="w-full text-sm">
                                    <caption className="sr-only">
                                        {t('Score changes')}
                                    </caption>
                                    <thead className="bg-muted/40 text-left">
                                        <tr>
                                            <th scope="col" className="p-2">
                                                {t('Student')}
                                            </th>
                                            <th
                                                scope="col"
                                                className="p-2 text-right"
                                            >
                                                {t('Score now')}
                                            </th>
                                            <th
                                                scope="col"
                                                className="p-2 text-right"
                                            >
                                                {t('Score after')}
                                            </th>
                                            <th scope="col" className="p-2">
                                                {t('Passed')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {preview.changes.map((row) => (
                                            <tr
                                                key={row.attempt}
                                                className="border-t"
                                            >
                                                <td className="p-2">
                                                    {row.student}
                                                </td>
                                                <td className="tabular p-2 text-right">
                                                    {row.old_score ?? '—'}
                                                </td>
                                                <td className="tabular p-2 text-right font-medium">
                                                    {row.new_score ??
                                                        t('graded on submit')}
                                                </td>
                                                <td className="p-2">
                                                    {row.new_score === null
                                                        ? '—'
                                                        : `${passLabel(row.old_passed, t)} → ${passLabel(row.new_passed, t)}`}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <Alert>
                                <TriangleAlert aria-hidden="true" />
                                <AlertTitle>
                                    {t('This changes recorded results')}
                                </AlertTitle>
                                <AlertDescription>
                                    {t(
                                        'Scores, pass results and later certificates follow the new grades. The regrade is recorded in the audit log.',
                                    )}
                                </AlertDescription>
                            </Alert>

                            <Button
                                onClick={() => setConfirming(true)}
                                disabled={processing}
                            >
                                <RefreshCcw aria-hidden="true" />
                                {t('Regrade :count attempts', {
                                    count: preview.summary.affected,
                                })}
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t('Regrade this question?')}
                description={t(
                    'Confirm with your password. The regrade runs in the background.',
                )}
                confirmLabel={t('Regrade')}
                destructive
                requirePassword
                processing={processing}
                onConfirm={() =>
                    router.post(urls.store, payload(), {
                        onStart: () => setProcessing(true),
                        onFinish: () => setProcessing(false),
                        onSuccess: () => {
                            setConfirming(false);
                            setSelected(null);
                            setPreview(null);
                        },
                        onError: (next) => {
                            setConfirming(false);
                            setErrors(next);
                        },
                    })
                }
            />
        </>
    );
}

function passLabel(
    passed: boolean | null,
    t: ReturnType<typeof useTranslation>['t'],
): string {
    return passed === null ? '—' : passed ? t('passed') : t('failed');
}

Regrade.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
