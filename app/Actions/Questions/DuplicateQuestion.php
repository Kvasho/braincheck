<?php

declare(strict_types=1);

namespace App\Actions\Questions;

use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Copies a question into a new one (version 1) with fresh random option /
 * item ids: the definition goes through the handler without its ids. Tags,
 * category, rich text and the image reference are copied.
 */
final class DuplicateQuestion
{
    public function __construct(private readonly QuestionTypeRegistry $registry) {}

    public function handle(Question $source, User $owner): Question
    {
        return DB::transaction(function () use ($source, $owner): Question {
            $handler = $this->registry->for($source->type);
            $source->loadMissing('tags');

            $copy = new Question;
            $copy->type = $source->type;
            $copy->fill([
                'body' => $source->body,
                'explanation' => $source->explanation,
                'difficulty' => $source->difficulty,
                'points' => $source->points,
                'definition' => $handler->normalizeDefinition($handler->toEditorInput($source->definition, withIds: false)),
                'category_id' => $source->category_id,
            ]);
            $copy->owner()->associate($owner);
            $copy->image_path = $source->image_path;
            $copy->version = 1;
            $copy->save();

            $copy->tags()->sync($source->tags->modelKeys());

            return $copy;
        });
    }
}
