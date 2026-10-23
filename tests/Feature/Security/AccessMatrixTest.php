<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| Access matrix
|--------------------------------------------------------------------------
|
| Every application route → expected outcome for every actor. Adding a route
| without classifying it here fails the completeness test below.
|
| Outcomes: an HTTP status (200, 403, 404, ...), 'login' (302 → login),
| 'verify' (302 → email verification notice), or any other route name
| (302 → that route).
|
*/

const ACTORS = ['guest', 'unverified', 'blocked', 'student', 'teacher', 'admin'];

/**
 * @return array<string, array{method: string, data?: array<string, mixed>, from?: string, params?: Closure(?User): array<string, mixed>, expect: array<string, int|string>}>
 */
function accessMatrix(): array
{
    $everyone = fn (int|string $outcome): array => array_fill_keys(ACTORS, $outcome);

    // Signed-in, verified, not blocked.
    $members = fn (int|string $outcome): array => [
        'guest' => 'login',
        'unverified' => 'verify',
        'blocked' => 'login',
        'student' => $outcome,
        'teacher' => $outcome,
        'admin' => $outcome,
    ];

    $only = fn (string $role): array => [
        'guest' => 'login',
        'unverified' => 'verify',
        'blocked' => 'login',
        'student' => $role === 'student' ? 200 : 403,
        'teacher' => $role === 'teacher' ? 200 : 403,
        'admin' => $role === 'admin' ? 200 : 403,
    ];

    // Admin-only actions behind `password.confirm`: without a recent confirmation
    // the admin is sent to the confirm page; everyone else is stopped earlier.
    $adminConfirm = [...$only('admin'), 'admin' => 'password.confirm'];

    $aUser = fn (): array => ['user' => User::factory()->student()->create()];
    // A question owned by the acting user (a teacher for guests), so the owner rule is what's tested.
    $ownQuestion = fn (?User $actor): array => ['question' => App\Models\Question::factory()->for($actor ?? User::factory()->teacher(), 'owner')->create()];
    $ownArchivedQuestion = function (?User $actor) use ($ownQuestion): array {
        $params = $ownQuestion($actor);
        $params['question']->delete();

        return $params;
    };
    $aTeachersQuestion = fn (): array => ['question' => App\Models\Question::factory()->create()];
    // Teacher-only routes where the teacher's request ends in a redirect back (validation) or elsewhere.
    $ownTest = fn (?User $actor): array => ['test' => App\Models\Test::factory()->for($actor ?? User::factory()->teacher(), 'owner')->create()];
    $ownTestWithQuestion = function (?User $actor) use ($ownTest): array {
        $params = $ownTest($actor);
        $params['test']->questions()->attach(App\Models\Question::factory()->for($params['test']->owner, 'owner')->create(), ['position' => 1]);

        return $params;
    };
    $ownPreview = function (?User $actor) use ($ownTest): array {
        $params = $ownTest($actor);
        $params['attempt'] = App\Models\Attempt::factory()->for($params['test'])->for($params['test']->owner)->create(['is_preview' => true]);

        return $params;
    };
    $ownGroup = fn (?User $actor): array => ['group' => App\Models\Group::factory()->for($actor ?? User::factory()->teacher(), 'owner')->create()];
    $ownGroupWithMember = function (?User $actor) use ($ownGroup): array {
        $params = $ownGroup($actor);
        $params['member'] = User::factory()->student()->create();
        $params['group']->members()->attach($params['member'], ['joined_at' => now()]);

        return $params;
    };
    $ownGroupWithTest = function (?User $actor) use ($ownGroup): array {
        $params = $ownGroup($actor);
        $params['test'] = App\Models\Test::factory()->for($params['group']->owner, 'owner')->published()->create();
        $params['group']->tests()->attach($params['test'], ['assigned_at' => now()]);

        return $params;
    };
    $joinedGroup = function (?User $actor): array {
        $group = App\Models\Group::factory()->create();

        if ($actor !== null) {
            $group->members()->attach($actor, ['joined_at' => now()]);
        }

        return ['group' => $group];
    };
    $visibleTest = fn (?User $actor): array => ['test' => Tests\Support\ExamFixtures::assignedTest($actor ?? User::factory()->student()->create())->ulid];
    $ownAttempt = function (?User $actor): array {
        $student = $actor ?? User::factory()->student()->create();
        $test = Tests\Support\ExamFixtures::assignedTest($student);

        return ['attempt' => app(App\Actions\Attempts\StartAttempt::class)->handle($student, $test, new App\Domain\Attempts\AttemptClient(null, null, null))['attempt']->ulid];
    };
    $ownAttemptQuestion = fn (?User $actor): array => [...$ownAttempt($actor), 'position' => 1];
    $ownFinishedAttempt = function (?User $actor) use ($ownAttempt): array {
        $params = $ownAttempt($actor);
        app(App\Actions\Attempts\SubmitAttempt::class)->handle(App\Models\Attempt::query()->where('ulid', $params['attempt'])->sole());

        return $params;
    };
    $gradableAttempt = function (?User $actor): array {
        $teacher = $actor ?? User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $test = Tests\Support\GradingFixtures::test($teacher, $student, [[new App\Domain\Questions\Definitions\TextDefinition(500, null), '2.00']]);

        return ['attempt' => Tests\Support\GradingFixtures::submitted($test, $student, [1 => ['text' => 'Matrix']])->ulid, 'position' => 1];
    };
    $gradableAttemptOnly = fn (?User $actor): array => ['attempt' => $gradableAttempt($actor)['attempt']];
    $anyTest = fn (): array => ['test' => App\Models\Test::factory()->create()];
    $testWithAttempt = function (?User $actor): array {
        $test = App\Models\Test::factory()->for($actor ?? User::factory()->teacher(), 'owner')->published()->create();

        return ['test' => $test, 'attempt' => App\Models\Attempt::factory()->for($test)->graded()->create()];
    };
    $anyTestWithAttempt = fn (): array => $testWithAttempt(null);
    $studentTo = fn (int|string $outcome): array => [...$only('student'), 'student' => $outcome];
    $teacherTo = fn (int|string $outcome): array => [...$only('teacher'), 'teacher' => $outcome];
    $aTag = fn (): array => ['tag' => App\Models\Tag::factory()->create()];
    $aCategory = fn (): array => ['category' => App\Models\Category::factory()->create()];
    // Admin mutations that redirect back to their index on success or failure.
    $adminTo = fn (string $route): array => [...$only('admin'), 'admin' => $route];
    $aTeacherRequest = fn (): array => ['teacherRequest' => App\Models\TeacherAccessRequest::factory()->create()];

    return [
        // Public
        'home' => ['method' => 'GET', 'expect' => [
            'guest' => 200,
            'unverified' => 'dashboard',
            'blocked' => 'login',
            'student' => 'dashboard',
            'teacher' => 'dashboard',
            'admin' => 'dashboard',
        ]],
        'locale.update' => ['method' => 'POST', 'data' => ['locale' => 'en'], 'from' => 'home', 'expect' => [
            ...$everyone('home'),
            'blocked' => 'login',
        ]],
        'well-known.passkeys' => ['method' => 'GET', 'expect' => [...$everyone(200), 'blocked' => 'login']],

        // Shared, signed-in
        'dashboard' => ['method' => 'GET', 'expect' => $members(200)],
        'join' => ['method' => 'GET', 'expect' => [...$members('dashboard'), 'student' => 'student.groups.join']],
        // Own question without an image: 404 for its owner and admins, 403 for students.
        'question-images.show' => ['method' => 'GET', 'params' => $ownQuestion, 'expect' => [...$members(404), 'student' => 403]],
        'settings' => ['method' => 'GET', 'expect' => $members('profile.edit')],
        'profile.edit' => ['method' => 'GET', 'expect' => $members(200)],
        'profile.update' => ['method' => 'PATCH', 'data' => ['name' => '', 'email' => ''], 'from' => 'profile.edit', 'expect' => $members('profile.edit')],
        'profile.destroy' => ['method' => 'DELETE', 'data' => ['password' => 'wrong-password'], 'from' => 'profile.edit', 'expect' => $members('profile.edit')],
        'security.edit' => ['method' => 'GET', 'expect' => $members('password.confirm')],
        'user-password.update' => ['method' => 'PUT', 'data' => [], 'from' => 'profile.edit', 'expect' => $members('profile.edit')],
        'appearance.edit' => ['method' => 'GET', 'expect' => $members(200)],

        // Admin area
        'admin.users.index' => ['method' => 'GET', 'expect' => $only('admin')],
        'admin.users.show' => ['method' => 'GET', 'params' => $aUser, 'expect' => $only('admin')],
        'admin.users.role.update' => ['method' => 'PUT', 'params' => $aUser, 'data' => ['role' => 'teacher'], 'from' => 'dashboard', 'expect' => $adminConfirm],
        'admin.users.block' => ['method' => 'POST', 'params' => $aUser, 'data' => ['reason' => 'Spam'], 'from' => 'dashboard', 'expect' => $adminConfirm],
        'admin.users.unblock' => ['method' => 'DELETE', 'params' => $aUser, 'from' => 'dashboard', 'expect' => $adminConfirm],
        'admin.teacher-requests.index' => ['method' => 'GET', 'expect' => $only('admin')],
        'admin.teacher-requests.approve' => ['method' => 'POST', 'params' => $aTeacherRequest, 'from' => 'dashboard', 'expect' => $adminConfirm],
        'admin.teacher-requests.reject' => ['method' => 'POST', 'params' => $aTeacherRequest, 'from' => 'dashboard', 'expect' => [...$only('admin'), 'admin' => 'dashboard']],
        'admin.categories.index' => ['method' => 'GET', 'expect' => $only('admin')],
        'admin.categories.store' => ['method' => 'POST', 'data' => ['name' => ['ka' => 'ტესტი', 'en' => 'Matrix']], 'from' => 'admin.categories.index', 'expect' => $adminTo('admin.categories.index')],
        'admin.categories.reorder' => ['method' => 'PUT', 'data' => ['order' => []], 'from' => 'admin.categories.index', 'expect' => $adminTo('admin.categories.index')],
        'admin.categories.update' => ['method' => 'PUT', 'params' => $aCategory, 'data' => ['name' => ['ka' => 'ტესტი', 'en' => 'Matrix']], 'from' => 'admin.categories.index', 'expect' => $adminTo('admin.categories.index')],
        'admin.categories.destroy' => ['method' => 'DELETE', 'params' => $aCategory, 'from' => 'admin.categories.index', 'expect' => $adminTo('admin.categories.index')],
        'admin.tags.index' => ['method' => 'GET', 'expect' => $only('admin')],
        'admin.tags.store' => ['method' => 'POST', 'data' => ['name' => 'matrix-tag'], 'from' => 'admin.tags.index', 'expect' => $adminTo('admin.tags.index')],
        'admin.tags.update' => ['method' => 'PUT', 'params' => $aTag, 'data' => ['name' => 'matrix-tag-renamed'], 'from' => 'admin.tags.index', 'expect' => $adminTo('admin.tags.index')],
        'admin.tags.destroy' => ['method' => 'DELETE', 'params' => $aTag, 'from' => 'admin.tags.index', 'expect' => $adminTo('admin.tags.index')],
        'admin.tags.merge' => ['method' => 'POST', 'params' => $aTag, 'data' => ['target' => ''], 'from' => 'admin.tags.index', 'expect' => $adminTo('admin.tags.index')],
        'admin.audit-log.index' => ['method' => 'GET', 'expect' => $only('admin')],
        'admin.questions.index' => ['method' => 'GET', 'expect' => $only('admin')],
        'admin.questions.show' => ['method' => 'GET', 'params' => $aTeachersQuestion, 'expect' => $only('admin')],
        'admin.tests.regrade.show' => ['method' => 'GET', 'params' => $anyTest, 'expect' => $only('admin')],
        'admin.tests.regrade.preview' => ['method' => 'POST', 'params' => $anyTest, 'from' => 'admin.users.index', 'expect' => $adminTo('admin.users.index')],
        'admin.tests.regrade.store' => ['method' => 'POST', 'params' => $anyTest, 'expect' => $adminConfirm],
        'admin.tests.analytics.show' => ['method' => 'GET', 'params' => $anyTest, 'expect' => $only('admin')],
        'admin.tests.analytics.recompute' => ['method' => 'POST', 'params' => $anyTest, 'expect' => [...$only('admin'), 'admin' => 302]],
        'admin.tests.analytics.attempts.show' => ['method' => 'GET', 'params' => $anyTestWithAttempt, 'expect' => $only('admin')],
        'admin.tests.results.export' => ['method' => 'GET', 'params' => $anyTest, 'expect' => $only('admin')],

        // Teacher area
        'teacher.questions.index' => ['method' => 'GET', 'expect' => $only('teacher')],
        'teacher.questions.create' => ['method' => 'GET', 'expect' => $only('teacher')],
        'teacher.questions.store' => ['method' => 'POST', 'data' => ['type' => 'true_false'], 'from' => 'teacher.questions.index', 'expect' => $teacherTo('teacher.questions.index')],
        'teacher.questions.bulk' => ['method' => 'POST', 'data' => ['action' => 'archive'], 'from' => 'teacher.questions.index', 'expect' => $teacherTo('teacher.questions.index')],
        'teacher.questions.edit' => ['method' => 'GET', 'params' => $ownQuestion, 'expect' => $only('teacher')],
        'teacher.questions.update' => ['method' => 'PUT', 'params' => $ownQuestion, 'from' => 'teacher.questions.index', 'expect' => $teacherTo('teacher.questions.index')],
        'teacher.questions.destroy' => ['method' => 'DELETE', 'params' => $ownQuestion, 'expect' => $teacherTo('teacher.questions.index')],
        'teacher.questions.restore' => ['method' => 'POST', 'params' => $ownArchivedQuestion, 'from' => 'teacher.questions.index', 'expect' => $teacherTo('teacher.questions.index')],
        'teacher.questions.duplicate' => ['method' => 'POST', 'params' => $ownQuestion, 'expect' => $teacherTo(302)],
        'teacher.questions.preview' => ['method' => 'GET', 'params' => $ownQuestion, 'expect' => $only('teacher')],
        'teacher.questions.preview.grade' => ['method' => 'POST', 'params' => $ownQuestion, 'data' => ['answer' => null], 'expect' => $only('teacher')],
        'teacher.tests.index' => ['method' => 'GET', 'expect' => $only('teacher')],
        'teacher.tests.create' => ['method' => 'GET', 'expect' => $only('teacher')],
        'teacher.tests.store' => ['method' => 'POST', 'data' => ['title' => 'Matrix test'], 'expect' => $teacherTo(302)],
        'teacher.tests.question-picker' => ['method' => 'GET', 'expect' => $only('teacher')],
        'teacher.tests.edit' => ['method' => 'GET', 'params' => $ownTest, 'expect' => $only('teacher')],
        'teacher.tests.update' => ['method' => 'PUT', 'params' => $ownTest, 'from' => 'teacher.tests.index', 'expect' => $teacherTo('teacher.tests.index')],
        'teacher.tests.publish' => ['method' => 'POST', 'params' => $ownTest, 'from' => 'teacher.tests.index', 'expect' => $teacherTo('teacher.tests.index')],
        'teacher.tests.unpublish' => ['method' => 'DELETE', 'params' => $ownTest, 'from' => 'teacher.tests.index', 'expect' => $teacherTo('teacher.tests.index')],
        'teacher.tests.archive' => ['method' => 'POST', 'params' => $ownTest, 'expect' => $teacherTo('teacher.tests.index')],
        'teacher.tests.duplicate' => ['method' => 'POST', 'params' => $ownTest, 'expect' => $teacherTo(302)],
        'teacher.tests.preview.store' => ['method' => 'POST', 'params' => $ownTestWithQuestion, 'expect' => $teacherTo(302)],
        'teacher.tests.preview.show' => ['method' => 'GET', 'params' => $ownPreview, 'expect' => $only('teacher')],
        'teacher.tests.preview.submit' => ['method' => 'POST', 'params' => $ownPreview, 'data' => ['answers' => '{}'], 'expect' => $only('teacher')],
        'teacher.groups.index' => ['method' => 'GET', 'expect' => $only('teacher')],
        'teacher.groups.create' => ['method' => 'GET', 'expect' => $only('teacher')],
        'teacher.groups.store' => ['method' => 'POST', 'data' => ['name' => 'Matrix group', 'joining_enabled' => true, 'invite_expiry' => 'never'], 'expect' => $teacherTo(302)],
        'teacher.groups.show' => ['method' => 'GET', 'params' => $ownGroup, 'expect' => $only('teacher')],
        'teacher.groups.edit' => ['method' => 'GET', 'params' => $ownGroup, 'expect' => $only('teacher')],
        'teacher.groups.update' => ['method' => 'PUT', 'params' => $ownGroup, 'from' => 'teacher.groups.index', 'expect' => $teacherTo('teacher.groups.index')],
        'teacher.groups.archive' => ['method' => 'POST', 'params' => $ownGroup, 'expect' => $teacherTo('teacher.groups.index')],
        'teacher.groups.invite-code.show' => ['method' => 'GET', 'params' => $ownGroup, 'expect' => $only('teacher')],
        'teacher.groups.invite-code.regenerate' => ['method' => 'POST', 'params' => $ownGroup, 'expect' => $teacherTo(302)],
        'teacher.groups.joining.update' => ['method' => 'PUT', 'params' => $ownGroup, 'data' => ['joining_enabled' => false], 'expect' => $teacherTo(302)],
        'teacher.groups.members.destroy' => ['method' => 'DELETE', 'params' => $ownGroupWithMember, 'expect' => $teacherTo(302)],
        'teacher.groups.tests.store' => ['method' => 'POST', 'params' => $ownGroup, 'from' => 'teacher.groups.index', 'expect' => $teacherTo('teacher.groups.index')],
        'teacher.groups.tests.destroy' => ['method' => 'DELETE', 'params' => $ownGroupWithTest, 'expect' => $teacherTo(302)],
        'teacher.grading.index' => ['method' => 'GET', 'expect' => $only('teacher')],
        'teacher.grading.anonymity.update' => ['method' => 'PUT', 'data' => ['anonymous' => true], 'expect' => $teacherTo('teacher.grading.index')],
        'teacher.grading.answers.show' => ['method' => 'GET', 'params' => $gradableAttempt, 'expect' => $only('teacher')],
        'teacher.grading.answers.update' => ['method' => 'PUT', 'params' => $gradableAttempt, 'from' => 'teacher.grading.index', 'expect' => $teacherTo('teacher.grading.index')],
        'teacher.grading.answers.override' => ['method' => 'PUT', 'params' => $gradableAttempt, 'from' => 'teacher.grading.index', 'expect' => $teacherTo('teacher.grading.index')],
        'teacher.grading.identity.store' => ['method' => 'POST', 'params' => $gradableAttemptOnly, 'expect' => $only('teacher')],
        'teacher.tests.regrade.show' => ['method' => 'GET', 'params' => $ownTest, 'expect' => $only('teacher')],
        'teacher.tests.regrade.preview' => ['method' => 'POST', 'params' => $ownTest, 'from' => 'teacher.tests.index', 'expect' => $teacherTo('teacher.tests.index')],
        'teacher.tests.regrade.store' => ['method' => 'POST', 'params' => $ownTest, 'expect' => $teacherTo('password.confirm')],
        'teacher.tests.analytics.show' => ['method' => 'GET', 'params' => $ownTest, 'expect' => $only('teacher')],
        'teacher.tests.analytics.recompute' => ['method' => 'POST', 'params' => $ownTest, 'expect' => $teacherTo(302)],
        'teacher.tests.analytics.attempts.show' => ['method' => 'GET', 'params' => $testWithAttempt, 'expect' => $only('teacher')],
        'teacher.tests.results.export' => ['method' => 'GET', 'params' => $ownTest, 'expect' => $only('teacher')],
        // Only `manual` tests can be released: the default test is refused with a validation error.
        'teacher.tests.results.release' => ['method' => 'POST', 'params' => $ownTest, 'from' => 'teacher.tests.index', 'expect' => $teacherTo('teacher.tests.index')],

        // Student area
        'student.groups.index' => ['method' => 'GET', 'expect' => $only('student')],
        'student.groups.join' => ['method' => 'GET', 'expect' => $only('student')],
        'student.groups.join.store' => ['method' => 'POST', 'data' => ['code' => 'ZZZZZZZZZZ'], 'from' => 'student.groups.join', 'expect' => $studentTo('student.groups.join')],
        'student.groups.leave' => ['method' => 'DELETE', 'params' => $joinedGroup, 'expect' => [...$only('student'), 'student' => 'student.groups.index']],
        'student.exams.index' => ['method' => 'GET', 'expect' => $only('student')],
        'student.results.index' => ['method' => 'GET', 'expect' => $only('student')],
        'student.exams.show' => ['method' => 'GET', 'params' => $visibleTest, 'expect' => $only('student')],
        'student.exams.attempts.store' => ['method' => 'POST', 'params' => $visibleTest, 'expect' => $studentTo(302)],
        'student.attempts.show' => ['method' => 'GET', 'params' => $ownAttempt, 'expect' => $only('student')],
        'student.attempts.answers.update' => ['method' => 'PUT', 'params' => $ownAttemptQuestion, 'data' => ['answer' => null], 'expect' => $only('student')],
        'student.attempts.submit' => ['method' => 'POST', 'params' => $ownAttempt, 'expect' => $studentTo(302)],
        'student.attempts.events.store' => ['method' => 'POST', 'params' => $ownAttempt, 'data' => ['events' => [['type' => 'copy']]], 'expect' => $studentTo(204)],
        // The question of the fixture has no image: 404 for the owner, the area guard for everyone else.
        'student.attempts.images.show' => ['method' => 'GET', 'params' => $ownAttemptQuestion, 'expect' => $studentTo(404)],
        'student.attempts.result' => ['method' => 'GET', 'params' => $ownFinishedAttempt, 'expect' => $only('student')],
        'student.teacher-request.show' => ['method' => 'GET', 'expect' => $only('student')],
        'student.teacher-request.store' => ['method' => 'POST', 'data' => ['message' => str_repeat('I would like to teach. ', 3)], 'from' => 'dashboard', 'expect' => [...$only('student'), 'student' => 'student.teacher-request.show']],
    ];
}

/**
 * Routes deliberately left out of the matrix, with the reason.
 *
 * @return array<string, string> pattern (route name or "METHOD uri") => reason
 */
function excludedRoutes(): array
{
    return [
        // Fortify authentication flows: covered by tests/Feature/Auth and Security tests.
        'login' => 'Fortify', 'login.store' => 'Fortify', 'logout' => 'Fortify',
        'register' => 'Fortify', 'register.store' => 'Fortify',
        'password.*' => 'Fortify password reset / confirmation',
        'verification.*' => 'Fortify email verification',
        'two-factor.*' => 'Fortify two-factor authentication',
        // laravel/passkeys: vendor routes, covered by vendor tests + BlockedUserTest.
        'passkey.*' => 'laravel/passkeys',
        // Framework: local disk serving, only via signed URLs.
        'storage.local' => 'framework (signed URLs)',
        'storage.local.upload' => 'framework (signed URLs)',
        // Framework health check.
        'GET up' => 'health check',
        // Local-only tooling (never registered in production).
        'dev.*' => 'local-only style guide',
        'telescope.*' => 'local-only Telescope',
        'boost.*' => 'Laravel Boost (local tooling)',
        'sanctum.*' => 'Sanctum CSRF cookie (not installed)',
    ];
}

function routeIdentifier(RoutingRoute $route): string
{
    return $route->getName() ?? $route->methods()[0].' '.$route->uri();
}

function actor(string $actor): ?User
{
    return match ($actor) {
        'guest' => null,
        'unverified' => User::factory()->student()->unverified()->create(),
        'blocked' => User::factory()->student()->blocked()->create(),
        'student' => User::factory()->student()->create(),
        // 2FA is mandatory for these roles (EnsureTwoFactorEnabled; TwoFactorEnforcementTest).
        'teacher' => User::factory()->teacher()->withTwoFactor()->create(),
        'admin' => User::factory()->admin()->withTwoFactor()->create(),
        default => throw new InvalidArgumentException("Unknown actor [{$actor}]"),
    };
}

/**
 * @return Generator<string, array{string, string}>
 */
function matrixCases(): Generator
{
    foreach (accessMatrix() as $route => $definition) {
        foreach (ACTORS as $actor) {
            yield "{$route} as {$actor}" => [$route, $actor];
        }
    }
}

it('enforces the access matrix', function (string $routeName, string $actorName): void {
    $definition = accessMatrix()[$routeName];
    $user = actor($actorName);

    if ($user !== null) {
        $this->actingAs($user);
    }

    if (isset($definition['from'])) {
        $this->from(route($definition['from']));
    }

    // Mutating requests carry a matching CSRF token, like a real form post.
    $token = 'access-matrix-csrf-token';
    $data = ($definition['data'] ?? []) + ['_token' => $token];

    /** @var TestResponse $response */
    $response = $this->withSession(['_token' => $token])
        ->call($definition['method'], route($routeName, isset($definition['params']) ? ($definition['params'])($user) : []), $definition['method'] === 'GET' ? [] : $data);

    $expected = $definition['expect'][$actorName];

    match (true) {
        is_int($expected) => $response->assertStatus($expected),
        $expected === 'login' => $response->assertRedirect(route('login')),
        $expected === 'verify' => $response->assertRedirect(route('verification.notice')),
        default => $response->assertRedirect(route($expected)),
    };
})->with(matrixCases());

/**
 * @return list<string>
 */
function unclassifiedRoutes(): array
{
    $classified = array_keys(accessMatrix());
    $excluded = array_keys(excludedRoutes());

    return collect(Route::getRoutes()->getRoutes())
        ->map(fn (RoutingRoute $route): string => routeIdentifier($route))
        ->reject(fn (string $id): bool => in_array($id, $classified, true))
        ->reject(fn (string $id): bool => collect($excluded)->contains(fn (string $pattern): bool => Str::is($pattern, $id)))
        ->values()
        ->all();
}

it('classifies every application route', function (): void {
    expect(unclassifiedRoutes())->toBe([], 'Add these routes to the access matrix in '.basename(__FILE__));
});

it('fails when a new route is not classified', function (): void {
    Route::middleware('web')->get('__unclassified-probe', fn () => 'probe')->name('probe.unclassified');
    Route::getRoutes()->refreshNameLookups();

    expect(unclassifiedRoutes())->toBe(['probe.unclassified']);
});

it('only lists routes that exist', function (): void {
    $missing = array_values(array_filter(array_keys(accessMatrix()), fn (string $name): bool => ! Route::has($name)));

    expect($missing)->toBe([]);
});

it('defines an outcome for every actor on every route', function (): void {
    foreach (accessMatrix() as $route => $definition) {
        expect(array_keys($definition['expect']))->toEqualCanonicalizing(ACTORS, "Route [{$route}]");
    }
});

it('renders the dashboard component for each role', function (Role $role, string $component): void {
    $user = User::factory()->withRole($role)->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    [Role::Admin, 'dashboard/admin'],
    [Role::Teacher, 'dashboard/teacher'],
    [Role::Student, 'dashboard/student'],
]);

it('shares exactly the role permissions', function (Role $role): void {
    $user = User::factory()->withRole($role)->create();

    $props = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('page')['props'];

    expect($props['auth']['permissions'])
        ->toEqualCanonicalizing(array_map(fn (Permission $p): string => $p->value, Role::permissions()[$role->value]))
        ->and($props['auth']['user']['role'])->toBe($role->value);
})->with(Role::cases());

it('renders a styled, translated 403 page without details', function (): void {
    $student = User::factory()->student()->create();

    $response = $this->actingAs($student)->get(route('admin.users.index'))->assertForbidden();

    $response->assertInertia(fn ($page) => $page->component('error')->where('status', 403));

    // Neither spatie's exception message nor the required role/permission leaks.
    expect((string) $response->getContent())
        ->not->toContain('does not have the right')
        ->not->toContain('UnauthorizedException')
        ->not->toContain('Spatie')
        ->not->toContain('users.view');
});
