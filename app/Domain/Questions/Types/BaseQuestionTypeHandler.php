<?php

declare(strict_types=1);

namespace App\Domain\Questions\Types;

use App\Domain\Questions\Definitions\Item;
use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Questions\Definitions\ShortId;
use LogicException;
use Random\Randomizer;

/**
 * Shared helpers for the handlers: the definition type guard, item
 * normalization (trimmed text, kept or fresh random ids) and shuffling.
 */
abstract class BaseQuestionTypeHandler implements QuestionTypeHandler
{
    /** Option / item limits shared by the list-based types. */
    public const int MIN_ITEMS = 2;

    public const int MAX_ITEMS = 10;

    public const int ITEM_TEXT_MAX = 500;

    /**
     * @template T of QuestionDefinition
     *
     * @param  class-string<T>  $class
     * @return T
     *
     * @throws LogicException when given a definition of another type
     */
    protected function expect(QuestionDefinition $definition, string $class): QuestionDefinition
    {
        if (! $definition instanceof $class) {
            throw new LogicException(static::class.' can not handle '.$definition::class.'.');
        }

        return $definition;
    }

    /**
     * Editor rows → items. A valid, not yet used short id is kept (editing keeps
     * ids stable); anything else gets a fresh CSPRNG id. Text is trimmed.
     *
     * @param  array<array-key, mixed>  $rows
     * @param  array<string, true>  $usedIds  ids taken so far in this definition (updated)
     * @return list<Item>
     */
    protected function items(array $rows, array &$usedIds): array
    {
        $items = [];

        foreach (array_values($rows) as $row) {
            $row = is_array($row) ? $row : [];
            $id = $row['id'] ?? null;

            if (! ShortId::isValid($id) || isset($usedIds[$id])) {
                do {
                    $id = ShortId::generate();
                } while (isset($usedIds[$id]));
            }

            /** @var string $id */
            $usedIds[$id] = true;
            $items[] = new Item($id, trim(is_string($row['text'] ?? null) ? $row['text'] : ''));
        }

        return $items;
    }

    /**
     * Items as editor rows, optionally without their ids.
     *
     * @param  list<Item>  $items
     * @return list<array<string, string>>
     */
    protected function editorRows(array $items, bool $withIds): array
    {
        return array_map(fn (Item $item): array => $withIds ? $item->toArray() : ['text' => $item->text], $items);
    }

    /**
     * @param  list<Item>  $items
     * @return list<array{id: string, text: string}>
     */
    protected function itemArrays(array $items): array
    {
        return array_map(fn (Item $item): array => $item->toArray(), $items);
    }

    /**
     * @template T
     *
     * @param  list<T>  $values
     * @return list<T>
     */
    protected function shuffled(array $values, Randomizer $randomizer, bool $shuffle = true): array
    {
        return $shuffle ? $randomizer->shuffleArray($values) : $values;
    }

    /**
     * Rules for a list of editor rows `{ id?, text }` (plus extra per-row rules).
     *
     * @param  array<string, mixed>  $extraRowRules  e.g. ['correct' => ['required', 'boolean']]
     * @return array<string, mixed>
     */
    protected function itemRowRules(string $field, int $min, int $max, array $extraRowRules = []): array
    {
        $rules = [
            $field => ['required', 'array', 'list', "min:{$min}", "max:{$max}"],
            "{$field}.*" => ['required', 'array:'.implode(',', ['id', 'text', ...array_keys($extraRowRules)])],
            "{$field}.*.id" => ['nullable', 'string', 'max:'.ShortId::LENGTH],
            "{$field}.*.text" => ['required', 'string', 'max:'.self::ITEM_TEXT_MAX, 'regex:/\S/u'],
        ];

        foreach ($extraRowRules as $key => $rowRules) {
            $rules["{$field}.*.{$key}"] = $rowRules;
        }

        return $rules;
    }

    /**
     * @param  list<Item>  $items
     * @return list<string>
     */
    protected function ids(array $items): array
    {
        return array_map(fn (Item $item): string => $item->id, $items);
    }
}
