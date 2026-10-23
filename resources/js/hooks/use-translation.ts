import { usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import type { Locale as DateFnsLocale } from 'date-fns';
import { enUS, ka } from 'date-fns/locale';
import { useCallback } from 'react';

/**
 * Every UI key, derived from lang/en.json at type level only (nothing is bundled).
 * A typo in t('...') is a TypeScript error.
 */
export type TranslationKey = keyof typeof import('../../../lang/en.json');

/** Mirrors App\Enums\Locale. The server only ever sends these values. */
export type AppLocale = 'ka' | 'en';

export type Replacements = Record<string, string | number>;

const dateFnsLocales: Record<AppLocale, DateFnsLocale> = { ka, en: enUS };

/**
 * Laravel-style placeholders: `:name` as given, `:Name` capitalised, `:NAME` upper-cased.
 */
function replacePlaceholders(text: string, replacements: Replacements): string {
    return Object.entries(replacements).reduce((result, [key, raw]) => {
        const value = String(raw);
        const capitalised = value.charAt(0).toUpperCase() + value.slice(1);

        return result
            .replaceAll(`:${key.toUpperCase()}`, value.toUpperCase())
            .replaceAll(
                `:${key.charAt(0).toUpperCase()}${key.slice(1)}`,
                capitalised,
            )
            .replaceAll(`:${key}`, value);
    }, text);
}

export function useTranslation() {
    const { translations, locale } = usePage().props;

    /** Translate a known UI key. Falls back to the key (the English text). */
    const t = useCallback(
        (key: TranslationKey, replacements: Replacements = {}): string =>
            replacePlaceholders(translations[key] ?? key, replacements),
        [translations],
    );

    /**
     * Translate a key that arrives as data (layout titles, breadcrumbs, nav items).
     * Unknown keys are returned unchanged.
     */
    const translate = useCallback(
        (key: string, replacements: Replacements = {}): string =>
            replacePlaceholders(translations[key] ?? key, replacements),
        [translations],
    );

    /** date-fns format() in the active locale. Default pattern: localized medium date. */
    const formatDate = useCallback(
        (date: Date | number | string, pattern = 'PP'): string =>
            format(typeof date === 'string' ? new Date(date) : date, pattern, {
                locale: dateFnsLocales[locale],
            }),
        [locale],
    );

    return {
        t,
        translate,
        formatDate,
        locale,
        dateLocale: dateFnsLocales[locale],
    } as const;
}
