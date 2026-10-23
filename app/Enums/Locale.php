<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Supported locales: the single source of truth.
 *
 * Only these values may ever reach App::setLocale(), translation file paths
 * or the database. Never pass raw request input anywhere locale-related.
 */
enum Locale: string
{
    case Georgian = 'ka';
    case English = 'en';

    /** Session key holding the visitor's chosen locale. */
    public const SESSION_KEY = 'locale';

    public static function default(): self
    {
        return self::Georgian;
    }

    public static function fallback(): self
    {
        return self::English;
    }

    /**
     * Native name, shown in the language switcher.
     */
    public function label(): string
    {
        return match ($this) {
            self::Georgian => 'ქართული',
            self::English => 'English',
        };
    }

    /**
     * Match a language tag such as "ka-GE", "en_US" or "EN" to a supported locale.
     */
    public static function fromLanguageTag(?string $tag): ?self
    {
        if ($tag === null || $tag === '') {
            return null;
        }

        $primary = strtolower(strstr(str_replace('_', '-', $tag), '-', true) ?: $tag);

        return self::tryFrom($primary);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $locale): array => ['value' => $locale->value, 'label' => $locale->label()],
            self::cases(),
        );
    }
}
