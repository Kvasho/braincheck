<?php

declare(strict_types=1);

namespace App\Actions\Questions;

use App\Models\Question;

final class RestoreQuestion
{
    public function handle(Question $question): void
    {
        if ($question->trashed()) {
            $question->restore();
        }
    }
}
