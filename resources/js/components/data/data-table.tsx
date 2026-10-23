import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { SortDirection } from '@/types';

export type DataTableColumn<T> = {
    key: string;
    /** Already translated header text. */
    header: string;
    cell: (row: T) => ReactNode;
    /** Public sort key (must be whitelisted by the server's Listing). */
    sortKey?: string;
    className?: string;
    align?: 'start' | 'end';
    /** Shown as the card title on small screens. */
    primary?: boolean;
};

export type DataTableSort = {
    key: string;
    direction: SortDirection;
};

/**
 * Server-driven table: rows come from Inertia props, sorting asks the server
 * (onSort), nothing is sorted or filtered client-side. On small screens every
 * row becomes a card. Sortable headers are buttons with aria-sort.
 */
export function DataTable<T>({
    columns,
    rows,
    rowKey,
    caption,
    sort,
    onSort,
    loading = false,
    empty,
    rowActions,
}: {
    columns: DataTableColumn<T>[];
    rows: T[];
    rowKey: (row: T, index: number) => string;
    /** Screen-reader caption describing the table. */
    caption: string;
    sort?: DataTableSort;
    onSort?: (key: string, direction: SortDirection) => void;
    loading?: boolean;
    empty?: ReactNode;
    rowActions?: (row: T) => ReactNode;
}) {
    const { t } = useTranslation();

    const toggleSort = (key: string) => {
        if (!onSort) {
            return;
        }

        onSort(
            key,
            sort?.key === key && sort.direction === 'asc' ? 'desc' : 'asc',
        );
    };

    if (rows.length === 0 && !loading) {
        return <>{empty}</>;
    }

    const primary = columns.find((column) => column.primary) ?? columns[0];
    const secondary = columns.filter((column) => column !== primary);

    return (
        <div
            aria-busy={loading}
            className={cn(
                'transition-opacity duration-200 motion-reduce:transition-none',
                loading && 'pointer-events-none opacity-60',
            )}
        >
            <div className="hidden overflow-x-auto rounded-xl border md:block">
                <table className="w-full text-sm">
                    <caption className="sr-only">{caption}</caption>
                    <thead className="border-b bg-muted/40 text-left">
                        <tr>
                            {columns.map((column) => {
                                const active = sort?.key === column.sortKey;
                                const ariaSort =
                                    active && sort
                                        ? sort.direction === 'asc'
                                            ? 'ascending'
                                            : 'descending'
                                        : undefined;

                                return (
                                    <th
                                        key={column.key}
                                        scope="col"
                                        aria-sort={
                                            column.sortKey
                                                ? ariaSort
                                                : undefined
                                        }
                                        className={cn(
                                            'px-4 py-3 font-medium text-muted-foreground',
                                            column.align === 'end' &&
                                                'text-right',
                                            column.className,
                                        )}
                                    >
                                        {column.sortKey && onSort ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    toggleSort(
                                                        column.sortKey as string,
                                                    )
                                                }
                                                className="-mx-1 inline-flex items-center gap-1 rounded px-1 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            >
                                                {column.header}
                                                <SortIcon
                                                    direction={
                                                        active
                                                            ? sort?.direction
                                                            : undefined
                                                    }
                                                />
                                                <span className="sr-only">
                                                    {t('Sort')}
                                                </span>
                                            </button>
                                        ) : (
                                            column.header
                                        )}
                                    </th>
                                );
                            })}
                            {rowActions && (
                                <th scope="col" className="px-4 py-3">
                                    <span className="sr-only">
                                        {t('Actions')}
                                    </span>
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {rows.map((row, index) => (
                            <tr
                                key={rowKey(row, index)}
                                className="hover:bg-muted/30"
                            >
                                {columns.map((column) => (
                                    <td
                                        key={column.key}
                                        className={cn(
                                            'px-4 py-3 align-middle',
                                            column.align === 'end' &&
                                                'text-right',
                                            column.className,
                                        )}
                                    >
                                        {column.cell(row)}
                                    </td>
                                ))}
                                {rowActions && (
                                    <td className="px-4 py-3 text-right">
                                        {rowActions(row)}
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <ul className="space-y-3 md:hidden" aria-label={caption}>
                {rows.map((row, index) => (
                    <li
                        key={rowKey(row, index)}
                        className="rounded-xl border bg-card p-4 text-card-foreground"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0 font-medium">
                                {primary?.cell(row)}
                            </div>
                            {rowActions && (
                                <div className="shrink-0">
                                    {rowActions(row)}
                                </div>
                            )}
                        </div>
                        {secondary.length > 0 && (
                            <dl className="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                                {secondary.map((column) => (
                                    <div key={column.key} className="contents">
                                        <dt className="text-muted-foreground">
                                            {column.header}
                                        </dt>
                                        <dd className="min-w-0">
                                            {column.cell(row)}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}

function SortIcon({ direction }: { direction?: SortDirection }) {
    const Icon =
        direction === 'asc'
            ? ArrowUp
            : direction === 'desc'
              ? ArrowDown
              : ArrowUpDown;

    return (
        <Icon
            className={cn('size-3.5', !direction && 'opacity-50')}
            aria-hidden="true"
        />
    );
}
