<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Models\Question;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the owner's editor needs, including the definition as editor
 * input. It contains the answer: use only on the owner's edit page.
 * Requires `category` and `tags`.
 *
 * @mixin Question
 */
final class QuestionEditorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'type' => $this->type->value,
            'body' => $this->body->toArray(),
            'explanation' => $this->explanation?->toArray(),
            'category' => $this->category?->ulid,
            'tags' => $this->tags->map(fn (Tag $tag): string => $tag->ulid)->values()->all(),
            'difficulty' => $this->difficulty->value,
            'points' => $this->points,
            'definition' => app(QuestionTypeRegistry::class)->for($this->type)->toEditorInput($this->definition),
            'image_url' => QuestionResource::imageUrl($this->resource),
            'version' => $this->version,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
