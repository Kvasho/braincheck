<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Teacher\QuestionIndexRequest as TeacherQuestionIndexRequest;
use App\Models\Question;

final class QuestionIndexRequest extends TeacherQuestionIndexRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Question::class) === true;
    }
}
