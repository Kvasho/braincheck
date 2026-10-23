<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Test;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test assigned to a group, for the group's owner (status and window).
 *
 * @mixin Test
 */
final class GroupTestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pivot = $this->resource->relationLoaded('pivot') ? $this->resource->getRelation('pivot') : null;
        $assignedAt = $pivot instanceof Pivot ? $pivot->getAttribute('assigned_at') : null;

        return [
            'ulid' => $this->ulid,
            'title' => $this->title,
            'status' => $this->status->value,
            'opens_at' => $this->opens_at?->toIso8601String(),
            'closes_at' => $this->closes_at?->toIso8601String(),
            'assigned_at' => is_string($assignedAt) ? now()->parse($assignedAt)->toIso8601String() : null,
        ];
    }
}
