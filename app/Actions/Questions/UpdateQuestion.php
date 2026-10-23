<?php

declare(strict_types=1);

namespace App\Actions\Questions;

use App\Domain\Questions\Points;
use App\Domain\Questions\QuestionData;
use App\Models\Question;
use App\Models\Tag;
use App\Support\QuestionImageStore;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Updates a question. `version` increments only when the content students see
 * or are graded on changes: body, definition or points. Category, tags,
 * difficulty, explanation and image edits keep the version.
 *
 * Attempts already started keep their snapshot, so editing a question that is
 * used in a test with attempts is allowed (the UI shows a notice).
 * The type of a question never changes.
 */
final class UpdateQuestion
{
    public function __construct(private readonly QuestionImageStore $images) {}

    public function handle(Question $question, QuestionData $data): Question
    {
        if ($data->type !== $question->type) {
            throw new LogicException('The type of a question can not change.');
        }

        $imagePath = $data->image === null ? null : $this->images->store($data->image);

        return DB::transaction(function () use ($question, $data, $imagePath): Question {
            $contentChanged = $question->body->toArray() !== $data->body->toArray()
                || $question->definition->toArray() !== $data->definition->toArray()
                || ! Points::of($question->points)->equals($data->points);

            $question->fill([
                'body' => $data->body,
                'explanation' => $data->explanation,
                'difficulty' => $data->difficulty,
                'points' => $data->points->toString(),
                'definition' => $data->definition,
            ]);
            $question->category()->associate($data->category);

            if ($imagePath !== null) {
                $question->image_path = $imagePath;
            } elseif ($data->removeImage) {
                $question->image_path = null;
            }

            if ($contentChanged) {
                $question->version++;
            }

            $question->save();
            $question->tags()->sync(array_map(fn (Tag $tag): int => $tag->id, $data->tags));

            return $question;
        });
    }
}
