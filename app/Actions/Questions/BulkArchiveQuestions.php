<?php

declare(strict_types=1);

namespace App\Actions\Questions;

use App\Models\Question;
use Illuminate\Support\Facades\DB;

/**
 * Archives several questions in one transaction (ownership checked before).
 */
final class BulkArchiveQuestions
{
    public function __construct(private readonly ArchiveQuestion $archive) {}

    /**
     * @param  list<Question>  $questions
     */
    public function handle(array $questions): void
    {
        DB::transaction(function () use ($questions): void {
            foreach ($questions as $question) {
                $this->archive->handle($question);
            }
        });
    }
}
