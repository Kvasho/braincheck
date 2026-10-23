<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Attempt;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Deletes teacher preview attempts older than braincheck.preview_attempt_ttl_hours
 * (24 h). Scheduled hourly (routes/console.php). Real attempts are never touched.
 */
#[Signature('braincheck:prune-preview-attempts')]
#[Description('Delete teacher preview attempts older than 24 hours')]
final class PrunePreviewAttemptsCommand extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subHours((int) config('braincheck.preview_attempt_ttl_hours', 24));
        $deleted = 0;

        Attempt::query()->preview()->where('created_at', '<', $cutoff)->chunkById(200, function ($attempts) use (&$deleted): void {
            foreach ($attempts as $attempt) {
                $attempt->delete();
                $deleted++;
            }
        });

        $this->info("Deleted {$deleted} preview attempt(s).");

        return self::SUCCESS;
    }
}
