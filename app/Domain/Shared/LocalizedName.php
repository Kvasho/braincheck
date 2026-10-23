<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Enums\Locale;
use InvalidArgumentException;
use JsonSerializable;

/**
 * A name in every supported locale: {"ka": "...", "en": "..."}.
 * Both translations are required and non-empty (the DB enforces it too).
 */
final readonly class LocalizedName implements JsonSerializable
{
    public const MAX_LENGTH = 255;

    public function __construct(
        public string $ka,
        public string $en,
    ) {
        foreach (['ka' => $ka, 'en' => $en] as $locale => $value) {
            if (trim($value) === '' || mb_strlen($value) > self::MAX_LENGTH) {
                throw new InvalidArgumentException("The {$locale} name must be 1–".self::MAX_LENGTH.' characters.');
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! is_string($data['ka'] ?? null) || ! is_string($data['en'] ?? null)) {
            throw new InvalidArgumentException('A localized name needs string "ka" and "en" values.');
        }

        return new self($data['ka'], $data['en']);
    }

    public function in(Locale $locale): string
    {
        return match ($locale) {
            Locale::Georgian => $this->ka,
            Locale::English => $this->en,
        };
    }

    /**
     * The name in the active application locale (falls back to English).
     */
    public function current(): string
    {
        return $this->in(Locale::tryFrom(app()->getLocale()) ?? Locale::fallback());
    }

    /**
     * @return array{ka: string, en: string}
     */
    public function toArray(): array
    {
        return ['ka' => $this->ka, 'en' => $this->en];
    }

    /**
     * @return array{ka: string, en: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
