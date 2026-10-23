<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Tag;
use App\Models\TeacherAccessRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An audit entry for the admin UI (user page, dashboard, audit log viewer).
 * Requires `actor` and `subject` to be loaded.
 *
 * `properties` are returned as stored data (already sanitized when recorded);
 * the UI renders them as text, never as HTML.
 *
 * @mixin AuditLog
 */
final class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $this->actor;

        return [
            'action' => $this->action,
            'action_label' => AuditAction::tryFrom($this->action)?->label() ?? $this->action,
            'actor' => $actor === null ? null : [
                'ulid' => $actor->ulid,
                'name' => $actor->name,
                'email' => $actor->email,
            ],
            'subject' => $this->subjectSummary(),
            'properties' => $this->properties,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array{type: string, type_label: string, label: string|null, ulid: string|null}|null
     */
    private function subjectSummary(): ?array
    {
        if ($this->subject_type === null) {
            return null;
        }

        $subject = $this->subject;

        [$label, $ulid] = match (true) {
            $subject instanceof User => ["{$subject->name} ({$subject->email})", $subject->ulid],
            $subject instanceof Category => [$subject->name->current(), $subject->ulid],
            $subject instanceof Tag => [$subject->name, $subject->ulid],
            $subject instanceof TeacherAccessRequest => [null, $subject->ulid],
            default => [null, null],
        };

        return [
            'type' => $this->subject_type,
            'type_label' => self::subjectTypeLabel($this->subject_type),
            'label' => $label,
            'ulid' => $ulid,
        ];
    }

    public static function subjectTypeLabel(string $type): string
    {
        return match ($type) {
            'user' => __('User'),
            'category' => __('Category'),
            'tag' => __('Tag'),
            'teacher_access_request' => __('Teacher request'),
            default => $type,
        };
    }
}
