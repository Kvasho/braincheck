<?php

declare(strict_types=1);

use App\Enums\TestStatus;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptEvent;
use App\Models\AttemptQuestion;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Certificate;
use App\Models\Group;
use App\Models\Question;
use App\Models\Tag;
use App\Models\TeacherAccessRequest;
use App\Models\Test;
use App\Models\TestQuestionStat;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\XpTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * One in-memory instance of every model, owned by / belonging to $owner.
 *
 * @return array<class-string<Model>, Model>
 */
function policySubjects(User $owner): array
{
    $owned = fn (Model $model, string $column) => $model->forceFill([$column => $owner->id]);

    return [
        User::class => $owner,
        Category::class => new Category,
        Tag::class => new Tag,
        Question::class => $owned(new Question, 'owner_id'),
        Test::class => $owned(new Test, 'owner_id'),
        Group::class => $owned(new Group, 'owner_id'),
        TeacherAccessRequest::class => $owned(new TeacherAccessRequest, 'user_id'),
        Attempt::class => $owned(new Attempt, 'user_id'),
        AttemptQuestion::class => new AttemptQuestion,
        AttemptAnswer::class => new AttemptAnswer,
        AttemptEvent::class => new AttemptEvent,
        Certificate::class => $owned(new Certificate, 'user_id'),
        UserBadge::class => $owned(new UserBadge, 'user_id'),
        XpTransaction::class => $owned(new XpTransaction, 'user_id'),
        AuditLog::class => $owned(new AuditLog, 'actor_id'),
        TestQuestionStat::class => new TestQuestionStat,
    ];
}

/**
 * Public ability methods of a model's policy.
 *
 * @param  class-string<Model>  $model
 * @return list<string>
 */
function policyAbilities(string $model): array
{
    $policy = Gate::getPolicyFor($model);

    expect($policy)->not->toBeNull("No policy for {$model}");

    return collect((new ReflectionClass($policy))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->reject(fn (ReflectionMethod $method): bool => $method->isConstructor() || $method->isStatic())
        ->map(fn (ReflectionMethod $method): string => $method->getName())
        ->values()
        ->all();
}

function allows(User $user, string $ability, Model $subject): bool
{
    // viewAny / create take the class, everything else the instance.
    $argument = in_array($ability, ['viewAny', 'create'], true) ? $subject::class : $subject;

    return Gate::forUser($user)->allows($ability, $argument);
}

it('denies every ability on every model to an unrelated user without permissions', function (): void {
    $owner = User::factory()->teacher()->create();
    $stranger = User::factory()->create();

    $checked = 0;

    foreach (policySubjects($owner) as $model => $subject) {
        foreach (policyAbilities($model) as $ability) {
            expect(allows($stranger, $ability, $subject))->toBeFalse(class_basename($model)."::{$ability}");
            $checked++;
        }
    }

    // 7 standard abilities per model, plus the custom ones (see customAbilities()).
    expect($checked)->toBe(16 * 7 + count(array_merge(...array_values(customAbilities()))));
});

/**
 * Abilities beyond the standard seven, per model.
 *
 * @return array<class-string<Model>, list<string>>
 */
function customAbilities(): array
{
    return [
        User::class => ['changeRole', 'block', 'unblock'],
        Category::class => ['reorder'],
        Tag::class => ['merge'],
        TeacherAccessRequest::class => ['review'],
        Question::class => ['duplicate', 'preview', 'previewGrade', 'viewImage'],
        Test::class => ['publish', 'duplicate', 'preview', 'regrade', 'releaseResults', 'viewAnalytics'],
        Group::class => ['viewInviteCode', 'leave'],
        Attempt::class => ['take', 'grade'],
    ];
}

/**
 * The complete allow-list per role for resources the actor doesn't own:
 * admin oversight (*-any views) and the Stage 3 admin actions, and the
 * student's teacher access request. Everything else stays denied.
 *
 * @return array<string, array<class-string<Model>, list<string>>>
 */
function allowedAbilities(): array
{
    return [
        'admin' => [
            User::class => ['viewAny', 'view', 'changeRole', 'block', 'unblock'],
            Category::class => ['viewAny', 'create', 'update', 'delete', 'reorder'],
            Tag::class => ['viewAny', 'create', 'update', 'delete', 'merge'],
            AuditLog::class => ['viewAny', 'view'],
            TeacherAccessRequest::class => ['viewAny', 'review'],
            Question::class => ['viewAny', 'view', 'preview', 'viewImage'],
            Test::class => ['viewAny', 'view', 'regrade', 'viewAnalytics'],
            Group::class => ['viewAny', 'view'],
        ],
        // Creating is not about anyone else's resource.
        'teacher' => [
            Question::class => ['create'],
            Test::class => ['create'],
            Group::class => ['create'],
        ],
        'student' => [
            TeacherAccessRequest::class => ['create'],
        ],
    ];
}

it('denies every role access to other people\'s resources, except the allow-list', function (string $role): void {
    $owner = User::factory()->teacher()->create();
    $actor = User::factory()->withRole(App\Enums\Role::from($role))->create();

    $allowed = allowedAbilities()[$role];

    foreach (policySubjects($owner) as $model => $subject) {
        foreach (policyAbilities($model) as $ability) {
            $expected = in_array($ability, $allowed[$model] ?? [], true);

            expect(allows($actor, $ability, $subject))->toBe($expected, "{$role}: ".class_basename($model)."::{$ability}");
        }
    }
})->with(['student', 'teacher', 'admin']);

describe('owner rules', function (): void {
    it('lets a teacher view, update, archive, duplicate and preview their own question, never hard-delete it', function (): void {
        $teacher = User::factory()->teacher()->create();
        $question = (new Question)->forceFill(['owner_id' => $teacher->id]);

        expect(allows($teacher, 'view', $question))->toBeTrue()
            ->and(allows($teacher, 'update', $question))->toBeTrue()
            ->and(allows($teacher, 'delete', $question))->toBeTrue()
            ->and(allows($teacher, 'duplicate', $question))->toBeTrue()
            ->and(allows($teacher, 'previewGrade', $question))->toBeTrue()
            ->and(allows($teacher, 'restore', $question))->toBeFalse()
            ->and(allows($teacher, 'forceDelete', $question))->toBeFalse()
            ->and(allows($teacher, 'viewAny', $question))->toBeFalse();
    });

    it('does not let an owner update a soft-deleted question or group', function (): void {
        $teacher = User::factory()->teacher()->create();

        expect(allows($teacher, 'update', (new Question)->forceFill(['owner_id' => $teacher->id, 'deleted_at' => now()])))->toBeFalse()
            ->and(allows($teacher, 'update', (new Group)->forceFill(['owner_id' => $teacher->id, 'deleted_at' => now()])))->toBeFalse();
    });

    it('lets a teacher update their own test unless it is archived (locks are enforced by the Actions)', function (TestStatus $status, bool $allowed): void {
        $teacher = User::factory()->teacher()->create();
        $test = (new Test)->forceFill(['owner_id' => $teacher->id, 'status' => $status]);

        expect(allows($teacher, 'view', $test))->toBeTrue()
            ->and(allows($teacher, 'update', $test))->toBe($allowed);
    })->with([
        [TestStatus::Draft, true],
        [TestStatus::Published, true],
        [TestStatus::Archived, false],
    ]);

    it('lets a teacher manage their own group', function (): void {
        $teacher = User::factory()->teacher()->create();
        $group = (new Group)->forceFill(['owner_id' => $teacher->id]);

        expect(allows($teacher, 'view', $group))->toBeTrue()
            ->and(allows($teacher, 'update', $group))->toBeTrue()
            ->and(allows($teacher, 'delete', $group))->toBeTrue()
            ->and(allows($teacher, 'viewInviteCode', $group))->toBeTrue()
            ->and(allows($teacher, 'forceDelete', $group))->toBeFalse();
    });

    it('requires the matching permission, not only ownership', function (): void {
        // A student who somehow owns a question still lacks questions.*-own permissions.
        $student = User::factory()->student()->create();
        $question = (new Question)->forceFill(['owner_id' => $student->id]);

        expect(allows($student, 'view', $question))->toBeFalse()
            ->and(allows($student, 'update', $question))->toBeFalse();
    });

    it('lets a student view their own attempt and certificate only', function (): void {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();

        expect(allows($student, 'view', (new Attempt)->forceFill(['user_id' => $student->id])))->toBeTrue()
            ->and(allows($student, 'view', (new Attempt)->forceFill(['user_id' => $other->id])))->toBeFalse()
            ->and(allows($student, 'update', (new Attempt)->forceFill(['user_id' => $student->id])))->toBeFalse()
            ->and(allows($student, 'view', (new Certificate)->forceFill(['user_id' => $student->id])))->toBeTrue()
            ->and(allows($student, 'view', (new Certificate)->forceFill(['user_id' => $other->id])))->toBeFalse();
    });

    it('leaves blocking to EnsureUserIsNotBlocked (policies check ownership and permissions only)', function (): void {
        // Policies don't check blocking; EnsureUserIsNotBlocked logs blocked users out
        // before any controller runs. This documents that the owner rule itself is unchanged.
        $teacher = User::factory()->teacher()->blocked()->create();

        expect(allows($teacher, 'view', (new Question)->forceFill(['owner_id' => $teacher->id])))->toBeTrue();
    });
});
