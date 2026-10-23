<?php

declare(strict_types=1);

use App\Domain\Questions\Definitions\Definitions;
use App\Enums\AttemptStatus;
use App\Enums\Difficulty;
use App\Enums\GradingStatus;
use App\Enums\QuestionSelectionMode;
use App\Enums\QuestionType;
use App\Enums\Role;
use App\Enums\TeacherRequestStatus;
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
use App\Models\User;
use App\Models\UserBadge;
use App\Models\XpTransaction;
use Illuminate\Database\Eloquent\Model;

it('creates a valid record for every model with its default state', function (string $model): void {
    /** @var class-string<Model> $model */
    $record = $model::factory()->create();

    expect($record->exists)->toBeTrue()
        ->and($model::query()->count())->toBeGreaterThanOrEqual(1);
})->with([
    Category::class, Tag::class, Question::class, Test::class, Group::class, TeacherAccessRequest::class,
    Attempt::class, AttemptQuestion::class, AttemptAnswer::class, AttemptEvent::class, Certificate::class,
    UserBadge::class, XpTransaction::class, AuditLog::class, App\Models\TestQuestionStat::class,
]);

describe('questions', function (): void {
    it('produces a valid definition for every type', function (QuestionType $type): void {
        $question = Question::factory()->ofType($type)->create()->fresh();

        expect($question?->type)->toBe($type)
            ->and($question?->definition)->toBeInstanceOf(Definitions::classFor($type));
    })->with(QuestionType::cases());

    it('has a named state per type and per difficulty', function (): void {
        foreach (['singleChoice', 'multipleChoice', 'trueFalse', 'text', 'numeric', 'ordering', 'matching'] as $state) {
            expect(Question::factory()->{$state}()->create()->exists)->toBeTrue();
        }

        expect(Question::factory()->easy()->create()->difficulty)->toBe(Difficulty::Easy)
            ->and(Question::factory()->medium()->create()->difficulty)->toBe(Difficulty::Medium)
            ->and(Question::factory()->hard()->create()->difficulty)->toBe(Difficulty::Hard);
    });

    it('is owned by a teacher', function (): void {
        expect(Question::factory()->create()->owner()->firstOrFail()->role())->toBe(Role::Teacher);
    });
});

describe('tests', function (): void {
    it('supports every status and configuration state', function (): void {
        expect(Test::factory()->draft()->create()->status)->toBe(TestStatus::Draft)
            ->and(Test::factory()->published()->create()->status)->toBe(TestStatus::Published)
            ->and(Test::factory()->archived()->create()->status)->toBe(TestStatus::Archived)
            ->and(Test::factory()->timed(45)->create()->time_limit_minutes)->toBe(45)
            ->and(Test::factory()->withAttemptLimit(2)->create()->max_attempts)->toBe(2);

        $pool = Test::factory()->randomPool(4)->create();
        expect($pool->selection_mode)->toBe(QuestionSelectionMode::RandomPool)
            ->and($pool->random_question_count)->toBe(4);
    });

    it('builds open, closed and upcoming windows', function (): void {
        $open = Test::factory()->published()->openWindow()->create();
        $closed = Test::factory()->published()->closed()->create();
        $upcoming = Test::factory()->published()->notYetOpen()->create();

        expect(Test::query()->published()->openAt(now())->pluck('id')->all())
            ->toContain($open->id)
            ->not->toContain($closed->id)
            ->not->toContain($upcoming->id);
    });

    it('attaches questions owned by the test owner, in order', function (): void {
        $test = Test::factory()->withQuestions(4)->create();
        $questions = $test->questions()->get();

        expect($questions)->toHaveCount(4)
            ->and($questions->pluck('owner_id')->unique()->all())->toBe([$test->owner_id])
            ->and($questions->map(fn (Question $q): mixed => $q->getRelation('pivot')->getAttribute('position'))->all())->toBe([1, 2, 3, 4]);
    });
});

describe('groups', function (): void {
    it('adds student members and supports invite states', function (): void {
        $group = Group::factory()->withMembers(3)->create();

        expect($group->members()->count())->toBe(3)
            ->and($group->members()->get()->every(fn (User $u): bool => $u->isStudent()))->toBeTrue()
            ->and($group->invite_code)->toMatch('/^[A-HJ-NP-Z2-9]{10}$/')
            ->and(Group::factory()->expiredInvite()->create()->invite_code_expires_at?->isPast())->toBeTrue()
            ->and(Group::factory()->joiningDisabled()->create()->joining_enabled)->toBeFalse();
    });
});

describe('attempts', function (): void {
    it('snapshots every test question with one answer row each', function (): void {
        $attempt = Attempt::factory()->create();
        $questions = $attempt->questions()->with('answer')->get();
        $testQuestions = $attempt->test()->firstOrFail()->questions()->get();

        expect($questions)->toHaveCount($testQuestions->count())
            ->and($questions->pluck('position')->all())->toBe(range(1, $testQuestions->count()))
            ->and($questions->every(fn (AttemptQuestion $q): bool => $q->answer !== null))->toBeTrue();

        $first = $questions->firstOrFail();
        expect($first->snapshot)->toHaveKeys(['type', 'body', 'explanation', 'definition', 'points', 'version'])
            ->and($first->snapshot['definition'])->toBe($testQuestions->firstOrFail()->definition->toArray());
    });

    it('builds consistent in-progress, submitted and timed-out attempts', function (): void {
        $inProgress = Attempt::factory()->inProgress()->create();
        $submitted = Attempt::factory()->submitted()->create();
        $timedOut = Attempt::factory()->timedOut()->create();

        expect($inProgress->status)->toBe(AttemptStatus::InProgress)
            ->and($inProgress->submitted_at)->toBeNull()
            ->and($submitted->status)->toBe(AttemptStatus::Submitted)
            ->and($submitted->questions()->with('answer')->get()->every(fn (AttemptQuestion $q): bool => $q->answer?->answer !== null))->toBeTrue()
            ->and($timedOut->status)->toBe(AttemptStatus::TimedOut)
            ->and($timedOut->submitted_at?->equalTo($timedOut->deadline_at))->toBeTrue();
    });

    it('sets the deadline from the test time limit', function (): void {
        $attempt = Attempt::factory()->for(Test::factory()->published()->timed(30)->withQuestions(2))->create();

        expect($attempt->fresh()?->deadline_at?->equalTo($attempt->started_at->addMinutes(30)))->toBeTrue();
    });

    it('grades consistently', function (bool $passed): void {
        $attempt = Attempt::factory()->graded($passed)->create()->fresh();
        $answers = $attempt?->questions()->with('answer')->get()->map(fn (AttemptQuestion $q): ?AttemptAnswer => $q->answer);

        expect($attempt?->grading_status)->toBe(GradingStatus::Graded)
            ->and($attempt?->passed)->toBe($passed)
            ->and($attempt?->score_percent)->toBe($passed ? '100.00' : '0.00')
            ->and($attempt?->score)->toBe($passed ? $attempt?->max_score : '0.00')
            ->and($answers?->every(fn (?AttemptAnswer $a): bool => $a?->is_correct === $passed))->toBeTrue();
    })->with([true, false]);

    it('grades with a chosen number of correct answers', function (): void {
        $attempt = Attempt::factory()
            ->for(Test::factory()->published()->withQuestions(4))
            ->gradedWith(3)
            ->create()
            ->fresh();

        expect((float) $attempt?->score_percent)->toBeGreaterThan(0.0)->toBeLessThan(100.0)
            ->and($attempt?->questions()->with('answer')->get()->filter(fn (AttemptQuestion $q): bool => $q->answer?->is_correct === true))->toHaveCount(3);
    });
});

describe('other factories', function (): void {
    it('builds certificates from passed attempts, optionally revoked', function (): void {
        $certificate = Certificate::factory()->create();
        $attempt = $certificate->attempt()->firstOrFail();

        expect($attempt->passed)->toBeTrue()
            ->and($certificate->user_id)->toBe($attempt->user_id)
            ->and($certificate->test_id)->toBe($attempt->test_id)
            ->and($certificate->verification_code)->toHaveLength(32)
            ->and(Certificate::factory()->revoked()->create()->isRevoked())->toBeTrue();
    });

    it('builds reviewed teacher requests', function (): void {
        expect(TeacherAccessRequest::factory()->approved()->create()->status)->toBe(TeacherRequestStatus::Approved)
            ->and(TeacherAccessRequest::factory()->rejected()->create()->reviewed_at)->not->toBeNull();
    });

    it('records xp and audit sources as morph aliases', function (): void {
        $attempt = Attempt::factory()->create();

        $xp = XpTransaction::factory()->forSource($attempt)->create();
        $log = AuditLog::factory()->about($attempt->user()->firstOrFail())->create();

        expect($xp->source_type)->toBe('attempt')
            ->and($log->subject_type)->toBe('user');
    });
});
