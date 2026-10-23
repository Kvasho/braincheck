<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\Shared\LocalizedName;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * JSON {ka, en} column ⇄ LocalizedName.
 *
 * @implements CastsAttributes<LocalizedName, LocalizedName|array{ka: string, en: string}>
 */
final class LocalizedNameCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?LocalizedName
    {
        if ($value === null) {
            return null;
        }

        $data = is_string($value) ? json_decode($value, true) : null;

        if (! is_array($data)) {
            throw new InvalidArgumentException("Stored {$key} is not a localized name.");
        }

        return LocalizedName::fromArray($data);
    }

    /**
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $name = $value instanceof LocalizedName ? $value : LocalizedName::fromArray($value);

        return [$key => json_encode($name->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)];
    }
}
