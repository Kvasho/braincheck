<?php

declare(strict_types=1);

namespace App\Actions\Questions;

use App\Models\Question;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

/**
 * Adds or removes tags on several questions at once, in one transaction.
 * Ownership of every question is checked before (BulkQuestionsRequest):
 * a request with any foreign question is rejected as a whole.
 */
final class BulkTagQuestions
{
    /**
     * @param  list<Question>  $questions
     * @param  list<Tag>  $tags
     */
    public function handle(array $questions, array $tags, bool $attach): void
    {
        $tagIds = array_map(fn (Tag $tag): int => $tag->id, $tags);

        DB::transaction(function () use ($questions, $tagIds, $attach): void {
            foreach ($questions as $question) {
                $attach
                    ? $question->tags()->syncWithoutDetaching($tagIds)
                    : $question->tags()->detach($tagIds);
            }
        });
    }
}
