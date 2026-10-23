<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\Shared\RichTextDocument;
use App\Exceptions\InvalidRichText;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * JSON column ↔ RichTextDocument. Every write is validated against the
 * allowlist; a stored document that no longer validates throws on read.
 *
 * @implements CastsAttributes<RichTextDocument|null, RichTextDocument|array<array-key, mixed>|null>
 */
final class RichTextCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?RichTextDocument
    {
        if ($value === null) {
            return null;
        }

        $decoded = is_string($value) ? json_decode($value, true) : null;

        if (! is_array($decoded)) {
            throw InvalidRichText::because("{$key} is not valid JSON.");
        }

        return RichTextDocument::fromArray($decoded);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $document = $value instanceof RichTextDocument ? $value : RichTextDocument::fromArray($value);

        return (string) json_encode($document->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
