<?php

declare(strict_types=1);

namespace App\Domain\Questions\Definitions;

use App\Enums\QuestionType;
use App\Exceptions\InvalidQuestionDefinition;

/**
 * `{ options: [{id, text}], correct: id }`: exactly one correct option.
 */
final readonly class SingleChoiceDefinition implements QuestionDefinition
{
    /**
     * @param  list<Item>  $options
     */
    public function __construct(
        public array $options,
        public string $correct,
    ) {
        if (count($options) < 2) {
            throw InvalidQuestionDefinition::because('single choice needs at least 2 options.');
        }

        Guard::uniqueIds(Guard::ids($options), 'options');

        if (! in_array($correct, Guard::ids($options), true)) {
            throw InvalidQuestionDefinition::because('the correct option must be one of the options.');
        }
    }

    public function type(): QuestionType
    {
        return QuestionType::SingleChoice;
    }

    public static function fromArray(array $data): static
    {
        Guard::onlyKeys($data, ['options', 'correct']);

        if (! is_string($data['correct'] ?? null)) {
            throw InvalidQuestionDefinition::because('correct must be an option id.');
        }

        return new self(Guard::items($data['options'] ?? null, 'options', 2), $data['correct']);
    }

    public function toArray(): array
    {
        return [
            'options' => array_map(fn (Item $option): array => $option->toArray(), $this->options),
            'correct' => $this->correct,
        ];
    }
}
