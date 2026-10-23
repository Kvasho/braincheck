<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user row in admin listings. Explicit allow-list of what the UI shows:
 * never 2FA secrets, recovery codes, remember tokens or password hashes
 * (2FA is only a yes/no flag). Requires the `roles` relation to be loaded.
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array{ulid: string, name: string, email: string, role: string|null, verified: bool, blocked: bool, has_two_factor: bool, xp_total: int, created_at: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->roleOrNull()?->value,
            'verified' => $this->email_verified_at !== null,
            'blocked' => $this->isBlocked(),
            'has_two_factor' => $this->hasConfirmedTwoFactor(),
            'xp_total' => $this->xp_total,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
