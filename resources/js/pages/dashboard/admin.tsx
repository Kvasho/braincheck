import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Ban,
    ShieldAlert,
    ShieldCheck,
    UserCheck,
    UserPlus,
    Users,
} from 'lucide-react';
import { AuditActivityList } from '@/components/audit-activity-list';
import { StatusBadge } from '@/components/data/status-badge';
import type { PlatformAnalyticsData } from '@/components/analytics/platform-analytics';
import { PlatformAnalytics } from '@/components/analytics/platform-analytics';
import { RegistrationsChart } from '@/components/registrations-chart';
import { RoleBadge } from '@/components/role-badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import type { AuditEntry, Role } from '@/types';

/** Mirrors App\Support\AdminDashboardStats. */
type Stats = {
    users_by_role: Record<Role, number>;
    new_users_7d: number;
    blocked_users: number;
    pending_teacher_requests: number;
    failed_logins_24h: {
        total: number;
        top_ips: { ip: string; count: number }[];
        top_emails: { email: string; count: number }[];
    };
    privileged_without_two_factor: number;
    two_factor_enforced: boolean;
    registrations_30d: { date: string; count: number }[];
    generated_at: string;
};

export default function AdminDashboard({
    stats,
    activity,
    platform,
}: {
    stats: Stats;
    activity: AuditEntry[];
    platform?: PlatformAnalyticsData | null;
}) {
    const { t, formatDate } = useTranslation();
    const { auth } = usePage().props;
    const totalUsers = Object.values(stats.users_by_role).reduce(
        (sum, count) => sum + count,
        0,
    );

    return (
        <>
            <Head title={t('Administrator dashboard')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="space-y-2">
                    <RoleBadge role={auth.user.role} />
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('Welcome back, :name', { name: auth.user.name })}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('Numbers refresh every minute. Last update: :time', {
                            time: formatDate(stats.generated_at, 'p'),
                        })}
                    </p>
                </header>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={Users}
                        label={t('Users')}
                        value={totalUsers}
                        href={admin.users.index()}
                    >
                        <ul className="tabular flex flex-wrap gap-x-3 gap-y-1 text-sm text-muted-foreground">
                            <li>
                                {t('Admins')}: {stats.users_by_role.admin}
                            </li>
                            <li>
                                {t('Teachers')}: {stats.users_by_role.teacher}
                            </li>
                            <li>
                                {t('Students')}: {stats.users_by_role.student}
                            </li>
                        </ul>
                    </StatCard>
                    <StatCard
                        icon={UserPlus}
                        label={t('New in the last 7 days')}
                        value={stats.new_users_7d}
                    />
                    <StatCard
                        icon={Ban}
                        label={t('Blocked users')}
                        value={stats.blocked_users}
                        href={admin.users.index({
                            query: { status: 'blocked' },
                        })}
                    />
                    <StatCard
                        icon={UserCheck}
                        label={t('Pending teacher requests')}
                        value={stats.pending_teacher_requests}
                        href={admin.teacherRequests.index({
                            query: { status: 'pending' },
                        })}
                    />
                </div>

                {platform && <PlatformAnalytics data={platform} />}

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>
                                {t('Registrations, last 30 days')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <RegistrationsChart
                                days={stats.registrations_30d}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Security')}</CardTitle>
                            <CardDescription>
                                {t('Failed sign-ins in the last 24 hours')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5 text-sm">
                            <p className="tabular text-3xl font-semibold">
                                {stats.failed_logins_24h.total}
                            </p>

                            <TopList
                                title={t('Top IP addresses')}
                                rows={stats.failed_logins_24h.top_ips.map(
                                    (row) => ({
                                        label: row.ip,
                                        count: row.count,
                                    }),
                                )}
                            />
                            <TopList
                                title={t('Top attempted emails')}
                                rows={stats.failed_logins_24h.top_emails.map(
                                    (row) => ({
                                        label: row.email,
                                        count: row.count,
                                    }),
                                )}
                            />

                            <div className="space-y-2 border-t pt-4">
                                <p className="font-medium">
                                    {t('Admins and teachers without 2FA')}
                                </p>
                                {stats.privileged_without_two_factor === 0 ? (
                                    <StatusBadge
                                        tone="success"
                                        icon={ShieldCheck}
                                        label={t('None')}
                                    />
                                ) : (
                                    <StatusBadge
                                        tone={
                                            stats.two_factor_enforced
                                                ? 'warning'
                                                : 'danger'
                                        }
                                        icon={ShieldAlert}
                                        label={t(':count accounts', {
                                            count: stats.privileged_without_two_factor,
                                        })}
                                    />
                                )}
                                <p className="text-muted-foreground">
                                    {stats.two_factor_enforced
                                        ? t(
                                              'Enforcement is on: they cannot open their area until 2FA is confirmed.',
                                          )
                                        : t(
                                              'Enforcement is off (local environment only).',
                                          )}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <section className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">
                            {t('Recent activity')}
                        </h2>
                        <Link
                            href={admin.auditLog.index()}
                            className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                        >
                            {t('Open the audit log')}
                        </Link>
                    </div>
                    <AuditActivityList entries={activity} />
                </section>
            </div>
        </>
    );
}

function StatCard({
    icon: Icon,
    label,
    value,
    href,
    children,
}: {
    icon: LucideIcon;
    label: string;
    value: number;
    href?: Parameters<typeof Link>[0]['href'];
    children?: React.ReactNode;
}) {
    const content = (
        <Card className="h-full gap-3 transition-colors hover:bg-muted/30">
            <CardHeader className="flex flex-row items-center justify-between gap-2">
                <CardDescription>{label}</CardDescription>
                <Icon className="size-4 text-primary" aria-hidden="true" />
            </CardHeader>
            <CardContent className="space-y-2">
                <p className="tabular text-3xl font-semibold">{value}</p>
                {children}
            </CardContent>
        </Card>
    );

    return href ? (
        <Link
            href={href}
            className="rounded-xl focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
        >
            {content}
        </Link>
    ) : (
        content
    );
}

function TopList({
    title,
    rows,
}: {
    title: string;
    rows: { label: string; count: number }[];
}) {
    const { t } = useTranslation();

    return (
        <div className="space-y-1.5">
            <p className="font-medium">{title}</p>
            {rows.length === 0 ? (
                <p className="text-muted-foreground">{t('None')}</p>
            ) : (
                <ol className="space-y-1">
                    {rows.map((row) => (
                        <li
                            key={row.label}
                            className="flex justify-between gap-3"
                        >
                            <span className="truncate font-mono">
                                {row.label}
                            </span>
                            <span className="tabular text-muted-foreground">
                                {row.count}
                            </span>
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
