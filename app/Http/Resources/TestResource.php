<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Test;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the teacher's test list. Requires `category` and the counts
 * questions_count, groups_count and real_attempts_count (previews excluded).
 *
 * @mixin Test
 */
final class TestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'title' => $this->title,
            'status' => $this->status->value,
            'locked' => $this->isLocked(),
            'questions_count' => (int) $this->questions_count,
            'groups_count' => (int) $this->groups_count,
            'attempts_count' => (int) $this->real_attempts_count,
            'opens_at' => $this->opens_at?->toIso8601String(),
            'closes_at' => $this->closes_at?->toIso8601String(),
            'category' => $this->category === null ? null : [
                'ulid' => $this->category->ulid,
                'name' => $this->category->name->toArray(),
            ],
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
