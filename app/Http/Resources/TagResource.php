<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A tag row for the admin tag table. Requires questions_count.
 *
 * @mixin Tag
 */
final class TagResource extends JsonResource
{
    /**
     * @return array{ulid: string, name: string, slug: string, questions_count: int, created_at: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'slug' => $this->slug,
            'questions_count' => (int) $this->questions_count,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
