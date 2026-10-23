<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TeacherAccessRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A teacher access request. `user` and `reviewer` are included only when the
 * relation was loaded (admin listing); the student's own page doesn't load them,
 * so it never shows who reviewed the request.
 *
 * @mixin TeacherAccessRequest
 */
final class TeacherAccessRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->relationLoaded('user') ? $this->user : null;
        $reviewer = $this->relationLoaded('reviewer') ? $this->reviewer : null;

        return [
            'ulid' => $this->ulid,
            'message' => $this->message,
            'status' => $this->status->value,
            'review_note' => $this->review_note,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $user === null ? null : [
                'ulid' => $user->ulid,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'reviewer' => $reviewer === null ? null : [
                'ulid' => $reviewer->ulid,
                'name' => $reviewer->name,
            ],
        ];
    }
}
