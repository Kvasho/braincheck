<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use App\Enums\AntiCheatEvent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A batch of client-reported anti-cheat events. Only client event types
 * (never `session_changed`), at most braincheck.attempt_events_per_request
 * per request, `meta` limited to whitelisted integer keys and 1 KB.
 * Times are not accepted: the server stamps them.
 */
final class RecordAttemptEventsRequest extends AttemptRequest
{
    public const int META_MAX_BYTES = 1024;

    /** @var list<string> */
    public const array META_KEYS = ['question', 'duration_ms', 'chars'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'list', 'min:1', 'max:'.(int) config('braincheck.attempt_events_per_request')],
            'events.*' => ['required', 'array:type,meta'],
            'events.*.type' => ['required', 'string', Rule::enum(AntiCheatEvent::class)->except([AntiCheatEvent::SessionChanged])],
            'events.*.meta' => ['nullable', 'array:'.implode(',', self::META_KEYS)],
            'events.*.meta.*' => ['integer', 'min:0', 'max:86400000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('events', []) as $index => $event) {
                    if (is_array($event) && is_array($event['meta'] ?? null) && strlen((string) json_encode($event['meta'])) > self::META_MAX_BYTES) {
                        $validator->errors()->add("events.{$index}.meta", __('The event details are too large.'));
                    }
                }
            },
        ];
    }

    /**
     * @return list<array{type: AntiCheatEvent, meta: array<string, int|string>|null}>
     */
    public function events(): array
    {
        $events = [];

        foreach ((array) $this->validated('events') as $event) {
            /** @var array{type: string, meta?: array<string, int|string>|null} $event */
            $events[] = [
                'type' => AntiCheatEvent::from($event['type']),
                'meta' => isset($event['meta']) && $event['meta'] !== [] ? $event['meta'] : null,
            ];
        }

        return $events;
    }
}
