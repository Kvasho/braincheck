<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The authenticated user as shared with every Inertia page.
 *
 * Explicit allow-list: never add secrets (password, 2FA secret/recovery codes, remember token).
 * The role is for display only; authorization uses permissions and policies.
 *
 * @mixin User
 */
final class AuthUserResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, email: string, email_verified_at: string|null, role: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'role' => $this->roleOrNull()?->value,
        ];
    }
}
