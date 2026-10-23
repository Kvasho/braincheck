<?php

declare(strict_types=1);

use App\Actions\Groups\JoinGroup;
use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\Test;
use App\Models\User;

function joiningStudent(): User
{
    $student = User::factory()->student()->create();
    test()->actingAs($student);

    return $student;
}

describe('joining', function (): void {
    it('joins with a code typed in lower case with spaces', function (): void {
        $student = joiningStudent();
        $group = Group::factory()->create(['invite_code' => 'ABCDEFGHJK']);

        $this->post(route('student.groups.join.store'), ['code' => '  abcde fghjk '])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('student.groups.index'));

        expect($group->members()->whereKey($student->id)->exists())->toBeTrue();
    });

    it('is idempotent: joining twice is a success, not a second membership', function (): void {
        $student = joiningStudent();
        $group = Group::factory()->create();

        $this->post(route('student.groups.join.store'), ['code' => $group->invite_code])->assertSessionHasNoErrors();
        $this->post(route('student.groups.join.store'), ['code' => $group->invite_code])->assertSessionHasNoErrors();

        expect($group->members()->count())->toBe(1)
            ->and($student->groups()->count())->toBe(1);
    });

    it('fails identically for wrong, expired, disabled and archived codes', function (): void {
        joiningStudent();
        $expired = Group::factory()->expiredInvite()->create();
        $disabled = Group::factory()->joiningDisabled()->create();
        $archived = Group::factory()->create();
        $archived->delete();

        // 5 requests: within the per-minute limit.
        $responses = collect(['ZZZZZZZZZZ', $expired->invite_code, $disabled->invite_code, $archived->invite_code, 'short'])
            ->map(function (string $code): array {
                $response = $this->postJson(route('student.groups.join.store'), ['code' => $code]);

                return [$response->status(), $response->getContent()];
            });

        expect($responses->unique()->all())->toBe([[422, json_encode(['message' => JoinGroup::failureMessage(), 'errors' => ['code' => [JoinGroup::failureMessage()]]])]])
            ->and(Group::query()->withTrashed()->withCount('members')->get()->sum('members_count'))->toBe(0);
    });

    it('audits failures with only the first two characters and the length', function (): void {
        $student = joiningStudent();

        $this->post(route('student.groups.join.store'), ['code' => 'QWERTYUPAS']);

        $log = AuditLog::query()->where('action', AuditAction::GroupJoinFailed->value)->sole();
        expect($log->actor_id)->toBe($student->id)
            ->and($log->properties)->toBe(['input_prefix' => 'QW', 'input_length' => 10])
            ->and(json_encode($log->getAttributes()))->not->toContain('QWERTYUPAS');
    });

    it('stops working at once when the code is regenerated', function (): void {
        $group = Group::factory()->create();
        $old = $group->invite_code;
        $group->forceFill(['invite_code' => 'NEWCQDEXYZ'])->save();
        joiningStudent();

        $this->post(route('student.groups.join.store'), ['code' => $old])->assertSessionHasErrors('code');
        $this->post(route('student.groups.join.store'), ['code' => 'NEWCQDEXYZ'])->assertSessionHasNoErrors();
    });

    it('is only for students', function (): void {
        $group = Group::factory()->create();
        $this->actingAs(User::factory()->teacher()->withTwoFactor()->create());

        $this->post(route('student.groups.join.store'), ['code' => $group->invite_code])->assertForbidden();
        expect($group->members()->count())->toBe(0);
    });
});

describe('brute-force protection', function (): void {
    it('answers the 6th attempt within a minute with 429', function (): void {
        joiningStudent();

        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('student.groups.join.store'), ['code' => 'ZZZZZZZZZZ'])->assertUnprocessable();
        }

        $this->postJson(route('student.groups.join.store'), ['code' => 'ZZZZZZZZZZ'])->assertTooManyRequests();
    });

    it('caps attempts per day per user, across minutes and IPs', function (): void {
        config(['braincheck.join_group_daily_limit' => 7]);
        joiningStudent();

        foreach (range(1, 7) as $attempt) {
            $this->travel(1)->minutes();
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])
                ->postJson(route('student.groups.join.store'), ['code' => 'ZZZZZZZZZZ'])
                ->assertUnprocessable();
        }

        $this->travel(1)->minutes();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->postJson(route('student.groups.join.store'), ['code' => 'ZZZZZZZZZZ'])
            ->assertTooManyRequests();
    });
});

describe('my groups and leaving', function (): void {
    it('shows the student\'s groups with teacher name and test count, never members or emails', function (): void {
        $student = joiningStudent();
        $teacher = User::factory()->teacher()->create(['name' => 'Nino Teacher']);
        $group = Group::factory()->for($teacher, 'owner')->withMembers(3)->create();
        $group->members()->attach($student, ['joined_at' => now()]);
        $group->tests()->attach(Test::factory()->for($teacher, 'owner')->published()->create(), ['assigned_at' => now()]);
        $group->tests()->attach(Test::factory()->for($teacher, 'owner')->create(), ['assigned_at' => now()]);
        $others = $group->members()->whereKeyNot($student->id)->get();

        $response = $this->get(route('student.groups.index'))->assertOk();
        $groups = $response->viewData('page')['props']['groups'];

        expect($groups)->toHaveCount(1)
            ->and(array_keys($groups[0]))->toBe(['ulid', 'name', 'description', 'teacher', 'tests_count'])
            ->and($groups[0]['teacher'])->toBe(['name' => 'Nino Teacher'])
            ->and($groups[0]['tests_count'])->toBe(1);

        $html = (string) $response->getContent();
        foreach ($others as $other) {
            expect($html)->not->toContain($other->email)->not->toContain($other->name);
        }
        expect($html)->not->toContain($teacher->email)
            ->and($html)->not->toContain($group->invite_code);
    });

    it('leaves a group, and cannot leave one it is not in', function (): void {
        $student = joiningStudent();
        $group = Group::factory()->create();
        $group->members()->attach($student, ['joined_at' => now()]);
        $other = Group::factory()->withMembers(1)->create();

        $this->delete(route('student.groups.leave', $group))->assertRedirect(route('student.groups.index'));
        $this->delete(route('student.groups.leave', $other))->assertNotFound();

        expect($group->members()->count())->toBe(0)
            ->and($other->members()->count())->toBe(1);
    });
});

describe('join link', function (): void {
    it('sends a guest through login and back to the pre-filled join form', function (): void {
        $student = User::factory()->student()->create();

        $this->get(route('join', ['code' => 'abcde-fghjk']))->assertRedirect(route('login'));

        $this->post(route('login.store'), ['email' => $student->email, 'password' => 'password'])
            ->assertRedirect(route('join', ['code' => 'abcde-fghjk']));

        $this->get(route('join', ['code' => 'abcde-fghjk']))
            ->assertRedirect(route('student.groups.join', ['code' => 'ABCDEFGHJK']));

        $this->get(route('student.groups.join', ['code' => 'ABCDEFGHJK']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('student/groups/join')->where('code', 'ABCDEFGHJK'));
    });

    it('never echoes anything but code characters into the form', function (): void {
        joiningStudent();

        $this->get(route('student.groups.join', ['code' => '"><script>alert(1)</script>']))
            ->assertInertia(fn ($page) => $page->where('code', 'SCRIPTALERT1SCRIPT'));
    });

    it('sends teachers to their dashboard', function (): void {
        $this->actingAs(User::factory()->teacher()->withTwoFactor()->create());

        $this->get(route('join', ['code' => 'ABCDEFGHJK']))->assertRedirect(route('dashboard'));
    });
});
