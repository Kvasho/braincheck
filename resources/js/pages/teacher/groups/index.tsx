import { Head, Link, router } from '@inertiajs/react';
import { Archive, Pencil, Plus, Users } from 'lucide-react';
import { useState } from 'react';
import { ArchiveGroupDialog } from '@/components/groups/archive-group-dialog';
import type { DataTableColumn } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import { EmptyState } from '@/components/data/empty-state';
import { FilterBar } from '@/components/data/filter-bar';
import { PageHeader } from '@/components/data/page-header';
import { Pagination } from '@/components/data/pagination';
import { StatusBadge } from '@/components/data/status-badge';
import { JoinStatusBadge } from '@/components/groups/join-status-badge';
import { Button } from '@/components/ui/button';
import { useListing } from '@/hooks/use-listing';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import teacher from '@/routes/teacher';
import type { GroupRow, ListingState, Paginated } from '@/types';

type GroupFilters = ListingState & { archived: 'only' | null };

export default function GroupsIndex({
    groups,
    filters,
}: {
    groups: Paginated<GroupRow>;
    filters: GroupFilters;
}) {
    const { t, formatDate } = useTranslation();
    const { update, loading } = useListing({
        query: filters,
        only: ['groups', 'filters'],
    });
    const [archiving, setArchiving] = useState<GroupRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const columns: DataTableColumn<GroupRow>[] = [
        {
            key: 'name',
            header: t('Name'),
            sortKey: 'name',
            primary: true,
            cell: (group) =>
                group.archived ? (
                    <span className="font-medium">{group.name}</span>
                ) : (
                    <Link
                        href={teacher.groups.show(group.ulid)}
                        className="font-medium break-words underline-offset-4 hover:underline"
                    >
                        {group.name}
                    </Link>
                ),
        },
        {
            key: 'members',
            header: t('Members'),
            align: 'end',
            cell: (group) => (
                <span className="tabular">{group.members_count}</span>
            ),
        },
        {
            key: 'tests',
            header: t('Assigned tests'),
            align: 'end',
            cell: (group) => (
                <span className="tabular">{group.tests_count}</span>
            ),
        },
        {
            key: 'joining',
            header: t('Joining'),
            cell: (group) =>
                group.archived ? (
                    <StatusBadge
                        tone="neutral"
                        icon={Archive}
                        label={t('Archived')}
                    />
                ) : (
                    <JoinStatusBadge
                        joiningEnabled={group.joining_enabled}
                        acceptsJoins={group.accepts_joins}
                    />
                ),
        },
        {
            key: 'expiry',
            header: t('Invite expires'),
            cell: (group) =>
                group.invite_expires_at
                    ? formatDate(group.invite_expires_at, 'PPp')
                    : t('Never'),
        },
        {
            key: 'created',
            header: t('Created'),
            sortKey: 'created_at',
            cell: (group) =>
                group.created_at ? formatDate(group.created_at) : '—',
        },
    ];

    return (
        <>
            <Head title={t('Groups')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Groups')}
                    description={t(
                        'Your classes. Students join with an invite code; you assign tests to them.',
                    )}
                    actions={
                        <Button asChild>
                            <Link href={teacher.groups.create()}>
                                <Plus aria-hidden="true" />
                                {t('New group')}
                            </Link>
                        </Button>
                    }
                />

                <FilterBar
                    search={{
                        value: filters.search,
                        onChange: (search) => update({ search }),
                        placeholder: t('Search group names'),
                    }}
                    filters={[
                        {
                            key: 'archived',
                            label: t('Archive'),
                            value: filters.archived,
                            options: [
                                { value: 'only', label: t('Archived only') },
                            ],
                            onChange: (archived) => update({ archived }),
                        },
                    ]}
                    onReset={() => update({ search: null, archived: null })}
                />

                <DataTable
                    caption={t('Groups')}
                    columns={columns}
                    rows={groups.data}
                    rowKey={(group) => group.ulid}
                    loading={loading}
                    sort={{ key: filters.sort, direction: filters.direction }}
                    onSort={(sort, direction) => update({ sort, direction })}
                    rowActions={(group) =>
                        group.archived ? null : (
                            <div className="flex justify-end gap-1">
                                <Button asChild variant="ghost" size="icon">
                                    <Link
                                        href={teacher.groups.edit(group.ulid)}
                                        aria-label={t('Edit')}
                                    >
                                        <Pencil aria-hidden="true" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Archive')}
                                    onClick={() => setArchiving(group)}
                                >
                                    <Archive aria-hidden="true" />
                                </Button>
                            </div>
                        )
                    }
                    empty={
                        <EmptyState
                            icon={Users}
                            title={t('No groups found')}
                            description={t(
                                'Create a group and share its invite code with your students.',
                            )}
                            action={
                                <Button asChild variant="outline">
                                    <Link href={teacher.groups.create()}>
                                        {t('New group')}
                                    </Link>
                                </Button>
                            }
                        />
                    }
                />

                <Pagination
                    meta={groups.meta}
                    onPageChange={(page) => update({ page })}
                />
            </div>

            <ArchiveGroupDialog
                group={archiving}
                processing={processing}
                onClose={() => setArchiving(null)}
                onConfirm={(group) =>
                    router.post(
                        teacher.groups.archive.url(group.ulid),
                        {},
                        {
                            preserveScroll: true,
                            onStart: () => setProcessing(true),
                            onFinish: () => setProcessing(false),
                            onSuccess: () => setArchiving(null),
                        },
                    )
                }
            />
        </>
    );
}

GroupsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Groups', href: teacher.groups.index() },
    ],
};
