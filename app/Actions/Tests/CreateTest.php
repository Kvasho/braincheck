<?php

declare(strict_types=1);

namespace App\Actions\Tests;

use App\Enums\TestStatus;
use App\Models\Category;
use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a draft test with safe defaults; the builder configures the rest.
 */
final class CreateTest
{
    public const int DEFAULT_PASS_PERCENT = 60;

    public function handle(User $owner, string $title, ?string $description, ?Category $category): Test
    {
        return DB::transaction(function () use ($owner, $title, $description, $category): Test {
            $test = new Test([
                'title' => $title,
                'description' => $description,
                'pass_percent' => self::DEFAULT_PASS_PERCENT,
            ]);
            $test->owner()->associate($owner);
            $test->category()->associate($category);
            $test->status = TestStatus::Draft;
            $test->save();

            return $test;
        });
    }
}
