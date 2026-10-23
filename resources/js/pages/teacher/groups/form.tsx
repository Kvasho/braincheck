import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/data/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import teacher from '@/routes/teacher';
import type { GroupDetail, GroupFormOptions } from '@/types';

/**
 * Create or edit a group. The invite code itself is managed on the group
 * page (reveal, regenerate); here only its expiry.
 */
export default function GroupForm({
    group,
    options,
}: {
    group: GroupDetail | null;
    options: GroupFormOptions;
}) {
    const { t, formatDate } = useTranslation();
    const editing = group !== null;
    const [expiry, setExpiry] = useState(editing ? 'keep' : 'never');
    const [joining, setJoining] = useState(group?.joining_enabled ?? true);
    const title = editing ? t('Edit group') : t('New group');
    const definition = editing
        ? teacher.groups.update.form(group.ulid)
        : teacher.groups.store.form();

    return (
        <>
            <Head title={title} />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={title}
                    actions={
                        <Button asChild variant="ghost">
                            <Link
                                href={
                                    editing
                                        ? teacher.groups.show(group.ulid)
                                        : teacher.groups.index()
                                }
                            >
                                <ArrowLeft aria-hidden="true" />
                                {editing ? group.name : t('Groups')}
                            </Link>
                        </Button>
                    }
                />

                <Card>
                    <CardContent>
                        <Form {...definition} className="space-y-6">
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">
                                            {t('Name')}
                                        </Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            minLength={3}
                                            maxLength={options.nameMax}
                                            defaultValue={group?.name}
                                            autoFocus={!editing}
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="description">
                                            {t('Description')}
                                        </Label>
                                        <Textarea
                                            id="description"
                                            name="description"
                                            rows={3}
                                            maxLength={options.descriptionMax}
                                            defaultValue={
                                                group?.description ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.description}
                                        />
                                    </div>

                                    <div className="flex items-start gap-3">
                                        <input
                                            type="hidden"
                                            name="joining_enabled"
                                            value={joining ? '1' : '0'}
                                        />
                                        <input
                                            id="joining_enabled"
                                            type="checkbox"
                                            className="mt-0.5 size-4 accent-primary"
                                            checked={joining}
                                            onChange={(event) =>
                                                setJoining(event.target.checked)
                                            }
                                        />
                                        <div className="space-y-0.5">
                                            <Label htmlFor="joining_enabled">
                                                {t(
                                                    'Students can join with the code',
                                                )}
                                            </Label>
                                            <p className="text-xs text-muted-foreground">
                                                {t(
                                                    'You can turn this off at any time, e.g. once everyone has joined.',
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    <InputError
                                        message={errors.joining_enabled}
                                    />

                                    <div className="grid gap-2">
                                        <Label htmlFor="invite_expiry">
                                            {t('Invite code expires')}
                                        </Label>
                                        <Select
                                            value={expiry}
                                            onValueChange={setExpiry}
                                        >
                                            <SelectTrigger id="invite_expiry">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.expiries.map(
                                                    (option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <input
                                            type="hidden"
                                            name="invite_expiry"
                                            value={expiry}
                                        />
                                        {editing && expiry === 'keep' && (
                                            <p className="text-xs text-muted-foreground">
                                                {group.invite_expires_at
                                                    ? t(
                                                          'Currently expires :date.',
                                                          {
                                                              date: formatDate(
                                                                  group.invite_expires_at,
                                                                  'PPp',
                                                              ),
                                                          },
                                                      )
                                                    : t(
                                                          'Currently it never expires.',
                                                      )}
                                            </p>
                                        )}
                                        <InputError
                                            message={errors.invite_expiry}
                                        />
                                    </div>

                                    {expiry === 'custom' && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="invite_expires_at">
                                                {t('Expiry date and time')}
                                            </Label>
                                            <Input
                                                id="invite_expires_at"
                                                name="invite_expires_at"
                                                type="datetime-local"
                                                className="tabular"
                                                required
                                                defaultValue={
                                                    group?.invite_expires_at_local ??
                                                    ''
                                                }
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                {t('Time in :timezone.', {
                                                    timezone: options.timezone,
                                                })}
                                            </p>
                                            <InputError
                                                message={
                                                    errors.invite_expires_at
                                                }
                                            />
                                        </div>
                                    )}

                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        {editing
                                            ? t('Save')
                                            : t('Create group')}
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

GroupForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Groups', href: teacher.groups.index() },
    ],
};
