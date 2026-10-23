import { Search, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';

export const SEARCH_DEBOUNCE_MS = 300;

/** Radix Select can't hold an empty value; this stands for "no filter". */
const ALL = '__all__';

export type SelectFilter = {
    key: string;
    /** Already translated label. */
    label: string;
    value: string | null;
    options: { value: string; label: string }[];
    onChange: (value: string | null) => void;
};

/**
 * Search (debounced), select filters and an optional date range. Controlled by
 * the listing state from the server: the URL is the single source of truth.
 */
export function FilterBar({
    search,
    filters = [],
    dateRange,
    onReset,
    children,
}: {
    search?: {
        value: string | null;
        onChange: (value: string | null) => void;
        placeholder: string;
    };
    filters?: SelectFilter[];
    dateRange?: {
        from: string | null;
        to: string | null;
        onChange: (range: { from: string | null; to: string | null }) => void;
    };
    onReset?: () => void;
    /** Extra controls (e.g. a multi-select). */
    children?: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <div
            role="search"
            className="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-end"
        >
            {search && <DebouncedSearch {...search} />}

            {filters.map((filter) => (
                <div key={filter.key} className="grid gap-1.5 md:w-44">
                    <Label htmlFor={`filter-${filter.key}`}>
                        {filter.label}
                    </Label>
                    <Select
                        value={filter.value ?? ALL}
                        onValueChange={(value) =>
                            filter.onChange(value === ALL ? null : value)
                        }
                    >
                        <SelectTrigger id={`filter-${filter.key}`}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>{t('All')}</SelectItem>
                            {filter.options.map((option) => (
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
            ))}

            {dateRange && (
                <>
                    <div className="grid gap-1.5 md:w-40">
                        <Label htmlFor="filter-from">{t('From')}</Label>
                        <Input
                            id="filter-from"
                            type="date"
                            value={dateRange.from ?? ''}
                            max={dateRange.to ?? undefined}
                            onChange={(event) =>
                                dateRange.onChange({
                                    from: event.target.value || null,
                                    to: dateRange.to,
                                })
                            }
                        />
                    </div>
                    <div className="grid gap-1.5 md:w-40">
                        <Label htmlFor="filter-to">{t('To')}</Label>
                        <Input
                            id="filter-to"
                            type="date"
                            value={dateRange.to ?? ''}
                            min={dateRange.from ?? undefined}
                            onChange={(event) =>
                                dateRange.onChange({
                                    from: dateRange.from,
                                    to: event.target.value || null,
                                })
                            }
                        />
                    </div>
                </>
            )}

            {children}

            {onReset && (
                <Button type="button" variant="ghost" onClick={onReset}>
                    <X aria-hidden="true" />
                    {t('Reset filters')}
                </Button>
            )}
        </div>
    );
}

function DebouncedSearch({
    value,
    onChange,
    placeholder,
}: {
    value: string | null;
    onChange: (value: string | null) => void;
    placeholder: string;
}) {
    const { t } = useTranslation();
    const [text, setText] = useState(value ?? '');
    const [syncedValue, setSyncedValue] = useState(value);
    const latest = useRef(onChange);

    useEffect(() => {
        latest.current = onChange;
    }, [onChange]);

    // Back/forward or a reset changes the server state: follow it.
    if (value !== syncedValue) {
        setSyncedValue(value);
        setText(value ?? '');
    }

    useEffect(() => {
        const normalized = text.trim();

        if (normalized === (value ?? '')) {
            return;
        }

        const timer = window.setTimeout(
            () => latest.current(normalized === '' ? null : normalized),
            SEARCH_DEBOUNCE_MS,
        );

        return () => window.clearTimeout(timer);
    }, [text, value]);

    return (
        <div className="grid gap-1.5 md:min-w-64 md:flex-1">
            <Label htmlFor="filter-search">{t('Search')}</Label>
            <div className="relative">
                <Search
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    id="filter-search"
                    type="search"
                    value={text}
                    maxLength={100}
                    placeholder={placeholder}
                    onChange={(event) => setText(event.target.value)}
                    className="pl-9"
                />
            </div>
        </div>
    );
}
