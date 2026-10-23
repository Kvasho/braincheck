<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Attempts\FinishExpiredAttempt;
use App\Domain\Attempts\AttemptTiming;
use App\Models\Attempt;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Finishes real in-progress attempts whose deadline plus grace has passed
 * (status timed_out, submitted at the deadline, then grading). Scheduled
 * every minute (routes/console.php); the scheduler must run in production.
 * Idempotent, chunked, previews skipped. The player, autosave and submit
 * paths finish expired attempts lazily as well.
 */
#[Signature('attempts:expire')]
#[Description('Finish exam attempts whose deadline has passed')]
final class ExpireAttemptsCommand extends Command
{
    public function handle(FinishExpiredAttempt $finishExpired): int
    {
        $cutoff = now()->subSeconds(AttemptTiming::graceSeconds());
        $finished = 0;

        Attempt::query()->real()->inProgress()
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<', $cutoff)
            ->chunkById(100, function ($attempts) use ($finishExpired, &$finished): void {
                foreach ($attempts as $attempt) {
                    $finished += $finishExpired->handle($attempt) ? 1 : 0;
                }
            });

        $this->info("Finished {$finished} expired attempt(s).");

        return self::SUCCESS;
    }
}
