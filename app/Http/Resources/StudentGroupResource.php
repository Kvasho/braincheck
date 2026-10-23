<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A group as its students see it: name, teacher's name and the number of
 * tests available to them. Never other members, emails or the invite code.
 * Requires `owner` and the `available_tests_count` attribute.
 *
 * @mixin Group
 */
final class StudentGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'description' => $this->description,
            'teacher' => ['name' => $this->owner->name],
            'tests_count' => (int) $this->resource->getAttribute('available_tests_count'),
        ];
    }
}
