import { Form, Head, router } from '@inertiajs/react';
import { Combine, Pencil, Plus, Tags, Trash2 } from 'lucide-react';
import { useState } from 'react';
import TagController from '@/actions/App/Http/Controllers/Admin/TagController';
import { ConfirmDialog } from '@/components/data/confirm-dialog';
import type { DataTableColumn } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import { EmptyState } from '@/components/data/empty-state';
import { FilterBar } from '@/components/data/filter-bar';
import { PageHeader } from '@/components/data/page-header';
import { Pagination } from '@/components/data/pagination';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { useListing } from '@/hooks/use-listing';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import type { ListingState, Option, Paginated, TagRow } from '@/types';

type Editing = { mode: 'create' } | { mode: 'edit'; tag: TagRow };

export default function TagsIndex({
    tags,
    filters,
    mergeOptions,
}: {
    tags: Paginated<TagRow>;
    filters: ListingState;
    mergeOptions: Option[];
}) {
    const { t, formatDate } = useTranslation();
    const { update, loading } = useListing({
        query: filters,
        only: ['tags', 'filters', 'mergeOptions'],
    });
    const [editing, setEditing] = useState<Editing | null>(null);
    const [merging, setMerging] = useState<TagRow | null>(null);
    const [target, setTarget] = useState<string | null>(null);
    const [deleting, setDeleting] = useState<TagRow | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const visit = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
        onSuccess: () => {
            setMerging(null);
            setDeleting(null);
        },
        onError: (next: Record<string, string>) => setErrors(next),
    };

    const targetName =
        mergeOptions.find((option) => option.value === target)?.label ?? '';

    const columns: DataTableColumn<TagRow>[] = [
        {
            key: 'name',
            header: t('Name'),
            sortKey: 'name',
            primary: true,
            cell: (tag) => <span className="font-medium">{tag.name}</span>,
        },
        {
            key: 'slug',
            header: t('Slug'),
            cell: (tag) => (
                <span className="font-mono text-sm text-muted-foreground">
                    {tag.slug}
                </span>
            ),
        },
        {
            key: 'usage',
            header: t('Questions'),
            sortKey: 'questions_count',
            align: 'end',
            cell: (tag) => (
                <span className="tabular">{tag.questions_count}</span>
            ),
        },
        {
            key: 'created',
            header: t('Created'),
            sortKey: 'created_at',
            cell: (tag) => (tag.created_at ? formatDate(tag.created_at) : '—'),
        },
    ];

    return (
        <>
            <Head title={t('Tags')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Tags')}
                    description={t(
                        'Tags teachers attach to questions. Merge duplicates instead of deleting them.',
                    )}
                    actions={
                        <Button onClick={() => setEditing({ mode: 'create' })}>
                            <Plus aria-hidden="true" />
                            {t('New tag')}
                        </Button>
                    }
                />

                <FilterBar
                    search={{
                        value: filters.search,
                        onChange: (search) => update({ search }),
                        placeholder: t('Name or slug'),
                    }}
                />

                <DataTable
                    caption={t('Tags')}
                    columns={columns}
                    rows={tags.data}
                    rowKey={(tag) => tag.ulid}
                    loading={loading}
                    sort={{ key: filters.sort, direction: filters.direction }}
                    onSort={(sort, direction) => update({ sort, direction })}
                    rowActions={(tag) => (
                        <div className="flex justify-end gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('Edit :name', { name: tag.name })}
                                onClick={() =>
                                    setEditing({ mode: 'edit', tag })
                                }
                            >
                                <Pencil aria-hidden="true" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('Merge :name', {
                                    name: tag.name,
                                })}
                                onClick={() => {
                                    setErrors({});
                                    setTarget(null);
                                    setMerging(tag);
                                }}
                            >
                                <Combine aria-hidden="true" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('Delete :name', {
                                    name: tag.name,
                                })}
                                onClick={() => setDeleting(tag)}
                            >
                                <Trash2 aria-hidden="true" />
                            </Button>
                        </div>
                    )}
                    empty={
                        <EmptyState
                            icon={Tags}
                            title={t('No tags found')}
                            description={t(
                                'Create a tag, or try a different search.',
                            )}
                        />
                    }
                />

                <Pagination
                    meta={tags.meta}
                    onPageChange={(page) => update({ page })}
                />
            </div>

            <TagDialog editing={editing} onClose={() => setEditing(null)} />

            <ConfirmDialog
                open={merging !== null}
                onOpenChange={(next) => !next && setMerging(null)}
                title={t('Merge ":name" into another tag', {
                    name: merging?.name ?? '',
                })}
                description={t(
                    'Its :count questions move to the chosen tag (without duplicates) and ":name" is deleted. This cannot be undone.',
                    {
                        count: merging?.questions_count ?? 0,
                        name: merging?.name ?? '',
                    },
                )}
                confirmLabel={t('Merge tags')}
                destructive
                confirmText={merging?.name}
                canConfirm={target !== null}
                processing={processing}
                onConfirm={() =>
                    merging &&
                    router.post(
                        admin.tags.merge.url(merging.ulid),
                        { target },
                        visit,
                    )
                }
            >
                <div className="grid gap-2">
                    <Label htmlFor="merge-target">{t('Merge into')}</Label>
                    <Select
                        value={target ?? undefined}
                        onValueChange={setTarget}
                    >
                        <SelectTrigger id="merge-target">
                            <SelectValue placeholder={t('Choose a tag')} />
                        </SelectTrigger>
                        <SelectContent>
                            {mergeOptions
                                .filter(
                                    (option) => option.value !== merging?.ulid,
                                )
                                .map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                        </SelectContent>
                    </Select>
                    {targetName && (
                        <p className="text-xs text-muted-foreground">
                            {t('":source" → ":target"', {
                                source: merging?.name ?? '',
                                target: targetName,
                            })}
                        </p>
                    )}
                    <InputError message={errors.target} />
                </div>
            </ConfirmDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(next) => !next && setDeleting(null)}
                title={t('Delete :name?', { name: deleting?.name ?? '' })}
                description={
                    (deleting?.questions_count ?? 0) > 0
                        ? t(
                              'It is removed from :count questions. The questions themselves stay.',
                              { count: deleting?.questions_count ?? 0 },
                          )
                        : t('This tag is not used by any question.')
                }
                confirmLabel={t('Delete tag')}
                destructive
                confirmText={
                    (deleting?.questions_count ?? 0) > 0
                        ? deleting?.name
                        : undefined
                }
                processing={processing}
                onConfirm={() =>
                    deleting &&
                    router.delete(admin.tags.destroy.url(deleting.ulid), visit)
                }
            />
        </>
    );
}

function TagDialog({
    editing,
    onClose,
}: {
    editing: Editing | null;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const tag = editing?.mode === 'edit' ? editing.tag : null;
    const form = tag
        ? TagController.update.form(tag.ulid)
        : TagController.store.form();

    return (
        <Dialog
            open={editing !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <Form
                    {...form}
                    key={tag?.ulid ?? 'new'}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {tag ? t('Edit tag') : t('New tag')}
                                </DialogTitle>
                                <DialogDescription>
                                    {t(
                                        'Names and slugs are unique. Leave the slug empty to generate it from the name.',
                                    )}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="tag-name">{t('Name')}</Label>
                                <Input
                                    id="tag-name"
                                    name="name"
                                    required
                                    maxLength={50}
                                    defaultValue={tag?.name}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="tag-slug">{t('Slug')}</Label>
                                <Input
                                    id="tag-slug"
                                    name="slug"
                                    className="font-mono"
                                    maxLength={60}
                                    pattern="[a-z0-9]+(-[a-z0-9]+)*"
                                    defaultValue={tag?.slug}
                                />
                                <InputError message={errors.slug} />
                            </div>

                            <DialogFooter className="gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={onClose}
                                >
                                    {t('Cancel')}
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {t('Save')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

TagsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Tags', href: admin.tags.index() },
    ],
};
