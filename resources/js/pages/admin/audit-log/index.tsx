import { Head, Link } from '@inertiajs/react';
import { PanelRightOpen, ScrollText } from 'lucide-react';
import { useState } from 'react';
import type { DataTableColumn } from '@/components/data/data-table';
import { DataTable } from '@/components/data/data-table';
import { EmptyState } from '@/components/data/empty-state';
import { FilterBar } from '@/components/data/filter-bar';
import { MultiSelectFilter } from '@/components/data/multi-select-filter';
import { PageHeader } from '@/components/data/page-header';
import { Pagination } from '@/components/data/pagination';
import { PropertiesView } from '@/components/data/properties-view';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useListing } from '@/hooks/use-listing';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import type { AuditEntry, ListingState, Option, Paginated } from '@/types';

type Filters = ListingState & {
    action: string[];
    actor: string | null;
    from: string | null;
    to: string | null;
    subject_type: string | null;
};

export default function AuditLogIndex({
    entries,
    filters,
    actions,
    subjectTypes,
}: {
    entries: Paginated<AuditEntry>;
    filters: Filters;
    actions: Option[];
    subjectTypes: Option[];
}) {
    const { t, formatDate } = useTranslation();
    const { update, loading } = useListing({
        query: filters,
        only: ['entries', 'filters'],
    });
    const [selected, setSelected] = useState<AuditEntry | null>(null);

    const columns: DataTableColumn<AuditEntry>[] = [
        {
            key: 'action',
            header: t('Event'),
            primary: true,
            cell: (entry) => (
                <span className="font-medium">{entry.action_label}</span>
            ),
        },
        {
            key: 'actor',
            header: t('Actor'),
            cell: (entry) =>
                entry.actor ? (
                    <Link
                        href={admin.users.show(entry.actor.ulid)}
                        className="underline-offset-4 hover:underline"
                    >
                        {entry.actor.name}
                    </Link>
                ) : (
                    <span className="text-muted-foreground">{t('System')}</span>
                ),
        },
        {
            key: 'subject',
            header: t('Subject'),
            cell: (entry) =>
                entry.subject ? (
                    <span className="break-words">
                        <span className="text-muted-foreground">
                            {entry.subject.type_label}
                        </span>
                        {entry.subject.label && <> · {entry.subject.label}</>}
                    </span>
                ) : (
                    '—'
                ),
        },
        {
            key: 'ip',
            header: t('IP address'),
            cell: (entry) => (
                <span className="font-mono text-sm">
                    {entry.ip_address ?? '—'}
                </span>
            ),
        },
        {
            key: 'time',
            header: t('Time'),
            sortKey: 'created_at',
            cell: (entry) => (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <time
                            dateTime={entry.created_at}
                            className="tabular whitespace-nowrap"
                            tabIndex={0}
                        >
                            {formatDate(entry.created_at, 'PP p')}
                        </time>
                    </TooltipTrigger>
                    <TooltipContent>
                        {formatDate(entry.created_at, 'PPpp')} (
                        {entry.created_at})
                    </TooltipContent>
                </Tooltip>
            ),
        },
    ];

    return (
        <>
            <Head title={t('Audit log')} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={t('Audit log')}
                    description={t(
                        'Security-relevant events, newest first. Entries can never be changed or deleted.',
                    )}
                />

                <FilterBar
                    search={{
                        value: filters.actor,
                        onChange: (actor) => update({ actor }),
                        placeholder: t('Actor name or email'),
                    }}
                    filters={[
                        {
                            key: 'subject_type',
                            label: t('Subject'),
                            value: filters.subject_type,
                            options: subjectTypes,
                            onChange: (subject_type) =>
                                update({ subject_type }),
                        },
                    ]}
                    dateRange={{
                        from: filters.from,
                        to: filters.to,
                        onChange: ({ from, to }) => update({ from, to }),
                    }}
                    onReset={() =>
                        update({
                            action: [],
                            actor: null,
                            from: null,
                            to: null,
                            subject_type: null,
                        })
                    }
                >
                    <MultiSelectFilter
                        id="filter-action"
                        label={t('Event')}
                        values={filters.action}
                        options={actions}
                        onChange={(action) => update({ action })}
                    />
                </FilterBar>

                <DataTable
                    caption={t('Audit log')}
                    columns={columns}
                    rows={entries.data}
                    rowKey={(entry, index) => `${entry.created_at}-${index}`}
                    loading={loading}
                    sort={{ key: filters.sort, direction: filters.direction }}
                    onSort={(sort, direction) => update({ sort, direction })}
                    rowActions={(entry) => (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setSelected(entry)}
                        >
                            <PanelRightOpen aria-hidden="true" />
                            {t('Details')}
                        </Button>
                    )}
                    empty={
                        <EmptyState
                            icon={ScrollText}
                            title={t('No events found')}
                            description={t(
                                'Try a different filter or date range.',
                            )}
                        />
                    }
                />

                <Pagination
                    meta={entries.meta}
                    onPageChange={(page) => update({ page })}
                />
            </div>

            <Sheet
                open={selected !== null}
                onOpenChange={(open) => !open && setSelected(null)}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                    {selected && (
                        <>
                            <SheetHeader>
                                <SheetTitle>{selected.action_label}</SheetTitle>
                                <SheetDescription>
                                    <span className="font-mono">
                                        {selected.action}
                                    </span>{' '}
                                    · {formatDate(selected.created_at, 'PPpp')}
                                </SheetDescription>
                            </SheetHeader>

                            <div className="space-y-6 px-4 pb-6">
                                <section className="space-y-2">
                                    <h3 className="text-sm font-medium">
                                        {t('Context')}
                                    </h3>
                                    <PropertiesView
                                        value={{
                                            [t('Actor')]: selected.actor
                                                ? `${selected.actor.name} <${selected.actor.email}>`
                                                : t('System'),
                                            [t('Subject')]: selected.subject
                                                ? `${selected.subject.type_label}${selected.subject.label ? ` · ${selected.subject.label}` : ''}`
                                                : '—',
                                            [t('IP address')]:
                                                selected.ip_address ?? '—',
                                            [t('User agent')]:
                                                selected.user_agent ?? '—',
                                        }}
                                    />
                                </section>

                                <section className="space-y-2">
                                    <h3 className="text-sm font-medium">
                                        {t('Properties')}
                                    </h3>
                                    <PropertiesView
                                        value={selected.properties}
                                    />
                                </section>
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}

AuditLogIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Audit log', href: admin.auditLog.index() },
    ],
};
