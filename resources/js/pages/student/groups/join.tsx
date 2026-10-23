import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { PageHeader } from '@/components/data/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import student from '@/routes/student';

/** Join a group with an invite code (pre-filled from a join link). */
export default function JoinGroup({ code }: { code: string | null }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Join a group')} />

            <div className="mx-auto flex w-full max-w-lg flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Join a group')}
                    description={t(
                        'Enter the invite code your teacher gave you.',
                    )}
                    actions={
                        <Button asChild variant="ghost">
                            <Link href={student.groups.index()}>
                                <ArrowLeft aria-hidden="true" />
                                {t('My groups')}
                            </Link>
                        </Button>
                    }
                />

                <Card>
                    <CardContent>
                        <Form
                            {...student.groups.join.store.form()}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="code">
                                            {t('Invite code')}
                                        </Label>
                                        <Input
                                            id="code"
                                            name="code"
                                            required
                                            maxLength={32}
                                            autoComplete="off"
                                            autoCapitalize="characters"
                                            spellCheck={false}
                                            defaultValue={code ?? ''}
                                            autoFocus
                                            className="tabular text-lg tracking-[0.2em] uppercase"
                                            aria-invalid={
                                                errors.code ? true : undefined
                                            }
                                        />
                                        <InputError message={errors.code} />
                                    </div>

                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        {t('Join')}
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

JoinGroup.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'My groups', href: student.groups.index() },
        { title: 'Join a group', href: student.groups.join() },
    ],
};
