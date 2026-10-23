import { useCallback } from 'react';

export type GetInitialsFn = (fullName: string) => string;

function getInitial(name: string): string {
    return Array.from(name)[0] ?? '';
}

export function useInitials(): GetInitialsFn {
    return useCallback((fullName: string): string => {
        const names = fullName.trim().split(/\s+/u).filter(Boolean);

        const first = names[0];
        const last = names.at(-1);

        if (first === undefined || last === undefined) {
            return '';
        }

        if (names.length === 1) {
            return getInitial(first).toUpperCase();
        }

        const firstInitial = getInitial(first);
        const lastInitial = getInitial(last);

        return `${firstInitial}${lastInitial}`.toUpperCase();
    }, []);
}
