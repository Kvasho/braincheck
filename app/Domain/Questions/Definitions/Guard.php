<?php

declare(strict_types=1);

namespace App\Domain\Questions\Definitions;

use App\Exceptions\InvalidQuestionDefinition;

/**
 * Small validation helpers shared by the definition value objects.
 *
 * @internal
 */
final class Guard
{
    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $allowed
     */
    public static function onlyKeys(array $data, array $allowed): void
    {
        $unknown = array_diff(array_map(strval(...), array_keys($data)), $allowed);

        if ($unknown !== []) {
            throw InvalidQuestionDefinition::because('unknown keys: '.implode(', ', $unknown).'.');
        }
    }

    /**
     * @return list<Item>
     */
    public static function items(mixed $value, string $field, int $min): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw InvalidQuestionDefinition::because("{$field} must be a list.");
        }

        $items = array_map(Item::fromArray(...), $value);

        if (count($items) < $min) {
            throw InvalidQuestionDefinition::because("{$field} needs at least {$min} entries.");
        }

        self::uniqueIds(self::ids($items), $field);

        return $items;
    }

    /**
     * @param  list<Item>  $items
     * @return list<string>
     */
    public static function ids(array $items): array
    {
        return array_map(fn (Item $item): string => $item->id, $items);
    }

    /**
     * @param  list<string>  $ids
     */
    public static function uniqueIds(array $ids, string $field): void
    {
        if (count($ids) !== count(array_unique($ids))) {
            throw InvalidQuestionDefinition::because("{$field} ids must be unique.");
        }
    }

    /**
     * @return list<string>
     */
    public static function idList(mixed $value, string $field): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw InvalidQuestionDefinition::because("{$field} must be a list of ids.");
        }

        foreach ($value as $id) {
            if (! ShortId::isValid($id)) {
                throw InvalidQuestionDefinition::because("{$field} must contain short ids.");
            }
        }

        /** @var list<string> $value */
        return $value;
    }

    public static function number(mixed $value, string $field): float|int
    {
        if (! is_int($value) && ! (is_float($value) && is_finite($value))) {
            throw InvalidQuestionDefinition::because("{$field} must be a finite number.");
        }

        return $value;
    }

    public static function nullableString(mixed $value, string $field, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || mb_strlen($value) > $maxLength) {
            throw InvalidQuestionDefinition::because("{$field} must be null or a string of at most {$maxLength} characters.");
        }

        return $value;
    }
}
