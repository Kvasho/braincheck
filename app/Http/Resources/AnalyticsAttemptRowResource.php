<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Attempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A real attempt in the test analytics (owner / admin): the student, score,
 * status, duration and the number of anti-cheat events (stored rows plus
 * the overflow counters). Requires `user` and `events_count`.
 *
 * @mixin Attempt
 */
final class AnalyticsAttemptRowResource extends JsonResource
{
    /** Up to this many events: "a few" (warning); more: "many" (danger). */
    public const int FEW_EVENTS = 5;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $events = (int) $this->resource->getAttribute('events_count') + array_sum($this->event_overflow ?? []);

        return [
            'ulid' => $this->ulid,
            'student' => ['name' => $this->user->name, 'email' => $this->user->email],
            'status' => $this->status->value,
            'grading_status' => $this->grading_status->value,
            'percent' => $this->score_percent,
            'passed' => $this->passed,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'duration_seconds' => $this->submitted_at === null ? null : (int) $this->started_at->diffInSeconds($this->submitted_at, true),
            'events' => $events,
            'severity' => $events === 0 ? 'none' : ($events <= self::FEW_EVENTS ? 'few' : 'many'),
        ];
    }
}
