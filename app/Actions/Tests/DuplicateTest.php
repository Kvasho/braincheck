<?php

declare(strict_types=1);

namespace App\Actions\Tests;

use App\Enums\TestStatus;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * An independent draft copy ("Duplicate as new version"): same settings and
 * question links (with overrides), no schedule, not locked, title "(copy)".
 */
final class DuplicateTest
{
    public const int TITLE_MAX = 150;

    public function handle(Test $source, User $owner): Test
    {
        return DB::transaction(function () use ($source, $owner): Test {
            $suffix = ' '.__('(copy)');

            $copy = $source->replicate(['ulid', 'status', 'published_at', 'locked_at', 'opens_at', 'closes_at', 'deleted_at']);
            $copy->title = Str::limit($source->title, self::TITLE_MAX - mb_strlen($suffix), '').$suffix;
            $copy->status = TestStatus::Draft;
            $copy->opens_at = null;
            $copy->closes_at = null;
            $copy->published_at = null;
            $copy->locked_at = null;
            $copy->owner()->associate($owner);
            $copy->save();

            $links = [];

            foreach ($source->questions()->withTrashed()->get() as $question) {
                /** @var Question $question */
                /** @var object{position: int, points_override: string|null} $pivot */
                $pivot = $question->getRelation('pivot');
                $links[$question->id] = ['position' => $pivot->position, 'points_override' => $pivot->points_override];
            }

            $copy->questions()->attach($links);

            return $copy;
        });
    }
}
