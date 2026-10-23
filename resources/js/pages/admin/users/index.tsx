import { Head, Link } from '@inertiajs/react';
import { Users } from 'lucide-react';
import type { DataTableColumn } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import { EmptyState } from '@/components/data/empty-state';
import { FilterBar } from '@/components/data/filter-bar';
import { PageHeader } from '@/components/data/page-header';
import { Pagination } from '@/components/data/pagination';
import { StatusBadge } from '@/components/data/status-badge';
import { RoleBadge } from '@/components/role-badge';
import { useListing } from '@/hooks/use-listing';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import type { AdminUser, ListingState, Option, Paginated, Role } from '@/types';

type Filters = ListingState & {
    role: Role | null;
    status: 'active' | 'blocked' | null;
    verified: 'yes' | 'no' | null;
};

export default function UsersIndex({
    users,
    filters,
    roles,
}: {
    users: Paginated<AdminUser>;
    filters: Filters;
    roles: Option<Role>[];
}) {
    const { t, formatDate } = useTranslation();
    const { update, loading } = useListing({
        query: filters,
        only: ['users', 'filters'],
    });

    const columns: DataTableColumn<AdminUser>[] = [
        {
            key: 'name',
            header: t('Name'),
            sortKey: 'name',
            primary: true,
            cell: (user) => (
                <div className="min-w-0">
                    <Link
                        href={admin.users.show(user.ulid)}
                        className="font-medium underline-offset-4 hover:underline"
                    >
                        {user.name}
                    </Link>
                    <p className="truncate text-sm text-muted-foreground">
                        {user.email}
                    </p>
                </div>
            ),
        },
        {
            key: 'role',
            header: t('Role'),
            cell: (user) => <RoleBadge role={user.role} />,
        },
        {
            key: 'status',
            header: t('Status'),
            cell: (user) =>
                user.blocked ? (
                    <StatusBadge tone="danger" label={t('Blocked')} />
                ) : (
                    <StatusBadge tone="success" label={t('Active')} />
                ),
        },
        {
            key: 'verified',
            header: t('Email'),
            cell: (user) =>
                user.verified ? (
                    <StatusBadge tone="success" label={t('Verified')} />
                ) : (
                    <StatusBadge tone="warning" label={t('Unverified')} />
                ),
        },
        {
            key: '2fa',
            header: t('2FA'),
            cell: (user) =>
                user.has_two_factor ? (
                    <StatusBadge tone="success" label={t('On')} />
                ) : (
                    <StatusBadge tone="neutral" label={t('Off')} />
                ),
        },
        {
            key: 'xp',
            header: t('XP'),
            sortKey: 'xp_total',
            align: 'end',
            cell: (user) => <span className="tabular">{user.xp_total}</span>,
        },
        {
            key: 'created',
            header: t('Joined'),
            sortKey: 'created_at',
            cell: (user) =>
                user.created_at ? formatDate(user.created_at) : '—',
        },
    ];

    return (
        <>
            <Head title={t('Users')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Users')}
                    description={t(
                        'Search accounts, change roles and block access.',
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
                            key: 'role',
                            label: t('Role'),
                            value: filters.role,
                            options: roles,
                            onChange: (role) => update({ role }),
                        },
                        {
                            key: 'status',
                            label: t('Status'),
                            value: filters.status,
                            options: [
                                { value: 'active', label: t('Active') },
                                { value: 'blocked', label: t('Blocked') },
                            ],
                            onChange: (status) => update({ status }),
                        },
                        {
                            key: 'verified',
                            label: t('Email'),
                            value: filters.verified,
                            options: [
                                { value: 'yes', label: t('Verified') },
                                { value: 'no', label: t('Unverified') },
                            ],
                            onChange: (verified) => update({ verified }),
                        },
                    ]}
                    onReset={() =>
                        update({
                            search: null,
                            role: null,
                            status: null,
                            verified: null,
                        })
                    }
                />

                <DataTable
                    caption={t('Users')}
                    columns={columns}
                    rows={users.data}
                    rowKey={(user) => user.ulid}
                    loading={loading}
                    sort={{ key: filters.sort, direction: filters.direction }}
                    onSort={(sort, direction) => update({ sort, direction })}
                    empty={
                        <EmptyState
                            icon={Users}
                            title={t('No users found')}
                            description={t(
                                'Try a different search or clear the filters.',
                            )}
                        />
                    }
                />

                <Pagination
                    meta={users.meta}
                    onPageChange={(page) => update({ page })}
                />
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Users', href: admin.users.index() },
    ],
};
