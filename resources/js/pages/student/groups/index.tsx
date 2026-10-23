import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, LogOut, Plus, Users } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/data/confirm-dialog';
import { EmptyState } from '@/components/data/empty-state';
import { PageHeader } from '@/components/data/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import student from '@/routes/student';
import type { StudentGroup } from '@/types';

/**
 * The student's groups. Only the group name, its teacher's name and the
 * number of available tests: never the other members.
 */
export default function StudentGroups({ groups }: { groups: StudentGroup[] }) {
    const { t } = useTranslation();
    const [leaving, setLeaving] = useState<StudentGroup | null>(null);
    const [processing, setProcessing] = useState(false);

    const joinButton = (
        <Button asChild>
            <Link href={student.groups.join()}>
                <Plus aria-hidden="true" />
                {t('Join a group')}
            </Link>
        </Button>
    );

    return (
        <>
            <Head title={t('My groups')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('My groups')}
                    description={t(
                        'Your teachers assign tests to these groups.',
                    )}
                    actions={joinButton}
                />

                {groups.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title={t('You are not in a group yet')}
                        description={t(
                            'Ask your teacher for an invite code or a join link.',
                        )}
                        action={joinButton}
                    />
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {groups.map((group) => (
                            <li key={group.ulid}>
                                <Card className="h-full">
                                    <CardHeader>
                                        <CardTitle>{group.name}</CardTitle>
                                        <CardDescription>
                                            {t('Teacher: :name', {
                                                name: group.teacher.name,
                                            })}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="flex-1 space-y-2 text-sm">
                                        {group.description && (
                                            <p className="text-muted-foreground">
                                                {group.description}
                                            </p>
                                        )}
                                        <p className="tabular">
                                            {t(':count tests available', {
                                                count: group.tests_count,
                                            })}
                                        </p>
                                    </CardContent>
                                    <CardFooter className="flex flex-wrap gap-2">
                                        <Button asChild variant="outline">
                                            <Link
                                                href={student.exams.index({
                                                    query: {
                                                        group: group.ulid,
                                                    },
                                                })}
                                            >
                                                <ClipboardList aria-hidden="true" />
                                                {t('Exams')}
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            onClick={() => setLeaving(group)}
                                        >
                                            <LogOut aria-hidden="true" />
                                            {t('Leave')}
                                        </Button>
                                    </CardFooter>
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <ConfirmDialog
                open={leaving !== null}
                onOpenChange={(next) => !next && setLeaving(null)}
                title={t('Leave :name?', { name: leaving?.name ?? '' })}
                description={t(
                    'You will no longer see this group’s tests. An exam you have already started can still be finished; your results stay. You need a new invite code to join again.',
                )}
                confirmLabel={t('Leave')}
                destructive
                processing={processing}
                onConfirm={() =>
                    leaving &&
                    router.delete(student.groups.leave.url(leaving.ulid), {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => setProcessing(false),
                        onSuccess: () => setLeaving(null),
                    })
                }
            />
        </>
    );
}

StudentGroups.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'My groups', href: student.groups.index() },
    ],
};
