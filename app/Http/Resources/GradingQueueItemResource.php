<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Shared\RichTextDocument;
use App\Models\AttemptAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A row of the grading queue (test owner only). The student is the label
 * the controller set (`student_label`: "Student #N" in anonymous mode).
 * Requires `attemptQuestion.attempt.test`.
 *
 * @mixin AttemptAnswer
 */
final class GradingQueueItemResource extends JsonResource
{
    public const int EXCERPT_LENGTH = 120;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $question = $this->attemptQuestion;
        $attempt = $question->attempt;

        return [
            'attempt' => $attempt->ulid,
            'position' => $question->position,
            'test' => ['ulid' => $attempt->test->ulid, 'title' => $attempt->test->title],
            'excerpt' => Str::limit(RichTextDocument::fromArray((array) $question->snapshot['body'])->plainText, self::EXCERPT_LENGTH),
            'student' => (string) $this->resource->getAttribute('student_label'),
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
        ];
    }
}
