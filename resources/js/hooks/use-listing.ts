import { router, usePage } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import type { QueryValue } from '@/types';

type Query = Record<string, QueryValue>;

function isEmpty(value: QueryValue): boolean {
    return (
        value === null ||
        value === undefined ||
        value === '' ||
        (Array.isArray(value) && value.length === 0)
    );
}

/**
 * Listing state lives in the URL query string, so back/forward and shared links
 * work. `query` is the state the server echoed back (validated, whitelisted);
 * update() merges changes into it and reloads only the listed props.
 *
 * Any change except an explicit page change returns to page 1.
 */
export function useListing({ query, only }: { query: Query; only: string[] }) {
    const { url } = usePage();
    const [loading, setLoading] = useState(false);

    const update = useCallback(
        (changes: Query) => {
            const next: Query = { ...query, ...changes };

            if (!('page' in changes)) {
                delete next.page;
            }

            const clean = Object.fromEntries(
                Object.entries(next).filter(([, value]) => !isEmpty(value)),
            );

            router.get(url.split('?')[0] ?? url, clean, {
                only,
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            });
        },
        [query, only, url],
    );

    return { update, loading } as const;
}
