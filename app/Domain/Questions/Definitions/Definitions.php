<?php

declare(strict_types=1);

namespace App\Domain\Questions\Definitions;

use App\Enums\QuestionType;
use App\Exceptions\InvalidQuestionDefinition;

/**
 * Maps a question type to its definition value object.
 */
final class Definitions
{
    /**
     * @return class-string<QuestionDefinition>
     */
    public static function classFor(QuestionType $type): string
    {
        return match ($type) {
            QuestionType::SingleChoice => SingleChoiceDefinition::class,
            QuestionType::MultipleChoice => MultipleChoiceDefinition::class,
            QuestionType::TrueFalse => TrueFalseDefinition::class,
            QuestionType::Text => TextDefinition::class,
            QuestionType::Numeric => NumericDefinition::class,
            QuestionType::Ordering => OrderingDefinition::class,
            QuestionType::Matching => MatchingDefinition::class,
        };
    }

    /**
     * @param  array<array-key, mixed>  $data
     *
     * @throws InvalidQuestionDefinition
     */
    public static function fromArray(QuestionType $type, array $data): QuestionDefinition
    {
        return self::classFor($type)::fromArray($data);
    }
}
