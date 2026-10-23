<?php

declare(strict_types=1);

use App\Domain\Tests\AvailableTests;
use App\Enums\TestStatus;
use App\Models\Group;
use App\Models\Test;
use App\Models\User;

/*
 * The visibility matrix: membership × assignment × test status × group archived.
 * Visible only when every condition holds.
 */
it('applies the student visibility rule', function (bool $member, bool $assigned, TestStatus $status, bool $groupArchived, bool $visible): void {
    $student = User::factory()->student()->create();
    $group = Group::factory()->create();
    $test = Test::factory()->for($group->owner, 'owner')->create(['status' => $status]);

    if ($member) {
        $group->members()->attach($student, ['joined_at' => now()]);
    }

    if ($assigned) {
        $group->tests()->attach($test, ['assigned_at' => now()]);
    }

    if ($groupArchived) {
        $group->delete();
    }

    expect(AvailableTests::forStudent($student)->whereKey($test->id)->exists())->toBe($visible);
})->with(function (): Generator {
    foreach ([true, false] as $member) {
        foreach ([true, false] as $assigned) {
            foreach (TestStatus::cases() as $status) {
                foreach ([false, true] as $archived) {
                    $visible = $member && $assigned && $status === TestStatus::Published && ! $archived;
                    $name = sprintf('%s, %s, %s, %s', $member ? 'member' : 'not member', $assigned ? 'assigned' : 'not assigned', $status->value, $archived ? 'group archived' : 'group active');

                    yield $name => [$member, $assigned, $status, $archived, $visible];
                }
            }
        }
    }
});

it('stays visible through another group when one group is archived', function (): void {
    $student = User::factory()->student()->create();
    $teacher = User::factory()->teacher()->create();
    $test = Test::factory()->for($teacher, 'owner')->published()->create();
    [$first, $second] = Group::factory()->count(2)->for($teacher, 'owner')->create()->all();

    foreach ([$first, $second] as $group) {
        $group->members()->attach($student, ['joined_at' => now()]);
        $group->tests()->attach($test, ['assigned_at' => now()]);
    }
    $first->delete();

    expect(AvailableTests::forStudent($student)->pluck('id')->all())->toBe([$test->id])
        ->and(AvailableTests::forStudent($student, $first)->exists())->toBeFalse()
        ->and(AvailableTests::forStudent($student, $second)->exists())->toBeTrue();
});

it('hides tests from removed or leaving members and after unassigning', function (): void {
    $student = User::factory()->student()->create();
    $group = Group::factory()->create();
    $test = Test::factory()->for($group->owner, 'owner')->published()->create();
    $group->members()->attach($student, ['joined_at' => now()]);
    $group->tests()->attach($test, ['assigned_at' => now()]);
    expect(AvailableTests::forStudent($student)->count())->toBe(1);

    app(App\Actions\Groups\UnassignTestFromGroup::class)->handle($group, $test);
    expect(AvailableTests::forStudent($student)->count())->toBe(0);

    $group->tests()->attach($test, ['assigned_at' => now()]);
    app(App\Actions\Groups\RemoveGroupMember::class)->handle($group, $student);
    expect(AvailableTests::forStudent($student)->count())->toBe(0);
});

it('lets a removed member finish an attempt already in progress, but not start a new one', function (): void {
    $student = User::factory()->student()->create();
    $group = Group::factory()->create();
    $test = Test::factory()->for($group->owner, 'owner')->published()->create(['max_attempts' => 3]);
    $test->questions()->attach(App\Models\Question::factory()->for($group->owner, 'owner')->create(), ['position' => 1]);
    $group->members()->attach($student, ['joined_at' => now()]);
    $group->tests()->attach($test, ['assigned_at' => now()]);

    $this->actingAs($student)->post(route('student.exams.attempts.store', $test->ulid));
    $attempt = App\Models\Attempt::query()->sole();
    app(App\Actions\Groups\RemoveGroupMember::class)->handle($group, $student);

    // In-progress attempts are resolved by the attempt itself (owner), not through AvailableTests.
    $this->get(route('student.attempts.show', $attempt))->assertOk();
    $this->post(route('student.attempts.submit', $attempt))->assertRedirect(route('student.attempts.result', $attempt));
    $this->post(route('student.exams.attempts.store', $test->ulid))->assertNotFound();
});
