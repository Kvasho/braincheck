<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use App\Domain\Attempts\AnswerUpdate;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Autosave body: `answer` (object, or null to clear) and/or `flagged`. The
 * answer's content is validated against the snapshot inside SaveAnswer.
 * Any other field (deadline_at, score, status, …) is ignored.
 * Authorization: AttemptRequest (owner of a real attempt, else 404).
 */
final class SaveAnswerRequest extends AttemptRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'answer' => ['nullable', 'array'],
            'flagged' => ['sometimes', 'boolean'],
        ];
    }

    public function update(): AnswerUpdate
    {
        $answer = $this->input('answer');

        /** @var array<array-key, mixed>|null $value */
        $value = is_array($answer) ? $answer : null;

        return new AnswerUpdate(
            hasAnswer: $this->exists('answer'),
            answer: $value,
            flagged: $this->exists('flagged') ? $this->boolean('flagged') : null,
        );
    }
}
