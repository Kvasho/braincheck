<?php

declare(strict_types=1);

namespace App\Domain\Tests;

use App\Models\Group;
use App\Models\Test;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single visibility rule for students. A test is visible to a student
 * when it is published (not draft, not archived, not deleted) and assigned to
 * at least one non-archived group the student is currently a member of.
 *
 * Every student-facing test lookup goes through here: never look a test up
 * by ULID alone (e.g. `AvailableTests::forStudent($user)->where('ulid', $ulid)`).
 * The schedule window (opens_at / closes_at) is a separate concern of the
 * exam engine: an assigned test is visible before it opens and after it closes.
 */
final class AvailableTests
{
    /**
     * @return Builder<Test>
     */
    public static function forStudent(User $student, ?Group $inGroup = null): Builder
    {
        return Test::query()
            ->published()
            ->whereHas('groups', function (Builder $groups) use ($student, $inGroup): void {
                // whereHas applies Group's SoftDeletes scope: archived groups never count.
                $groups->whereHas('members', fn (Builder $members) => $members->whereKey($student->id));

                if ($inGroup !== null) {
                    $groups->whereKey($inGroup->id);
                }
            });
    }
}
