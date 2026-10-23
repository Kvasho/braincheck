<?php

declare(strict_types=1);

use App\Actions\TeacherRequests\ApproveTeacherRequest;
use App\Enums\AuditAction;
use App\Enums\Locale;
use App\Enums\Role;
use App\Enums\TeacherRequestStatus;
use App\Mail\TeacherRequestReviewed;
use App\Models\AuditLog;
use App\Models\TeacherAccessRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

const TEACHER_REQUEST_MESSAGE = 'I teach mathematics at a public school in Tbilisi and want to run my tests here.';

function reviewingAdmin(): User
{
    $admin = User::factory()->admin()->withTwoFactor()->create();
    test()->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    return $admin;
}

describe('submitting', function (): void {
    it('lets a student submit a request and audits it', function (): void {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->post(route('student.teacher-request.store'), ['message' => TEACHER_REQUEST_MESSAGE])
            ->assertRedirect(route('student.teacher-request.show'))
            ->assertSessionHasNoErrors();

        $request = TeacherAccessRequest::query()->sole();
        expect($request->user_id)->toBe($student->id)
            ->and($request->status)->toBe(TeacherRequestStatus::Pending)
            ->and($request->message)->toBe(TEACHER_REQUEST_MESSAGE);

        $log = AuditLog::query()->where('action', AuditAction::TeacherRequestSubmitted->value)->sole();
        expect($log->actor_id)->toBe($student->id)
            ->and($log->subject_type)->toBe('teacher_access_request')
            ->and($log->subject_id)->toBe($request->id);
    });

    it('validates the message', function (string $message): void {
        $this->actingAs(User::factory()->student()->create())
            ->post(route('student.teacher-request.store'), ['message' => $message])
            ->assertSessionHasErrors('message');

        expect(TeacherAccessRequest::query()->count())->toBe(0);
    })->with([
        'empty' => '',
        'too short' => 'Please',
        'too long' => str_repeat('a', 1001),
    ]);

    it('rejects a second request while one is pending', function (): void {
        $student = User::factory()->student()->create();
        TeacherAccessRequest::factory()->for($student)->create();

        $this->actingAs($student)
            ->post(route('student.teacher-request.store'), ['message' => TEACHER_REQUEST_MESSAGE])
            ->assertSessionHasErrors(['message' => __('You already have a pending request.')]);

        expect(TeacherAccessRequest::query()->count())->toBe(1);
    });

    it('is rate limited to three submissions per day', function (): void {
        $student = User::factory()->student()->create();
        $this->actingAs($student);

        foreach (range(1, 3) as $attempt) {
            $this->post(route('student.teacher-request.store'), ['message' => 'short'])->assertSessionHasErrors('message');
        }

        $this->post(route('student.teacher-request.store'), ['message' => TEACHER_REQUEST_MESSAGE])->assertStatus(429);
        expect(TeacherAccessRequest::query()->count())->toBe(0);
    });

    it('enforces the cooldown after a rejection', function (int $daysAgo, bool $allowed): void {
        $student = User::factory()->student()->create();
        TeacherAccessRequest::factory()->for($student)->rejected()->create(['reviewed_at' => now()->subDays($daysAgo)]);

        $response = $this->actingAs($student)
            ->post(route('student.teacher-request.store'), ['message' => TEACHER_REQUEST_MESSAGE]);

        if ($allowed) {
            $response->assertSessionHasNoErrors();
            expect(TeacherAccessRequest::query()->where('status', 'pending')->count())->toBe(1);
        } else {
            $response->assertSessionHasErrors('message');
            expect(TeacherAccessRequest::query()->where('status', 'pending')->count())->toBe(0);
        }
    })->with([
        'rejected yesterday' => [1, false],
        'rejected 6 days ago' => [6, false],
        'rejected 8 days ago' => [8, true],
    ]);

    it('uses the configured cooldown', function (): void {
        config(['braincheck.teacher_request_cooldown_days' => 30]);
        $student = User::factory()->student()->create();
        TeacherAccessRequest::factory()->for($student)->rejected()->create(['reviewed_at' => now()->subDays(10)]);

        $this->actingAs($student)
            ->post(route('student.teacher-request.store'), ['message' => TEACHER_REQUEST_MESSAGE])
            ->assertSessionHasErrors('message');
    });

    it('does not let teachers or admins submit', function (Role $role): void {
        $user = User::factory()->withRole($role)->withTwoFactor()->create();

        $this->actingAs($user)
            ->post(route('student.teacher-request.store'), ['message' => TEACHER_REQUEST_MESSAGE])
            ->assertForbidden();

        expect(TeacherAccessRequest::query()->count())->toBe(0);
    })->with([Role::Teacher, Role::Admin]);

    it('shows the student their latest request without reviewer details', function (): void {
        $student = User::factory()->student()->create();
        TeacherAccessRequest::factory()->for($student)->rejected()->create(['review_note' => 'Please add more detail.']);

        $this->actingAs($student)->get(route('student.teacher-request.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/teacher-request')
                ->where('latest.status', 'rejected')
                ->where('latest.review_note', 'Please add more detail.')
                ->where('latest.reviewer', null)
                ->where('canSubmit', false)
                ->whereType('cooldownEndsAt', 'string'));
    });
});

describe('reviewing', function (): void {
    it('lists pending requests first and shares the pending count', function (): void {
        reviewingAdmin();
        TeacherAccessRequest::factory()->rejected()->create(['created_at' => now()->subMinute()]);
        $pending = TeacherAccessRequest::factory()->create(['created_at' => now()->subDays(3)]);

        $this->get(route('admin.teacher-requests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/teacher-requests/index')
                ->where('requests.data.0.ulid', $pending->ulid)
                ->where('navBadges.teacherRequests', 1));
    });

    it('requires password confirmation to approve', function (): void {
        $request = TeacherAccessRequest::factory()->create();

        $this->actingAs(User::factory()->admin()->withTwoFactor()->create())
            ->post(route('admin.teacher-requests.approve', $request))
            ->assertRedirect(route('password.confirm'));

        expect($request->fresh()?->status)->toBe(TeacherRequestStatus::Pending);
    });

    it('approves: the student becomes a Teacher with exactly one role, audited and emailed', function (): void {
        Mail::fake();
        $admin = reviewingAdmin();
        $student = User::factory()->student()->create(['locale' => Locale::Georgian]);
        $request = TeacherAccessRequest::factory()->for($student)->create();

        $this->post(route('admin.teacher-requests.approve', $request), ['note' => 'Welcome aboard!'])
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.type', 'success');

        $request->refresh();
        expect($request->status)->toBe(TeacherRequestStatus::Approved)
            ->and($request->reviewed_by)->toBe($admin->id)
            ->and($request->review_note)->toBe('Welcome aboard!')
            ->and($student->fresh()?->roles->pluck('name')->all())->toBe(['teacher']);

        expect(AuditLog::query()->where('action', AuditAction::TeacherRequestApproved->value)->sole()->actor_id)->toBe($admin->id)
            ->and(AuditLog::query()->where('action', AuditAction::UserRoleChanged->value)->count())->toBe(1);

        Mail::assertQueued(TeacherRequestReviewed::class, fn (TeacherRequestReviewed $mail): bool => $mail->hasTo($student->email)
            && $mail->locale === 'ka'
            && $mail->teacherRequest->is($request));
    });

    it('rejects: audited and emailed, the role stays Student', function (): void {
        Mail::fake();
        $admin = reviewingAdmin();
        $student = User::factory()->student()->create(['locale' => Locale::English]);
        $request = TeacherAccessRequest::factory()->for($student)->create();

        $this->post(route('admin.teacher-requests.reject', $request), ['note' => 'Please tell us more.'])
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.type', 'success');

        expect($request->fresh()?->status)->toBe(TeacherRequestStatus::Rejected)
            ->and($student->fresh()?->role())->toBe(Role::Student);

        $log = AuditLog::query()->where('action', AuditAction::TeacherRequestRejected->value)->sole();
        expect($log->actor_id)->toBe($admin->id)
            ->and($log->properties)->toBe(['user' => $student->ulid, 'note' => 'Please tell us more.']);

        Mail::assertQueued(TeacherRequestReviewed::class, fn (TeacherRequestReviewed $mail): bool => $mail->hasTo($student->email)
            && $mail->locale === 'en');
    });

    it('never processes a request twice', function (): void {
        Mail::fake();
        $admin = reviewingAdmin();
        $student = User::factory()->student()->create();
        $request = TeacherAccessRequest::factory()->for($student)->create();

        $this->post(route('admin.teacher-requests.approve', $request))->assertSessionHas('inertia.flash_data.toast.type', 'success');

        // A second admin (or a double click) acting on the stale request.
        $this->post(route('admin.teacher-requests.approve', $request))->assertSessionHas('inertia.flash_data.toast.type', 'error');
        $this->post(route('admin.teacher-requests.reject', $request))->assertSessionHas('inertia.flash_data.toast.type', 'error');

        expect($request->fresh()?->status)->toBe(TeacherRequestStatus::Approved)
            ->and(AuditLog::query()->where('action', AuditAction::UserRoleChanged->value)->count())->toBe(1)
            ->and(AuditLog::query()->where('action', AuditAction::TeacherRequestApproved->value)->count())->toBe(1)
            ->and(AuditLog::query()->where('action', AuditAction::TeacherRequestRejected->value)->count())->toBe(0);
        Mail::assertQueuedCount(1);
    });

    it('does not approve a requester who is no longer a student', function (): void {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $request = TeacherAccessRequest::factory()->for(User::factory()->teacher())->create();

        expect(fn () => app(ApproveTeacherRequest::class)->handle($request, $admin))
            ->toThrow(App\Exceptions\TeacherRequestNotAllowed::class);

        expect($request->fresh()?->status)->toBe(TeacherRequestStatus::Pending);
        Mail::assertNothingQueued();
    });

    it('validates the note', function (): void {
        reviewingAdmin();
        $request = TeacherAccessRequest::factory()->create();

        $this->post(route('admin.teacher-requests.reject', $request), ['note' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('note');
    });
});

describe('email', function (): void {
    it('renders in the recipient\'s language and escapes the note', function (): void {
        $student = User::factory()->student()->create(['name' => 'Nino']);
        $request = TeacherAccessRequest::factory()->for($student)->rejected()->create(['review_note' => '<b>Try</b> again']);

        $georgian = (new TeacherRequestReviewed($request))->locale('ka')->render();
        $english = (new TeacherRequestReviewed($request))->locale('en')->render();

        expect($georgian)->toContain('გამარჯობა, Nino!')
            ->and($english)->toContain('Hello Nino,')
            ->and($english)->toContain('&lt;b&gt;Try&lt;/b&gt; again')
            ->and($english)->not->toContain('<b>Try</b>');
    });
});
