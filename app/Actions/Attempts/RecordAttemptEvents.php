<?php

declare(strict_types=1);

namespace App\Actions\Attempts;

use App\Enums\AntiCheatEvent;
use App\Enums\AttemptStatus;
use App\Exceptions\AttemptNotAccepting;
use App\Models\Attempt;
use App\Models\AttemptEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Stores client-reported anti-cheat events of an in-progress attempt. The
 * time is the server's. At most braincheck.attempt_events_per_attempt rows
 * are stored per attempt; beyond that only a per-type counter grows
 * (attempts.event_overflow). Events are information for the teacher and
 * never change a score.
 */
final class RecordAttemptEvents
{
    public function __construct(private readonly FinishExpiredAttempt $finishExpired) {}

    /**
     * @param  list<array{type: AntiCheatEvent, meta: array<string, int|string>|null}>  $events
     *
     * @throws AttemptNotAccepting
     */
    public function handle(Attempt $attempt, array $events): void
    {
        if ($this->finishExpired->handle($attempt)) {
            throw AttemptNotAccepting::expired($attempt);
        }

        DB::transaction(function () use ($attempt, $events): void {
            $locked = Attempt::lockedCopyOf($attempt) ?? throw (new ModelNotFoundException)->setModel(Attempt::class);

            if ($locked->status !== AttemptStatus::InProgress) {
                throw AttemptNotAccepting::finished($locked);
            }

            $now = CarbonImmutable::now();
            $room = max(0, (int) config('braincheck.attempt_events_per_attempt') - AttemptEvent::query()->where('attempt_id', $locked->id)->count());
            $overflow = $locked->event_overflow ?? [];

            foreach ($events as $index => $event) {
                if ($index < $room) {
                    $row = new AttemptEvent(['type' => $event['type'], 'occurred_at' => $now, 'meta' => $event['meta']]);
                    $row->attempt()->associate($locked);
                    $row->save();

                    continue;
                }

                $overflow[$event['type']->value] = ($overflow[$event['type']->value] ?? 0) + 1;
            }

            if ($overflow !== ($locked->event_overflow ?? [])) {
                $locked->forceFill(['event_overflow' => $overflow])->save();
            }
        });
    }
}
