<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the teacher's group list (owner only). Requires members_count and
 * tests_count. Never contains the invite code.
 *
 * @mixin Group
 */
final class GroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'members_count' => (int) $this->members_count,
            'tests_count' => (int) $this->tests_count,
            'joining_enabled' => $this->joining_enabled,
            'accepts_joins' => $this->acceptsJoins(),
            'invite_expires_at' => $this->invite_code_expires_at?->toIso8601String(),
            'archived' => $this->trashed(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
