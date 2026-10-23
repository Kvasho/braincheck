/** Mirrors App\Support\Listing::toProps(). */
export type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type Paginated<T> = {
    data: T[];
    meta: PaginationMeta;
};

export type SortDirection = 'asc' | 'desc';

/** Mirrors App\Support\Listing::state(): what the server actually applied. */
export type ListingState = {
    search: string | null;
    sort: string;
    direction: SortDirection;
    per_page: number;
};

/** A query-string value as the listing pages use it. */
export type QueryValue = string | number | string[] | null | undefined;
