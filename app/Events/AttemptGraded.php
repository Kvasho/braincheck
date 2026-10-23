<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Attempt;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An attempt became fully graded (grading_status = graded, `passed` final).
 * Fired once per attempt, after commit; consumed by certificates (Stage 10)
 * and XP (Stage 11). Regrades fire AttemptRegraded instead.
 */
final class AttemptGraded implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Attempt $attempt) {}
}
