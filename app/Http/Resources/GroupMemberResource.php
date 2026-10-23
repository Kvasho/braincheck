<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member row for the group's owner only (it contains the email). Requires
 * the selected `joined_at` and `group_attempts_count` (real attempts on the
 * group's tests). Never used on student pages.
 *
 * @mixin User
 */
final class GroupMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $joinedAt = $this->resource->getAttribute('joined_at');

        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'email' => $this->email,
            'joined_at' => is_string($joinedAt) ? now()->parse($joinedAt)->toIso8601String() : null,
            'attempts_count' => (int) $this->resource->getAttribute('group_attempts_count'),
        ];
    }
}
