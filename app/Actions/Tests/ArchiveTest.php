<?php

declare(strict_types=1);

namespace App\Actions\Tests;

use App\Enums\TestStatus;
use App\Models\Test;
use Illuminate\Support\Facades\DB;

/**
 * Archives a test: it disappears from student lists (only published tests are
 * listed), in-progress attempts may still finish until their deadline, and
 * results stay visible according to the test's settings. Not reversible here:
 * duplicate it to reuse it.
 */
final class ArchiveTest
{
    public function handle(Test $test): void
    {
        DB::transaction(function () use ($test): void {
            if ($test->status !== TestStatus::Archived) {
                $test->status = TestStatus::Archived;
                $test->save();
            }
        });
    }
}
