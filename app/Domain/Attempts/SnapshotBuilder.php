<?php

declare(strict_types=1);

namespace App\Domain\Attempts;

use App\Domain\Questions\Points;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Enums\QuestionSelectionMode;
use App\Models\Question;
use App\Models\Test;
use Random\Randomizer;

/**
 * Freezes a test's questions for one attempt (previews now, real attempts in
 * Stage 7: the same code path).
 *
 * - Fixed tests use every attached question (archived ones too: they stay in a
 *   test until the teacher removes them); random pools draw N of them.
 * - Question order is shuffled when the test says so, otherwise pivot order.
 * - Each snapshot stores the question as it is now (type, body, explanation,
 *   definition, points, version, image) and the student payload, with option
 *   order already shuffled when enabled, so reloading shows the same order.
 *   Grading, review and certificates read only the snapshot.
 */
final readonly class SnapshotBuilder
{
    public function __construct(private QuestionTypeRegistry $registry) {}

    /**
     * @return list<array{question: Question, position: int, points: Points, snapshot: array<string, mixed>}>
     */
    public function build(Test $test, Randomizer $randomizer): array
    {
        $questions = $test->questions()->withTrashed()->get()->all();

        if ($test->selection_mode === QuestionSelectionMode::RandomPool) {
            $draw = min((int) $test->random_question_count, count($questions));
            $questions = $draw > 0 && $questions !== []
                ? array_values(array_intersect_key($questions, array_flip($randomizer->pickArrayKeys($questions, $draw))))
                : [];
        }

        if ($test->shuffle_questions) {
            $questions = $randomizer->shuffleArray($questions);
        }

        $snapshots = [];

        foreach (array_values($questions) as $index => $question) {
            /** @var object{points_override: string|null} $pivot */
            $pivot = $question->getRelation('pivot');
            $points = Points::of($pivot->points_override ?? $question->points);
            $handler = $this->registry->for($question->type);

            $snapshots[] = [
                'question' => $question,
                'position' => $index + 1,
                'points' => $points,
                'snapshot' => [
                    'question_ulid' => $question->ulid,
                    'type' => $question->type->value,
                    'version' => $question->version,
                    'body' => $question->body->toArray(),
                    'explanation' => $question->explanation?->toArray(),
                    'image_path' => $question->image_path,
                    'points' => $points->toString(),
                    'definition' => $question->definition->toArray(),
                    'player' => $handler->toPlayerPayload($question->definition, $randomizer, $test->shuffle_options),
                ],
            ];
        }

        return $snapshots;
    }
}
