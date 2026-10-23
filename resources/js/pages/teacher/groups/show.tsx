import { Head, Link, router, useHttp } from '@inertiajs/react';
import {
    Archive,
    ArrowLeft,
    Check,
    ClipboardList,
    Copy,
    Eye,
    EyeOff,
    Pencil,
    Plus,
    RefreshCw,
    Trash2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/data/confirm-dialog';
import type { DataTableColumn } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import { EmptyState } from '@/components/data/empty-state';
import { FilterBar } from '@/components/data/filter-bar';
import { PageHeader } from '@/components/data/page-header';
import { Pagination } from '@/components/data/pagination';
import { ArchiveGroupDialog } from '@/components/groups/archive-group-dialog';
import { JoinStatusBadge } from '@/components/groups/join-status-badge';
import { TestStatusBadge } from '@/components/tests/test-status-badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';
import { useListing } from '@/hooks/use-listing';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import teacher from '@/routes/teacher';
import type {
    GroupActivity,
    GroupDetail,
    GroupInvite,
    GroupMember,
    GroupTest,
    ListingState,
    Option,
    Paginated,
} from '@/types';

// synapse-allow-color: a QR code needs a light quiet zone to scan, also in dark mode.
const QR_CLASS = 'mx-auto size-60 rounded-lg bg-white p-2';

type Tab = 'overview' | 'invite' | 'members' | 'tests';

type ShowFilters = ListingState & { tab: Tab };

export default function GroupShow({
    group,
    tab,
    members,
    filters,
    tests,
    assignableTests,
    activity,
}: {
    group: GroupDetail;
    tab: Tab;
    members: Paginated<GroupMember>;
    filters: ShowFilters;
    tests: GroupTest[];
    assignableTests: Option[];
    activity: GroupActivity;
}) {
    const { t } = useTranslation();
    const { update, loading } = useListing({
        query: filters,
        only: ['members', 'filters', 'tab'],
    });
    const [archiving, setArchiving] = useState(false);
    const [processing, setProcessing] = useState(false);

    const tabs: { key: Tab; label: string }[] = [
        { key: 'overview', label: t('Overview') },
        { key: 'invite', label: t('Invite') },
        { key: 'members', label: t('Members') },
        { key: 'tests', label: t('Tests') },
    ];

    return (
        <>
            <Head title={group.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={group.name}
                    description={group.description ?? undefined}
                    actions={
                        <>
                            <Button asChild variant="ghost">
                                <Link href={teacher.groups.index()}>
                                    <ArrowLeft aria-hidden="true" />
                                    {t('Groups')}
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={teacher.groups.edit(group.ulid)}>
                                    <Pencil aria-hidden="true" />
                                    {t('Edit')}
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                onClick={() => setArchiving(true)}
                            >
                                <Archive aria-hidden="true" />
                                {t('Archive')}
                            </Button>
                        </>
                    }
                />

                <div
                    role="tablist"
                    aria-label={t('Group')}
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
                            onClick={() => update({ tab: item.key })}
                            className={cn(
                                'shrink-0 rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                tab === item.key
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>

                <div
                    role="tabpanel"
                    id={`panel-${tab}`}
                    aria-labelledby={`tab-${tab}`}
                >
                    {tab === 'overview' && (
                        <OverviewPanel group={group} activity={activity} />
                    )}
                    {tab === 'invite' && <InvitePanel group={group} />}
                    {tab === 'members' && (
                        <MembersPanel
                            group={group}
                            members={members}
                            filters={filters}
                            loading={loading}
                            update={update}
                        />
                    )}
                    {tab === 'tests' && (
                        <TestsPanel
                            group={group}
                            tests={tests}
                            assignableTests={assignableTests}
                        />
                    )}
                </div>
            </div>

            <ArchiveGroupDialog
                group={archiving ? group : null}
                processing={processing}
                onClose={() => setArchiving(false)}
                onConfirm={() =>
                    router.post(
                        teacher.groups.archive.url(group.ulid),
                        {},
                        {
                            onStart: () => setProcessing(true),
                            onFinish: () => setProcessing(false),
                        },
                    )
                }
            />
        </>
    );
}

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="tabular text-3xl">{value}</CardTitle>
            </CardHeader>
        </Card>
    );
}

function OverviewPanel({
    group,
    activity,
}: {
    group: GroupDetail;
    activity: GroupActivity;
}) {
    const { t, formatDate } = useTranslation();

    return (
        <div className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-3">
                <Stat label={t('Members')} value={group.members_count} />
                <Stat label={t('Assigned tests')} value={group.tests_count} />
                <Card>
                    <CardHeader>
                        <CardDescription>{t('Joining')}</CardDescription>
                        <div className="pt-1">
                            <JoinStatusBadge
                                joiningEnabled={group.joining_enabled}
                                acceptsJoins={group.accepts_joins}
                            />
                        </div>
                    </CardHeader>
                </Card>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('Recently joined')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {activity.joins.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('Nobody has joined yet.')}
                            </p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {activity.joins.map((join, index) => (
                                    <li
                                        key={index}
                                        className="flex justify-between gap-3"
                                    >
                                        <span>{join.name}</span>
                                        <span className="text-muted-foreground">
                                            {join.at
                                                ? formatDate(join.at, 'PPp')
                                                : ''}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('Recently finished attempts')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {activity.finished.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('No finished attempts yet.')}
                            </p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {activity.finished.map((attempt, index) => (
                                    <li
                                        key={index}
                                        className="flex justify-between gap-3"
                                    >
                                        <span>
                                            {attempt.name} · {attempt.test}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {attempt.at
                                                ? formatDate(attempt.at, 'PPp')
                                                : ''}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function InvitePanel({ group }: { group: GroupDetail }) {
    const { t, formatDate } = useTranslation();
    const http = useHttp<Record<string, never>, GroupInvite>({});
    const [invite, setInvite] = useState<GroupInvite | null>(null);
    const [failed, setFailed] = useState(false);
    const [copied, copy] = useClipboard();
    const [regenerating, setRegenerating] = useState(false);
    const [processing, setProcessing] = useState(false);

    const reveal = async () => {
        setFailed(false);

        try {
            setInvite(
                await http.get(teacher.groups.inviteCode.show.url(group.ulid)),
            );
        } catch {
            setFailed(true);
        }
    };

    const visit = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
        onSuccess: () => {
            setRegenerating(false);
            setInvite(null);
        },
    };

    return (
        <div className="grid gap-6 lg:grid-cols-[1fr_auto]">
            <Card>
                <CardHeader>
                    <CardTitle>{t('Invite code')}</CardTitle>
                    <CardDescription>
                        {group.invite_expires_at
                            ? t('Expires :date.', {
                                  date: formatDate(
                                      group.invite_expires_at,
                                      'PPp',
                                  ),
                              })
                            : t('The code never expires.')}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="flex flex-wrap items-center gap-3">
                        <code
                            className="tabular rounded-lg border bg-muted/40 px-4 py-2 text-2xl tracking-[0.3em]"
                            aria-live="polite"
                        >
                            {invite ? invite.code : '••••••••••'}
                        </code>
                        {invite ? (
                            <Button
                                variant="outline"
                                onClick={() => setInvite(null)}
                            >
                                <EyeOff aria-hidden="true" />
                                {t('Hide')}
                            </Button>
                        ) : (
                            <Button
                                variant="outline"
                                onClick={reveal}
                                disabled={http.processing}
                            >
                                {http.processing ? (
                                    <Spinner />
                                ) : (
                                    <Eye aria-hidden="true" />
                                )}
                                {t('Reveal')}
                            </Button>
                        )}
                    </div>

                    {failed && (
                        <Alert variant="destructive">
                            <AlertDescription>
                                {t('The code could not be loaded.')}
                            </AlertDescription>
                        </Alert>
                    )}

                    {invite && (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={() => copy(invite.code)}
                            >
                                {copied === invite.code ? (
                                    <Check aria-hidden="true" />
                                ) : (
                                    <Copy aria-hidden="true" />
                                )}
                                {t('Copy code')}
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => copy(invite.join_url)}
                            >
                                {copied === invite.join_url ? (
                                    <Check aria-hidden="true" />
                                ) : (
                                    <Copy aria-hidden="true" />
                                )}
                                {t('Copy join link')}
                            </Button>
                        </div>
                    )}

                    <div className="flex flex-wrap items-center gap-3 border-t pt-6">
                        <JoinStatusBadge
                            joiningEnabled={group.joining_enabled}
                            acceptsJoins={group.accepts_joins}
                        />
                        <Button
                            variant="outline"
                            disabled={processing}
                            onClick={() =>
                                router.put(
                                    teacher.groups.joining.update.url(
                                        group.ulid,
                                    ),
                                    { joining_enabled: !group.joining_enabled },
                                    visit,
                                )
                            }
                        >
                            {group.joining_enabled
                                ? t('Turn joining off')
                                : t('Turn joining on')}
                        </Button>
                        <Button
                            variant="ghost"
                            disabled={processing}
                            onClick={() => setRegenerating(true)}
                        >
                            <RefreshCw aria-hidden="true" />
                            {t('Regenerate code')}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {invite && (
                <Card className="lg:w-72">
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('QR code')}
                        </CardTitle>
                        <CardDescription>
                            {t('Students scan it to open the join link.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <img
                            src={invite.qr}
                            alt={t('QR code of the join link')}
                            className={QR_CLASS}
                        />
                    </CardContent>
                </Card>
            )}

            <ConfirmDialog
                open={regenerating}
                onOpenChange={setRegenerating}
                title={t('Regenerate the invite code?')}
                description={t(
                    'The current code and join link stop working immediately. Members stay in the group.',
                )}
                confirmLabel={t('Regenerate')}
                destructive
                processing={processing}
                onConfirm={() =>
                    router.post(
                        teacher.groups.inviteCode.regenerate.url(group.ulid),
                        {},
                        visit,
                    )
                }
            />
        </div>
    );
}

function MembersPanel({
    group,
    members,
    filters,
    loading,
    update,
}: {
    group: GroupDetail;
    members: Paginated<GroupMember>;
    filters: ShowFilters;
    loading: boolean;
    update: ReturnType<typeof useListing>['update'];
}) {
    const { t, formatDate } = useTranslation();
    const [removing, setRemoving] = useState<GroupMember | null>(null);
    const [processing, setProcessing] = useState(false);

    const columns: DataTableColumn<GroupMember>[] = [
        {
            key: 'name',
            header: t('Name'),
            sortKey: 'name',
            primary: true,
            cell: (member) => (
                <span className="font-medium">{member.name}</span>
            ),
        },
        {
            key: 'email',
            header: t('Email'),
            cell: (member) => (
                <span className="break-all text-muted-foreground">
                    {member.email}
                </span>
            ),
        },
        {
            key: 'joined',
            header: t('Joined'),
            sortKey: 'joined_at',
            cell: (member) =>
                member.joined_at ? formatDate(member.joined_at) : '—',
        },
        {
            key: 'attempts',
            header: t('Attempts'),
            align: 'end',
            cell: (member) => (
                <span className="tabular">{member.attempts_count}</span>
            ),
        },
    ];

    return (
        <div className="space-y-4">
            <FilterBar
                search={{
                    value: filters.search,
                    onChange: (search) => update({ search }),
                    placeholder: t('Search name or email'),
                }}
                onReset={() => update({ search: null })}
            />

            <DataTable
                caption={t('Members')}
                columns={columns}
                rows={members.data}
                rowKey={(member) => member.ulid}
                loading={loading}
                sort={{ key: filters.sort, direction: filters.direction }}
                onSort={(sort, direction) => update({ sort, direction })}
                rowActions={(member) => (
                    <div className="flex justify-end">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('Remove :name', {
                                name: member.name,
                            })}
                            onClick={() => setRemoving(member)}
                        >
                            <Trash2 aria-hidden="true" />
                        </Button>
                    </div>
                )}
                empty={
                    <EmptyState
                        icon={Users}
                        title={t('No members yet')}
                        description={t(
                            'Share the invite code: students appear here once they join.',
                        )}
                    />
                }
            />

            <Pagination
                meta={members.meta}
                onPageChange={(page) => update({ page })}
            />

            <ConfirmDialog
                open={removing !== null}
                onOpenChange={(next) => !next && setRemoving(null)}
                title={t('Remove :name from the group?', {
                    name: removing?.name ?? '',
                })}
                description={t(
                    'They no longer see or start this group’s tests. An attempt already in progress can still be finished; past results stay.',
                )}
                confirmLabel={t('Remove')}
                destructive
                processing={processing}
                onConfirm={() =>
                    removing &&
                    router.delete(
                        teacher.groups.members.destroy.url({
                            group: group.ulid,
                            member: removing.ulid,
                        }),
                        {
                            preserveScroll: true,
                            onStart: () => setProcessing(true),
                            onFinish: () => setProcessing(false),
                            onSuccess: () => setRemoving(null),
                        },
                    )
                }
            />
        </div>
    );
}

function TestsPanel({
    group,
    tests,
    assignableTests,
}: {
    group: GroupDetail;
    tests: GroupTest[];
    assignableTests: Option[];
}) {
    const { t, formatDate } = useTranslation();
    const [selected, setSelected] = useState<string | null>(null);
    const [unassigning, setUnassigning] = useState<GroupTest | null>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const availability = (test: GroupTest) => {
        if (test.opens_at === null && test.closes_at === null) {
            return t('Always open');
        }

        const from = test.opens_at ? formatDate(test.opens_at, 'PPp') : '…';
        const to = test.closes_at ? formatDate(test.closes_at, 'PPp') : '…';

        return `${from} – ${to}`;
    };

    const visit = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
        onSuccess: () => {
            setSelected(null);
            setUnassigning(null);
            setError(null);
        },
        onError: (errors: Record<string, string>) =>
            setError(errors.test ?? null),
    };

    const columns: DataTableColumn<GroupTest>[] = [
        {
            key: 'title',
            header: t('Title'),
            primary: true,
            cell: (test) => (
                <Link
                    href={teacher.tests.edit(test.ulid)}
                    className="font-medium underline-offset-4 hover:underline"
                >
                    {test.title}
                </Link>
            ),
        },
        {
            key: 'status',
            header: t('Status'),
            cell: (test) => <TestStatusBadge status={test.status} />,
        },
        {
            key: 'window',
            header: t('Availability'),
            cell: (test) => (
                <span className="text-sm">{availability(test)}</span>
            ),
        },
        {
            key: 'assigned',
            header: t('Assigned'),
            cell: (test) =>
                test.assigned_at ? formatDate(test.assigned_at) : '—',
        },
    ];

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        {t('Assign a test')}
                    </CardTitle>
                    <CardDescription>
                        {t(
                            'Only your published tests can be assigned. Members see them in their exams list.',
                        )}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {assignableTests.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'All your published tests are already assigned to this group.',
                            )}
                        </p>
                    ) : (
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="grid min-w-64 flex-1 gap-1.5">
                                <Label htmlFor="assign-test">{t('Test')}</Label>
                                <Select
                                    value={selected ?? undefined}
                                    onValueChange={setSelected}
                                >
                                    <SelectTrigger id="assign-test">
                                        <SelectValue
                                            placeholder={t('Choose a test')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {assignableTests.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button
                                disabled={selected === null || processing}
                                onClick={() =>
                                    router.post(
                                        teacher.groups.tests.store.url(
                                            group.ulid,
                                        ),
                                        { test: selected },
                                        visit,
                                    )
                                }
                            >
                                <Plus aria-hidden="true" />
                                {t('Assign')}
                            </Button>
                        </div>
                    )}
                    {error && (
                        <Alert variant="destructive" className="mt-4">
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}
                </CardContent>
            </Card>

            <DataTable
                caption={t('Assigned tests')}
                columns={columns}
                rows={tests}
                rowKey={(test) => test.ulid}
                rowActions={(test) => (
                    <div className="flex justify-end">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('Unassign :title', {
                                title: test.title,
                            })}
                            onClick={() => setUnassigning(test)}
                        >
                            <Trash2 aria-hidden="true" />
                        </Button>
                    </div>
                )}
                empty={
                    <EmptyState
                        icon={ClipboardList}
                        title={t('No tests assigned')}
                        description={t(
                            'Assign a published test so the group’s students can take it.',
                        )}
                    />
                }
            />

            <ConfirmDialog
                open={unassigning !== null}
                onOpenChange={(next) => !next && setUnassigning(null)}
                title={t('Unassign :title?', {
                    title: unassigning?.title ?? '',
                })}
                description={t(
                    'The group’s students no longer see this test. Attempts in progress can still be finished; results stay.',
                )}
                confirmLabel={t('Unassign')}
                destructive
                processing={processing}
                onConfirm={() =>
                    unassigning &&
                    router.delete(
                        teacher.groups.tests.destroy.url({
                            group: group.ulid,
                            test: unassigning.ulid,
                        }),
                        visit,
                    )
                }
            />
        </div>
    );
}

GroupShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Groups', href: teacher.groups.index() },
    ],
};
