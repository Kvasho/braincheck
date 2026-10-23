<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Question;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A question row in the bank listings. Never the definition (it holds the
 * answer). Requires `category`, `tags` and `tests_count`; `owner` is included
 * when loaded (admin browse).
 *
 * @mixin Question
 */
final class QuestionResource extends JsonResource
{
    public const int EXCERPT_LENGTH = 140;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $owner = $this->relationLoaded('owner') ? $this->owner : null;

        return [
            'ulid' => $this->ulid,
            'excerpt' => Str::limit(preg_replace('/\s+/u', ' ', $this->body_text) ?? '', self::EXCERPT_LENGTH),
            'type' => $this->type->value,
            'difficulty' => $this->difficulty->value,
            'points' => $this->points,
            'version' => $this->version,
            'category' => $this->category === null ? null : [
                'ulid' => $this->category->ulid,
                'name' => $this->category->name->toArray(),
            ],
            'tags' => $this->tags->map(fn (Tag $tag): array => ['ulid' => $tag->ulid, 'name' => $tag->name])->values()->all(),
            'tests_count' => (int) $this->tests_count,
            'has_image' => $this->image_path !== null,
            'archived' => $this->trashed(),
            'owner' => $owner === null ? null : ['ulid' => $owner->ulid, 'name' => $owner->name],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public static function imageUrl(Question $question): ?string
    {
        if ($question->image_path === null) {
            return null;
        }

        // The version parameter changes with the file, so a new image is never served from cache.
        return route('question-images.show', ['question' => $question->ulid, 'v' => substr(hash('sha256', $question->image_path), 0, 12)]);
    }
}
