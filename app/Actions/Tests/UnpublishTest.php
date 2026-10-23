<?php

declare(strict_types=1);

namespace App\Actions\Tests;

use App\Enums\TestStatus;
use App\Models\Test;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Back to draft, only while no real attempt exists (not locked). A locked
 * test can only be archived.
 */
final class UnpublishTest
{
    /**
     * @throws ValidationException
     */
    public function handle(Test $test): void
    {
        DB::transaction(function () use ($test): void {
            $locked = Test::query()->whereKey($test->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== TestStatus::Published) {
                throw ValidationException::withMessages(['test' => __('Only published tests can be unpublished.')]);
            }

            if ($locked->isLocked()) {
                throw ValidationException::withMessages(['test' => __('Students have already taken this test: it can only be archived.')]);
            }

            $locked->status = TestStatus::Draft;
            $locked->published_at = null;
            $locked->save();
        });

        $test->refresh();
    }
}
