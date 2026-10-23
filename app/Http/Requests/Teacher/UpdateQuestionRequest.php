<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Enums\QuestionType;
use App\Models\Question;

/**
 * The type is taken from the question itself: it never changes.
 */
final class UpdateQuestionRequest extends QuestionRequest
{
    public function authorize(): bool
    {
        $question = $this->question();

        return $question !== null && $this->user()?->can('update', $question) === true;
    }

    protected function questionType(): ?QuestionType
    {
        return $this->question()?->type;
    }

    private function question(): ?Question
    {
        $question = $this->route('question');

        return $question instanceof Question ? $question : null;
    }
}
