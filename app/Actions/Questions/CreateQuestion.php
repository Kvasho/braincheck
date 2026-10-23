<?php

declare(strict_types=1);

namespace App\Actions\Questions;

use App\Domain\Questions\QuestionData;
use App\Models\Question;
use App\Models\Tag;
use App\Models\User;
use App\Support\QuestionImageStore;
use Illuminate\Support\Facades\DB;

/**
 * Creates a question owned by $owner (version 1). No audit entry: question
 * edits are not security events; `updated_at` and `version` track them.
 */
final class CreateQuestion
{
    public function __construct(private readonly QuestionImageStore $images) {}

    public function handle(User $owner, QuestionData $data): Question
    {
        // Re-encode before the transaction: a rejected image changes nothing.
        $imagePath = $data->image === null ? null : $this->images->store($data->image);

        return DB::transaction(function () use ($owner, $data, $imagePath): Question {
            $question = new Question;
            $question->type = $data->type;
            $question->fill([
                'body' => $data->body,
                'explanation' => $data->explanation,
                'difficulty' => $data->difficulty,
                'points' => $data->points->toString(),
                'definition' => $data->definition,
            ]);
            $question->owner()->associate($owner);
            $question->category()->associate($data->category);
            $question->image_path = $imagePath;
            $question->version = 1;
            $question->save();

            $question->tags()->sync(array_map(fn (Tag $tag): int => $tag->id, $data->tags));

            return $question;
        });
    }
}
