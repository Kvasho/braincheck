import { Head, router } from '@inertiajs/react';
import { Ban, ShieldCheck, UserCog } from 'lucide-react';
import { useState } from 'react';
import { AuditActivityList } from '@/components/audit-activity-list';
import { ConfirmDialog } from '@/components/data/confirm-dialog';
import { PageHeader } from '@/components/data/page-header';
import { StatusBadge } from '@/components/data/status-badge';
import InputError from '@/components/input-error';
import { RoleBadge } from '@/components/role-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import type { AdminUserDetail, AuditEntry, Option, Role } from '@/types';

type Dialog = 'role' | 'block' | 'unblock' | null;

export default function UserShow({
    user,
    activity,
    roles,
    can,
}: {
    user: AdminUserDetail;
    activity: AuditEntry[];
    roles: Option<Role>[];
    can: { changeRole: boolean; block: boolean; unblock: boolean };
}) {
    const { t, formatDate } = useTranslation();
    const [dialog, setDialog] = useState<Dialog>(null);
    const [role, setRole] = useState<Role | null>(user.role);
    const [reason, setReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const open = (next: Dialog) => {
        setErrors({});
        setRole(user.role);
        setReason('');
        setDialog(next);
    };

    const visitOptions = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
        onSuccess: () => setDialog(null),
        onError: (errors: Record<string, string>) => setErrors(errors),
    };

    const date = (value: string | null) => (value ? formatDate(value) : '—');

    return (
        <>
            <Head title={user.name} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={user.name}
                    description={user.email}
                    actions={
                        <>
                            {can.changeRole && (
                                <Button
                                    variant="outline"
                                    onClick={() => open('role')}
                                >
                                    <UserCog aria-hidden="true" />
                                    {t('Change role')}
                                </Button>
                            )}
                            {can.block && !user.blocked && (
                                <Button
                                    variant="destructive"
                                    onClick={() => open('block')}
                                >
                                    <Ban aria-hidden="true" />
                                    {t('Block')}
                                </Button>
                            )}
                            {can.unblock && user.blocked && (
                                <Button
                                    variant="outline"
                                    onClick={() => open('unblock')}
                                >
                                    <ShieldCheck aria-hidden="true" />
                                    {t('Unblock')}
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>{t('Profile')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 text-sm sm:grid-cols-2">
                                <Detail label={t('Role')}>
                                    <RoleBadge role={user.role} />
                                </Detail>
                                <Detail label={t('Status')}>
                                    {user.blocked ? (
                                        <StatusBadge
                                            tone="danger"
                                            label={t('Blocked')}
                                        />
                                    ) : (
                                        <StatusBadge
                                            tone="success"
                                            label={t('Active')}
                                        />
                                    )}
                                </Detail>
                                {user.blocked && (
                                    <Detail label={t('Block reason')}>
                                        <span className="break-words">
                                            {user.blocked_reason ?? '—'}
                                        </span>
                                        <span className="block text-muted-foreground">
                                            {date(user.blocked_at)}
                                        </span>
                                    </Detail>
                                )}
                                <Detail label={t('Joined')}>
                                    {date(user.created_at)}
                                </Detail>
                                <Detail label={t('Email verified')}>
                                    {date(user.email_verified_at)}
                                </Detail>
                                <Detail label={t('Two-factor authentication')}>
                                    {user.has_two_factor ? (
                                        <StatusBadge
                                            tone="success"
                                            label={t('Enabled')}
                                        />
                                    ) : (
                                        <StatusBadge
                                            tone="neutral"
                                            label={t('Not enabled')}
                                        />
                                    )}
                                </Detail>
                                <Detail label={t('XP')}>
                                    <span className="tabular">
                                        {user.xp_total}
                                    </span>
                                </Detail>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Activity')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-2 gap-4 text-sm">
                                <Count
                                    label={t('Questions')}
                                    value={user.counts.questions}
                                />
                                <Count
                                    label={t('Tests')}
                                    value={user.counts.tests}
                                />
                                <Count
                                    label={t('Groups')}
                                    value={user.counts.groups}
                                />
                                <Count
                                    label={t('Attempts')}
                                    value={user.counts.attempts}
                                />
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">
                        {t('Recent events')}
                    </h2>
                    <AuditActivityList entries={activity} />
                </section>
            </div>

            <ConfirmDialog
                open={dialog === 'role'}
                onOpenChange={(next) => !next && setDialog(null)}
                title={t('Change role')}
                description={t(
                    'The user keeps exactly one role. The change is recorded in the audit log.',
                )}
                confirmLabel={t('Change role')}
                requirePassword
                processing={processing}
                canConfirm={role !== null && role !== user.role}
                onConfirm={() =>
                    router.put(
                        admin.users.role.update.url(user.ulid),
                        { role },
                        visitOptions,
                    )
                }
            >
                <div className="grid gap-2">
                    <Label htmlFor="role">{t('New role')}</Label>
                    <Select
                        value={role ?? undefined}
                        onValueChange={(value) => setRole(value as Role)}
                    >
                        <SelectTrigger id="role">
                            <SelectValue placeholder={t('Choose a role')} />
                        </SelectTrigger>
                        <SelectContent>
                            {roles.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.role} />
                </div>
            </ConfirmDialog>

            <ConfirmDialog
                open={dialog === 'block'}
                onOpenChange={(next) => !next && setDialog(null)}
                title={t('Block :name', { name: user.name })}
                description={t(
                    'All of their sessions end immediately and "remember me" stops working. They see a neutral message and cannot sign in until unblocked.',
                )}
                confirmLabel={t('Block user')}
                destructive
                confirmText={user.email}
                requirePassword
                processing={processing}
                canConfirm={reason.trim().length >= 3}
                onConfirm={() =>
                    router.post(
                        admin.users.block.url(user.ulid),
                        { reason },
                        visitOptions,
                    )
                }
            >
                <div className="grid gap-2">
                    <Label htmlFor="reason">{t('Reason')}</Label>
                    <Textarea
                        id="reason"
                        value={reason}
                        maxLength={255}
                        required
                        onChange={(event) => setReason(event.target.value)}
                    />
                    <p className="text-xs text-muted-foreground">
                        {t('Visible to administrators only.')}
                    </p>
                    <InputError message={errors.reason} />
                </div>
            </ConfirmDialog>

            <ConfirmDialog
                open={dialog === 'unblock'}
                onOpenChange={(next) => !next && setDialog(null)}
                title={t('Unblock :name', { name: user.name })}
                description={t('They will be able to sign in again.')}
                confirmLabel={t('Unblock')}
                requirePassword
                processing={processing}
                onConfirm={() =>
                    router.delete(
                        admin.users.unblock.url(user.ulid),
                        visitOptions,
                    )
                }
            />
        </>
    );
}

function Detail({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-1">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{children}</dd>
        </div>
    );
}

function Count({ label, value }: { label: string; value: number }) {
    return (
        <div>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="tabular text-2xl font-semibold">{value}</dd>
        </div>
    );
}

UserShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Users', href: admin.users.index() },
    ],
};
