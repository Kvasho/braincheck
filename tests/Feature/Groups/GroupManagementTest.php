<?php

declare(strict_types=1);

use App\Models\Attempt;
use App\Models\Group;
use App\Models\Test;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PayloadLeakScanner;

function groupTeacher(): User
{
    $teacher = User::factory()->teacher()->withTwoFactor()->create();
    test()->actingAs($teacher);

    return $teacher;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function groupPayload(array $overrides = []): array
{
    return [
        'name' => 'Class 10A',
        'description' => 'Mathematics',
        'joining_enabled' => true,
        'invite_expiry' => 'never',
        ...$overrides,
    ];
}

describe('creating and editing', function (): void {
    it('creates a group with a fresh invite code and opens its invite tab', function (): void {
        $teacher = groupTeacher();

        $this->post(route('teacher.groups.store'), groupPayload())->assertRedirect();

        $group = Group::query()->sole();
        expect($group->owner_id)->toBe($teacher->id)
            ->and($group->invite_code)->toHaveLength(10)
            ->and($group->invite_code_expires_at)->toBeNull()
            ->and($group->joining_enabled)->toBeTrue();
    });

    it('sets the invite expiry from a preset or a custom Asia/Tbilisi time', function (array $input, string $expected): void {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00', 'UTC'));
        groupTeacher();

        $this->post(route('teacher.groups.store'), groupPayload($input))->assertSessionHasNoErrors();

        expect(Group::query()->sole()->invite_code_expires_at?->toDateTimeString())->toBe($expected);
    })->with([
        '1 day' => [['invite_expiry' => '1d'], '2026-10-24 10:00:00'],
        '7 days' => [['invite_expiry' => '7d'], '2026-10-30 10:00:00'],
        '30 days' => [['invite_expiry' => '30d'], '2026-11-22 10:00:00'],
        'custom' => [['invite_expiry' => 'custom', 'invite_expires_at' => '2026-12-01T09:00'], '2026-12-01 05:00:00'],
    ]);

    it('validates the form', function (array $input, string $field): void {
        groupTeacher();

        $this->post(route('teacher.groups.store'), groupPayload($input))->assertSessionHasErrors($field);
    })->with([
        'short name' => [['name' => 'ab'], 'name'],
        'long name' => [['name' => str_repeat('a', 101)], 'name'],
        'long description' => [['description' => str_repeat('a', 1001)], 'description'],
        'unknown expiry' => [['invite_expiry' => '90d'], 'invite_expiry'],
        'keep on create' => [['invite_expiry' => 'keep'], 'invite_expiry'],
        'custom without date' => [['invite_expiry' => 'custom'], 'invite_expires_at'],
        'custom in the past' => [['invite_expiry' => 'custom', 'invite_expires_at' => '2020-01-01T10:00'], 'invite_expires_at'],
    ]);

    it('updates a group and keeps the expiry when asked', function (): void {
        $teacher = groupTeacher();
        $expires = now()->addDays(3)->startOfMinute();
        $group = Group::factory()->for($teacher, 'owner')->create(['invite_code_expires_at' => $expires]);

        $this->put(route('teacher.groups.update', $group), groupPayload(['name' => 'Renamed', 'joining_enabled' => false, 'invite_expiry' => 'keep']))
            ->assertRedirect(route('teacher.groups.show', $group));

        $group->refresh();
        expect($group->name)->toBe('Renamed')
            ->and($group->joining_enabled)->toBeFalse()
            ->and($group->invite_code_expires_at?->equalTo($expires))->toBeTrue();
    });

    it('lists only own groups with counts and never the invite code', function (): void {
        $teacher = groupTeacher();
        $group = Group::factory()->for($teacher, 'owner')->withMembers(3)->create(['name' => 'Mine']);
        $group->tests()->attach(Test::factory()->for($teacher, 'owner')->published()->create(), ['assigned_at' => now()]);
        Group::factory()->create(['name' => 'Someone else\'s']);

        $response = $this->get(route('teacher.groups.index'))->assertOk();
        $rows = $response->viewData('page')['props']['groups']['data'];

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['members_count'])->toBe(3)
            ->and($rows[0]['tests_count'])->toBe(1)
            ->and($response->getContent())->not->toContain($group->invite_code);
    });

    it('archives a group and lists it under archived', function (): void {
        $teacher = groupTeacher();
        $group = Group::factory()->for($teacher, 'owner')->create();

        $this->post(route('teacher.groups.archive', $group))->assertRedirect(route('teacher.groups.index'));

        expect($group->fresh()?->trashed())->toBeTrue()
            ->and($this->get(route('teacher.groups.index', ['archived' => 'only']))->viewData('page')['props']['groups']['data'])->toHaveCount(1);
        $this->get(route('teacher.groups.show', $group))->assertNotFound();
    });
});

describe('group page', function (): void {
    it('shows members with emails and attempts on the group\'s tests, without the invite code', function (): void {
        $teacher = groupTeacher();
        $group = Group::factory()->for($teacher, 'owner')->create();
        $student = User::factory()->student()->create(['email' => 'member@example.com']);
        $group->members()->attach($student, ['joined_at' => now()]);
        $assigned = Test::factory()->for($teacher, 'owner')->published()->create();
        $other = Test::factory()->for($teacher, 'owner')->published()->create();
        $group->tests()->attach($assigned, ['assigned_at' => now()]);
        Attempt::factory()->for($assigned)->for($student)->submitted()->create();
        Attempt::factory()->for($other)->for($student)->submitted()->create();
        Attempt::factory()->for($assigned)->for($student)->create(['attempt_number' => 2, 'is_preview' => true]);

        $response = $this->get(route('teacher.groups.show', [$group, 'tab' => 'members']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('teacher/groups/show')
                ->where('tab', 'members')
                ->where('members.data.0.email', 'member@example.com')
                ->where('members.data.0.attempts_count', 1)
                ->has('tests', 1)
                ->where('assignableTests.0.value', $other->ulid));

        expect($response->getContent())->not->toContain($group->invite_code);
    });

    it('reveals the invite code and QR code only through the dedicated endpoint', function (): void {
        $teacher = groupTeacher();
        $group = Group::factory()->for($teacher, 'owner')->create();

        $response = $this->getJson(route('teacher.groups.invite-code.show', $group))->assertOk();

        expect($response->json('code'))->toBe($group->invite_code)
            ->and($response->json('join_url'))->toBe(route('join', ['code' => $group->invite_code]))
            ->and($response->json('qr'))->toStartWith('data:image/svg+xml;base64,')
            ->and(base64_decode(substr($response->json('qr'), 26)))->toContain('<svg')
            ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    });

    it('regenerates the code and toggles joining', function (): void {
        $teacher = groupTeacher();
        $group = Group::factory()->for($teacher, 'owner')->create();
        $old = $group->invite_code;

        $this->post(route('teacher.groups.invite-code.regenerate', $group))->assertRedirect();
        expect($group->fresh()?->invite_code)->not->toBe($old)->toHaveLength(10);

        $this->put(route('teacher.groups.joining.update', $group), ['joining_enabled' => false])->assertRedirect();
        expect($group->fresh()?->joining_enabled)->toBeFalse();
    });

    it('removes a member, but only a member', function (): void {
        $teacher = groupTeacher();
        $group = Group::factory()->for($teacher, 'owner')->withMembers(2)->create();
        $member = $group->members()->firstOrFail();

        $this->delete(route('teacher.groups.members.destroy', [$group, $member]))->assertRedirect();
        expect($group->members()->count())->toBe(1);

        $this->delete(route('teacher.groups.members.destroy', [$group, User::factory()->student()->create()]))->assertNotFound();
    });
});

describe('assigning tests', function (): void {
    it('assigns own published tests and unassigns them', function (): void {
        $teacher = groupTeacher();
        $group = Group::factory()->for($teacher, 'owner')->create();
        $test = Test::factory()->for($teacher, 'owner')->published()->create();

        $this->post(route('teacher.groups.tests.store', $group), ['test' => $test->ulid])->assertSessionHasNoErrors();
        $this->post(route('teacher.groups.tests.store', $group), ['test' => $test->ulid])->assertSessionHasNoErrors();
        expect($group->tests()->count())->toBe(1);

        $this->delete(route('teacher.groups.tests.destroy', [$group, $test]))->assertRedirect();
        expect($group->tests()->count())->toBe(0);
    });

    it('rejects another teacher\'s test (403) and drafts or archived tests (422)', function (): void {
        $teacher = groupTeacher();
        $group = Group::factory()->for($teacher, 'owner')->create();

        $this->postJson(route('teacher.groups.tests.store', $group), ['test' => Test::factory()->published()->create()->ulid])->assertForbidden();
        $this->postJson(route('teacher.groups.tests.store', $group), ['test' => Test::factory()->for($teacher, 'owner')->create()->ulid])->assertUnprocessable()->assertJsonValidationErrors('test');
        $this->postJson(route('teacher.groups.tests.store', $group), ['test' => Test::factory()->for($teacher, 'owner')->archived()->create()->ulid])->assertUnprocessable();

        expect($group->tests()->count())->toBe(0);
    });
});

it('keeps other teachers out of a group (IDOR)', function (): void {
    $group = Group::factory()->withMembers(1)->create();
    $member = $group->members()->firstOrFail();
    $code = $group->invite_code;
    $teacher = groupTeacher();
    $myTest = Test::factory()->for($teacher, 'owner')->published()->create();

    $this->get(route('teacher.groups.show', $group))->assertForbidden();
    $this->get(route('teacher.groups.edit', $group))->assertForbidden();
    $this->put(route('teacher.groups.update', $group), groupPayload())->assertForbidden();
    $this->getJson(route('teacher.groups.invite-code.show', $group))->assertForbidden();
    $this->post(route('teacher.groups.invite-code.regenerate', $group))->assertForbidden();
    $this->put(route('teacher.groups.joining.update', $group), ['joining_enabled' => false])->assertForbidden();
    $this->delete(route('teacher.groups.members.destroy', [$group, $member]))->assertForbidden();
    $this->post(route('teacher.groups.tests.store', $group), ['test' => $myTest->ulid])->assertForbidden();
    $this->post(route('teacher.groups.archive', $group))->assertForbidden();

    $group->refresh();
    expect($group->invite_code)->toBe($code)
        ->and($group->members()->count())->toBe(1)
        ->and($group->tests()->count())->toBe(0)
        ->and($group->trashed())->toBeFalse();
});

it('never serializes the invite code from the model', function (): void {
    $group = Group::factory()->create();

    expect($group->toArray())->not->toHaveKey('invite_code')
        ->and(PayloadLeakScanner::findings($group->toArray()))->toBe([]);
});
