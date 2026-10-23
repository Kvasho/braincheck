<?php

declare(strict_types=1);

namespace App\Actions\Tests;

use App\Models\Test;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a locked test's closing time later (null = never closes). Earlier is
 * never allowed once students have taken the test.
 */
final class ExtendTestWindow
{
    /**
     * @throws ValidationException
     */
    public function handle(Test $test, ?CarbonImmutable $closesAt): void
    {
        $current = $test->closes_at;

        if ($closesAt !== null && ($current === null || $closesAt->lessThan($current))) {
            throw ValidationException::withMessages(['closes_at' => __('Once students have taken the test, it can only close later.')]);
        }

        if ($closesAt !== null && $test->opens_at !== null && ! $closesAt->greaterThan($test->opens_at)) {
            throw ValidationException::withMessages(['closes_at' => __('The test must close after it opens.')]);
        }

        DB::transaction(function () use ($test, $closesAt): void {
            $test->closes_at = $closesAt;
            $test->save();
        });
    }
}
