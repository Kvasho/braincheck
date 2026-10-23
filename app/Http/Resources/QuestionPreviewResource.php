<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Domain\Shared\Randomizers;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The question exactly as a student will see it while answering: body, image
 * and the handler's player payload (toPlayerPayload, the same code path the
 * exam engine uses). No definition, no explanation, nothing that reveals the answer.
 *
 * @mixin Question
 */
final class QuestionPreviewResource extends JsonResource
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
            'image_url' => QuestionResource::imageUrl($this->resource),
            'points' => $this->points,
            'player' => app(QuestionTypeRegistry::class)
                ->for($this->type)
                ->toPlayerPayload($this->definition, Randomizers::secure(), shuffle: true),
        ];
    }
}
