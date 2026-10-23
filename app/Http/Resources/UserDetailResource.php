<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The admin user page: the listing fields plus dates, the block reason and
 * ownership/activity counts. Same allow-list rule as UserResource.
 * Requires `roles` and the counts (questions, tests, owned_groups, attempts) loaded.
 *
 * @mixin User
 */
final class UserDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new UserResource($this->resource))->toArray($request),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'blocked_at' => $this->blocked_at?->toIso8601String(),
            'blocked_reason' => $this->blocked_reason,
            'counts' => [
                'questions' => (int) $this->questions_count,
                'tests' => (int) $this->tests_count,
                'groups' => (int) $this->owned_groups_count,
                'attempts' => (int) $this->attempts_count,
            ],
        ];
    }
}
