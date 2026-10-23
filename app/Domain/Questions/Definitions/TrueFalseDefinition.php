<?php

declare(strict_types=1);

namespace App\Domain\Questions\Definitions;

use App\Enums\QuestionType;
use App\Exceptions\InvalidQuestionDefinition;

/**
 * `{ correct: bool }`.
 */
final readonly class TrueFalseDefinition implements QuestionDefinition
{
    public function __construct(public bool $correct) {}

    public function type(): QuestionType
    {
        return QuestionType::TrueFalse;
    }

    public static function fromArray(array $data): static
    {
        Guard::onlyKeys($data, ['correct']);

        if (! is_bool($data['correct'] ?? null)) {
            throw InvalidQuestionDefinition::because('correct must be a boolean.');
        }

        return new self($data['correct']);
    }

    public function toArray(): array
    {
        return ['correct' => $this->correct];
    }
}
