<?php

declare(strict_types=1);

namespace App\Domain\Questions\Definitions;

use App\Enums\QuestionType;
use App\Exceptions\InvalidQuestionDefinition;

/**
 * Type-specific content of a question (the JSON `definition` column).
 * Contains the correct answer: it is secret and never sent to students as is.
 * Shapes are documented in docs/question-types.md.
 */
interface QuestionDefinition
{
    public function type(): QuestionType;

    /**
     * @param  array<array-key, mixed>  $data
     *
     * @throws InvalidQuestionDefinition
     */
    public static function fromArray(array $data): static;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
