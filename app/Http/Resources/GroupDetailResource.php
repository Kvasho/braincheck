<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Group;
use App\Support\AppTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The owner's view of a group (page header, edit form). Requires
 * members_count and tests_count. The invite code is never part of it: it
 * comes only from the dedicated reveal endpoint.
 *
 * @mixin Group
 */
final class GroupDetailResource extends JsonResource
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
            'joining_enabled' => $this->joining_enabled,
            'accepts_joins' => $this->acceptsJoins(),
            'invite_expires_at' => $this->invite_code_expires_at?->toIso8601String(),
            'invite_expires_at_local' => AppTime::toLocalInput($this->invite_code_expires_at),
            'members_count' => (int) $this->members_count,
            'tests_count' => (int) $this->tests_count,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
