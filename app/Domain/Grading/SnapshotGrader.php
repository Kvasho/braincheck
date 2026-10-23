<?php

declare(strict_types=1);

namespace App\Domain\Grading;

use App\Domain\Questions\Definitions\Definitions;
use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Questions\GradeResult;
use App\Domain\Questions\Points;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Enums\QuestionType;
use App\Models\AttemptQuestion;

/**
 * Grades one attempt question from its **snapshot** only (type, definition,
 * points frozen at start): never from the live question.
 */
final readonly class SnapshotGrader
{
    public function __construct(private QuestionTypeRegistry $registry) {}

    /**
     * @param  array<array-key, mixed>|null  $answer
     */
    public function grade(AttemptQuestion $question, ?array $answer): GradeResult
    {
        return $this->registry->for(self::type($question))
            ->grade(self::definition($question), $answer, Points::of($question->points));
    }

    public static function type(AttemptQuestion $question): QuestionType
    {
        return QuestionType::from((string) $question->snapshot['type']);
    }

    public static function definition(AttemptQuestion $question): QuestionDefinition
    {
        return Definitions::fromArray(self::type($question), (array) $question->snapshot['definition']);
    }
}
