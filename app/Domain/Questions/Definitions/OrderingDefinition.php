<?php

declare(strict_types=1);

namespace App\Domain\Questions\Definitions;

use App\Enums\MultipleChoiceScoring;
use App\Enums\QuestionType;
use App\Exceptions\InvalidQuestionDefinition;

/**
 * `{ items: [{id, text}], correct_order: [ids], scoring }`: correct_order is a
 * permutation of all item ids. `scoring` reuses MultipleChoiceScoring
 * (all_or_nothing | partial); definitions stored before Stage 4 have no scoring
 * and are read as all_or_nothing.
 */
final readonly class OrderingDefinition implements QuestionDefinition
{
    /**
     * @param  list<Item>  $items
     * @param  list<string>  $correctOrder
     */
    public function __construct(
        public array $items,
        public array $correctOrder,
        public MultipleChoiceScoring $scoring = MultipleChoiceScoring::AllOrNothing,
    ) {
        if (count($items) < 2) {
            throw InvalidQuestionDefinition::because('ordering needs at least 2 items.');
        }

        $ids = Guard::ids($items);
        Guard::uniqueIds($ids, 'items');
        Guard::uniqueIds($correctOrder, 'correct_order');

        $sortedIds = $ids;
        $sortedOrder = $correctOrder;
        sort($sortedIds);
        sort($sortedOrder);

        if ($sortedIds !== $sortedOrder) {
            throw InvalidQuestionDefinition::because('correct_order must contain every item id exactly once.');
        }
    }

    public function type(): QuestionType
    {
        return QuestionType::Ordering;
    }

    public static function fromArray(array $data): static
    {
        Guard::onlyKeys($data, ['items', 'correct_order', 'scoring']);

        $scoring = array_key_exists('scoring', $data)
            ? (is_string($data['scoring']) ? MultipleChoiceScoring::tryFrom($data['scoring']) : null)
            : MultipleChoiceScoring::AllOrNothing;

        if ($scoring === null) {
            throw InvalidQuestionDefinition::because('scoring must be "all_or_nothing" or "partial".');
        }

        return new self(
            Guard::items($data['items'] ?? null, 'items', 2),
            Guard::idList($data['correct_order'] ?? null, 'correct_order'),
            $scoring,
        );
    }

    public function toArray(): array
    {
        return [
            'items' => array_map(fn (Item $item): array => $item->toArray(), $this->items),
            'correct_order' => $this->correctOrder,
            'scoring' => $this->scoring->value,
        ];
    }
}
