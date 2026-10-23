import { Head, Link, router } from '@inertiajs/react';
import { Check, UserCheck, X } from 'lucide-react';
import { useState } from 'react';
import type { DataTableColumn } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import { ConfirmDialog } from '@/components/data/confirm-dialog';
import { EmptyState } from '@/components/data/empty-state';
import { FilterBar } from '@/components/data/filter-bar';
import { PageHeader } from '@/components/data/page-header';
import { Pagination } from '@/components/data/pagination';
import type { StatusTone } from '@/components/data/status-badge';
import { StatusBadge } from '@/components/data/status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useListing } from '@/hooks/use-listing';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import type {
    ListingState,
    Option,
    Paginated,
    TeacherAccessRequest,
    TeacherRequestStatus,
} from '@/types';

const tones: Record<TeacherRequestStatus, StatusTone> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
};

type Review = { request: TeacherAccessRequest; decision: 'approve' | 'reject' };

export default function TeacherRequestsIndex({
    requests,
    filters,
    statuses,
}: {
    requests: Paginated<TeacherAccessRequest>;
    filters: ListingState & { status: TeacherRequestStatus | null };
    statuses: Option<TeacherRequestStatus>[];
}) {
    const { t, formatDate } = useTranslation();
    const { update, loading } = useListing({
        query: filters,
        only: ['requests', 'filters', 'navBadges'],
    });
    const [review, setReview] = useState<Review | null>(null);
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState(false);

    const statusLabel = (status: TeacherRequestStatus) =>
        statuses.find((option) => option.value === status)?.label ?? status;

    const startReview = (next: Review) => {
        setNote('');
        setReview(next);
    };

    const submitReview = () => {
        if (!review) {
            return;
        }

        const route =
            review.decision === 'approve'
                ? admin.teacherRequests.approve
                : admin.teacherRequests.reject;

        router.post(
            route.url(review.request.ulid),
            { note },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setReview(null),
            },
        );
    };

    const columns: DataTableColumn<TeacherAccessRequest>[] = [
        {
            key: 'user',
            header: t('Student'),
            primary: true,
            cell: (request) =>
                request.user ? (
                    <div className="min-w-0">
                        <Link
                            href={admin.users.show(request.user.ulid)}
                            className="font-medium underline-offset-4 hover:underline"
                        >
                            {request.user.name}
                        </Link>
                        <p className="truncate text-sm text-muted-foreground">
                            {request.user.email}
                        </p>
                    </div>
                ) : (
                    '—'
                ),
        },
        {
            key: 'message',
            header: t('Message'),
            className: 'max-w-md',
            cell: (request) => (
                <p className="line-clamp-3 break-words whitespace-pre-line">
                    {request.message}
                </p>
            ),
        },
        {
            key: 'status',
            header: t('Status'),
            cell: (request) => (
                <div className="space-y-1">
                    <StatusBadge
                        tone={tones[request.status]}
                        label={statusLabel(request.status)}
                    />
                    {request.reviewer && (
                        <p className="text-xs text-muted-foreground">
                            {request.reviewer.name}
                        </p>
                    )}
                </div>
            ),
        },
        {
            key: 'created',
            header: t('Sent'),
            sortKey: 'created_at',
            cell: (request) =>
                request.created_at ? formatDate(request.created_at) : '—',
        },
    ];

    return (
        <>
            <Head title={t('Teacher requests')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Teacher requests')}
                    description={t(
                        'Students asking for the teacher role. Pending requests come first.',
                    )}
                />

                <FilterBar
                    search={{
                        value: filters.search,
                        onChange: (search) => update({ search }),
                        placeholder: t('Name or email'),
                    }}
                    filters={[
                        {
                            key: 'status',
                            label: t('Status'),
                            value: filters.status,
                            options: statuses,
                            onChange: (status) => update({ status }),
                        },
                    ]}
                />

                <DataTable
                    caption={t('Teacher requests')}
                    columns={columns}
                    rows={requests.data}
                    rowKey={(request) => request.ulid}
                    loading={loading}
                    sort={{ key: filters.sort, direction: filters.direction }}
                    onSort={(sort, direction) => update({ sort, direction })}
                    rowActions={(request) =>
                        request.status === 'pending' ? (
                            <div className="flex justify-end gap-2">
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        startReview({
                                            request,
                                            decision: 'approve',
                                        })
                                    }
                                >
                                    <Check aria-hidden="true" />
                                    {t('Approve')}
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        startReview({
                                            request,
                                            decision: 'reject',
                                        })
                                    }
                                >
                                    <X aria-hidden="true" />
                                    {t('Reject')}
                                </Button>
                            </div>
                        ) : null
                    }
                    empty={
                        <EmptyState
                            icon={UserCheck}
                            title={t('No requests')}
                            description={t(
                                'Requests from students will appear here.',
                            )}
                        />
                    }
                />

                <Pagination
                    meta={requests.meta}
                    onPageChange={(page) => update({ page })}
                />
            </div>

            <ConfirmDialog
                open={review !== null}
                onOpenChange={(next) => !next && setReview(null)}
                title={
                    review?.decision === 'approve'
                        ? t('Approve :name as a teacher?', {
                              name: review.request.user?.name ?? '',
                          })
                        : t('Reject the request from :name?', {
                              name: review?.request.user?.name ?? '',
                          })
                }
                description={
                    review?.decision === 'approve'
                        ? t(
                              'They become a Teacher immediately and receive an email.',
                          )
                        : t(
                              'They receive an email and can try again after the cooldown.',
                          )
                }
                confirmLabel={
                    review?.decision === 'approve' ? t('Approve') : t('Reject')
                }
                destructive={review?.decision === 'reject'}
                requirePassword={review?.decision === 'approve'}
                processing={processing}
                onConfirm={submitReview}
            >
                <div className="grid gap-2">
                    <Label htmlFor="review-note">
                        {t('Note to the student (optional)')}
                    </Label>
                    <Textarea
                        id="review-note"
                        value={note}
                        maxLength={1000}
                        onChange={(event) => setNote(event.target.value)}
                    />
                </div>
            </ConfirmDialog>
        </>
    );
}

TeacherRequestsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Teacher requests', href: admin.teacherRequests.index() },
    ],
};
