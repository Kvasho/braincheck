<?php

declare(strict_types=1);

namespace App\Support;

use BackedEnum;
use DateTimeInterface;
use UnitEnum;

/**
 * Safety net for audit log properties: callers must never pass secrets, but if one
 * slips through it is stripped here before anything reaches the database.
 *
 * - Keys matching a sensitive pattern are removed at every depth (case-insensitive).
 * - Values are normalized to JSON scalars; arbitrary objects (models, requests, ...)
 *   are never serialized, because their own serializers may expose hidden data.
 * - The encoded result is capped at MAX_BYTES. Oversized data loses its trailing
 *   top-level keys and carries a TRUNCATED_MARKER, so the JSON always stays valid.
 */
final class AuditPropertySanitizer
{
    public const int MAX_BYTES = 8192;

    public const int MAX_DEPTH = 5;

    public const int MAX_STRING_LENGTH = 1000;

    public const string TRUNCATED_MARKER = '_truncated';

    /**
     * Glob patterns (Str::is syntax) for keys that are never stored. `*_code` goes
     * beyond the spec's `code` because invite and verification codes are secrets too.
     *
     * @var list<string>
     */
    private const array SENSITIVE_KEYS = [
        'password*',
        '*token*',
        '*secret*',
        'recovery*',
        'remember*',
        'two_factor*',
        'code',
        '*_code',
    ];

    /**
     * @param  array<array-key, mixed>  $properties
     * @return array<array-key, mixed>
     */
    public function sanitize(array $properties): array
    {
        $clean = $this->clean($properties, 1);

        if ($this->encodedSize($clean) <= self::MAX_BYTES) {
            return $clean;
        }

        return $this->truncate($clean);
    }

    public function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $pattern) {
            if (fnmatch($pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function clean(array $values, int $depth): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }

            $clean[is_string($key) ? $this->cleanString($key) : $key] = $this->cleanValue($value, $depth);
        }

        return $clean;
    }

    private function cleanValue(mixed $value, int $depth): mixed
    {
        return match (true) {
            $value === null, is_bool($value), is_int($value) => $value,
            is_float($value) => is_finite($value) ? $value : null,
            is_string($value) => $this->cleanString($value),
            is_array($value) => $depth >= self::MAX_DEPTH ? self::TRUNCATED_MARKER : $this->clean($value, $depth + 1),
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            default => '['.get_debug_type($value).']',
        };
    }

    private function cleanString(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');

        return mb_strlen($value) > self::MAX_STRING_LENGTH
            ? mb_substr($value, 0, self::MAX_STRING_LENGTH).'…'
            : $value;
    }

    /**
     * Keep leading top-level entries while the result (marker included) fits.
     *
     * @param  array<array-key, mixed>  $properties
     * @return array<array-key, mixed>
     */
    private function truncate(array $properties): array
    {
        $kept = [self::TRUNCATED_MARKER => true];

        foreach ($properties as $key => $value) {
            $candidate = $kept;
            $candidate[$key] = $value;

            if ($this->encodedSize($candidate) > self::MAX_BYTES) {
                break;
            }

            $kept = $candidate;
        }

        return $kept;
    }

    /**
     * Same encoding as the model's `array` cast, so the measured size is the stored size.
     *
     * @param  array<array-key, mixed>  $properties
     */
    private function encodedSize(array $properties): int
    {
        return strlen((string) json_encode($properties));
    }
}
