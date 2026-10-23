<?php

declare(strict_types=1);

namespace App\Domain\Questions\Definitions;

use App\Enums\MultipleChoiceScoring;
use App\Enums\QuestionType;
use App\Exceptions\InvalidQuestionDefinition;

/**
 * `{ options: [{id, text}], correct: [ids], scoring: "all_or_nothing" | "partial" }`.
 */
final readonly class MultipleChoiceDefinition implements QuestionDefinition
{
    /**
     * @param  list<Item>  $options
     * @param  list<string>  $correct
     */
    public function __construct(
        public array $options,
        public array $correct,
        public MultipleChoiceScoring $scoring,
    ) {
        if (count($options) < 2) {
            throw InvalidQuestionDefinition::because('multiple choice needs at least 2 options.');
        }

        $ids = Guard::ids($options);
        Guard::uniqueIds($ids, 'options');
        Guard::uniqueIds($correct, 'correct');

        if ($correct === [] || array_diff($correct, $ids) !== []) {
            throw InvalidQuestionDefinition::because('correct must be a non-empty subset of the option ids.');
        }
    }

    public function type(): QuestionType
    {
        return QuestionType::MultipleChoice;
    }

    public static function fromArray(array $data): static
    {
        Guard::onlyKeys($data, ['options', 'correct', 'scoring']);

        $scoring = is_string($data['scoring'] ?? null) ? MultipleChoiceScoring::tryFrom($data['scoring']) : null;

        if ($scoring === null) {
            throw InvalidQuestionDefinition::because('scoring must be "all_or_nothing" or "partial".');
        }

        return new self(
            Guard::items($data['options'] ?? null, 'options', 2),
            Guard::idList($data['correct'] ?? null, 'correct'),
            $scoring,
        );
    }

    public function toArray(): array
    {
        return [
            'options' => array_map(fn (Item $option): array => $option->toArray(), $this->options),
            'correct' => $this->correct,
            'scoring' => $this->scoring->value,
        ];
    }
}
