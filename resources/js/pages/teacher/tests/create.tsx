import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/data/page-header';
import InputError from '@/components/input-error';
import { CategorySelect } from '@/components/questions/question-fields';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import teacher from '@/routes/teacher';
import type { TestOptions } from '@/types';

const TITLE_MAX = 150;
const DESCRIPTION_MAX = 2000;

/** Step one: a title (and optionally a description and category); the builder does the rest. */
export default function CreateTest({ options }: { options: TestOptions }) {
    const { t } = useTranslation();
    const [category, setCategory] = useState<string | null>(null);

    return (
        <>
            <Head title={t('New test')} />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('New test')}
                    description={t(
                        'Start with a title. Questions and settings come next, in the builder.',
                    )}
                    actions={
                        <Button asChild variant="ghost">
                            <Link href={teacher.tests.index()}>
                                <ArrowLeft aria-hidden="true" />
                                {t('Tests')}
                            </Link>
                        </Button>
                    }
                />

                <Card>
                    <CardContent>
                        <Form
                            {...teacher.tests.store.form()}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="title">
                                            {t('Title')}
                                        </Label>
                                        <Input
                                            id="title"
                                            name="title"
                                            required
                                            minLength={3}
                                            maxLength={TITLE_MAX}
                                            autoFocus
                                        />
                                        <InputError message={errors.title} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="description">
                                            {t('Description')}
                                        </Label>
                                        <Textarea
                                            id="description"
                                            name="description"
                                            rows={4}
                                            maxLength={DESCRIPTION_MAX}
                                        />
                                        <InputError
                                            message={errors.description}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="category">
                                            {t('Category')}
                                        </Label>
                                        <CategorySelect
                                            id="category"
                                            value={category}
                                            categories={options.categories}
                                            onChange={setCategory}
                                        />
                                        <input
                                            type="hidden"
                                            name="category"
                                            value={category ?? ''}
                                        />
                                        <InputError message={errors.category} />
                                    </div>

                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        {t('Create and open builder')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CreateTest.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Tests', href: teacher.tests.index() },
        { title: 'New test', href: teacher.tests.create() },
    ],
};
