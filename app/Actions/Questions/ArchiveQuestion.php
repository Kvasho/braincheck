<?php

declare(strict_types=1);

namespace App\Actions\Questions;

use App\Models\Question;

/**
 * Archives (soft-deletes) a question. Tests that already use it keep it until
 * the teacher removes it there (Stage 5 shows a warning); attempts keep their
 * snapshots.
 */
final class ArchiveQuestion
{
    public function handle(Question $question): void
    {
        if (! $question->trashed()) {
            $question->delete();
        }
    }
}
