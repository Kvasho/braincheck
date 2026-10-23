<?php

declare(strict_types=1);

namespace App\Domain\Questions\Definitions;

use App\Enums\QuestionType;
use App\Exceptions\InvalidQuestionDefinition;

/**
 * `{ max_length: int, reference_answer: string | null }`. Graded manually;
 * the reference answer is for the teacher only.
 */
final readonly class TextDefinition implements QuestionDefinition
{
    public const MAX_ANSWER_LENGTH = 10000;

    public function __construct(
        public int $maxLength,
        public ?string $referenceAnswer,
    ) {
        if ($maxLength < 1 || $maxLength > self::MAX_ANSWER_LENGTH) {
            throw InvalidQuestionDefinition::because('max_length must be between 1 and '.self::MAX_ANSWER_LENGTH.'.');
        }
    }

    public function type(): QuestionType
    {
        return QuestionType::Text;
    }

    public static function fromArray(array $data): static
    {
        Guard::onlyKeys($data, ['max_length', 'reference_answer']);

        if (! is_int($data['max_length'] ?? null)) {
            throw InvalidQuestionDefinition::because('max_length must be an integer.');
        }

        return new self(
            $data['max_length'],
            Guard::nullableString($data['reference_answer'] ?? null, 'reference_answer', self::MAX_ANSWER_LENGTH),
        );
    }

    public function toArray(): array
    {
        return [
            'max_length' => $this->maxLength,
            'reference_answer' => $this->referenceAnswer,
        ];
    }
}
