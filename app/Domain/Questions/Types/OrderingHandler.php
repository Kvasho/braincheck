<?php

declare(strict_types=1);

namespace App\Domain\Questions\Types;

use App\Domain\Questions\Definitions\Item;
use App\Domain\Questions\Definitions\OrderingDefinition;
use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Questions\GradeResult;
use App\Domain\Questions\Points;
use App\Domain\Shared\Randomizers;
use App\Enums\MultipleChoiceScoring;
use App\Enums\QuestionType;
use Illuminate\Validation\Rule;
use Random\Randomizer;

/**
 * ordering: 2–10 items to put in order.
 *
 * - all_or_nothing: full points only for the exact order.
 * - partial: (items in their correct position / all items) × points, rounded
 *   half up to 2 decimals.
 *
 * The items are always shown shuffled (whatever $shuffle says): showing them in
 * stored order could mirror the answer. Normalization also stores them shuffled.
 *
 * Editor input: `{ items: [{ id?, text }] in the correct order, scoring }`.
 * Answer: `{ order: [ids] }`, a permutation of all item ids.
 */
final class OrderingHandler extends BaseQuestionTypeHandler
{
    public function type(): QuestionType
    {
        return QuestionType::Ordering;
    }

    public function definitionRules(): array
    {
        return [
            ...$this->itemRowRules('items', self::MIN_ITEMS, self::MAX_ITEMS),
            'scoring' => ['required', Rule::enum(MultipleChoiceScoring::class)],
        ];
    }

    public function normalizeDefinition(array $input): OrderingDefinition
    {
        $used = [];
        $ordered = $this->items(is_array($input['items'] ?? null) ? $input['items'] : [], $used);
        $scoring = MultipleChoiceScoring::tryFrom(is_string($input['scoring'] ?? null) ? $input['scoring'] : '') ?? MultipleChoiceScoring::AllOrNothing;

        return new OrderingDefinition(
            $this->shuffled($ordered, Randomizers::secure()),
            $this->ids($ordered),
            $scoring,
        );
    }

    public function toEditorInput(QuestionDefinition $definition, bool $withIds = true): array
    {
        $definition = $this->expect($definition, OrderingDefinition::class);

        $byId = [];

        foreach ($definition->items as $item) {
            $byId[$item->id] = $item;
        }

        // Rows in the correct order: that is how the editor expresses the answer.
        $ordered = array_map(fn (string $id): Item => $byId[$id], $definition->correctOrder);

        return [
            'items' => $this->editorRows($ordered, $withIds),
            'scoring' => $definition->scoring->value,
        ];
    }

    public function answerRules(QuestionDefinition $definition): array
    {
        $definition = $this->expect($definition, OrderingDefinition::class);

        return [
            'order' => ['present', 'array', 'list', 'size:'.count($definition->items)],
            'order.*' => ['required', 'string', 'distinct', Rule::in($this->ids($definition->items))],
        ];
    }

    public function grade(QuestionDefinition $definition, ?array $answer, Points $points): GradeResult
    {
        $definition = $this->expect($definition, OrderingDefinition::class);
        $order = $this->order($answer);
        $total = count($definition->correctOrder);

        $inPlace = 0;

        foreach ($definition->correctOrder as $position => $id) {
            if (($order[$position] ?? null) === $id) {
                $inPlace++;
            }
        }

        if ($inPlace === $total) {
            return GradeResult::full($points);
        }

        if ($definition->scoring === MultipleChoiceScoring::AllOrNothing) {
            return GradeResult::zero();
        }

        return GradeResult::partial($points->timesFraction($inPlace, $total), $points);
    }

    public function toPlayerPayload(QuestionDefinition $definition, Randomizer $randomizer, bool $shuffle): array
    {
        $definition = $this->expect($definition, OrderingDefinition::class);

        return ['items' => $this->itemArrays($this->shuffled($definition->items, $randomizer))];
    }

    public function toReviewPayload(QuestionDefinition $definition, ?array $answer, bool $revealCorrect): array
    {
        $definition = $this->expect($definition, OrderingDefinition::class);
        $order = $this->order($answer);
        $byId = [];

        foreach ($definition->items as $item) {
            $byId[$item->id] = $item;
        }

        $answered = array_values(array_filter(array_map(fn (string $id): ?Item => $byId[$id] ?? null, $order)));

        return [
            'items' => array_map(fn (Item $item): array => $item->toArray(), $definition->items),
            'order' => $order,
            'answered' => array_map(fn (Item $item, int $position): array => [
                ...$item->toArray(),
                ...($revealCorrect ? ['is_correct' => ($definition->correctOrder[$position] ?? null) === $item->id] : []),
            ], $answered, array_keys($answered)),
            ...($revealCorrect ? ['correct_order' => $definition->correctOrder, 'scoring' => $definition->scoring->value] : []),
        ];
    }

    /**
     * @param  array<array-key, mixed>|null  $answer
     * @return list<string>
     */
    private function order(?array $answer): array
    {
        $order = $answer['order'] ?? [];

        return is_array($order) ? array_values(array_filter($order, 'is_string')) : [];
    }
}
