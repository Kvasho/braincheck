<?php

declare(strict_types=1);

use App\Actions\Grading\GradeAttempt;
use App\Domain\Questions\Definitions\Item;
use App\Domain\Questions\Definitions\MatchingDefinition;
use App\Domain\Questions\Definitions\MultipleChoiceDefinition;
use App\Domain\Questions\Definitions\NumericDefinition;
use App\Domain\Questions\Definitions\OrderingDefinition;
use App\Domain\Questions\Definitions\SingleChoiceDefinition;
use App\Domain\Questions\Definitions\TextDefinition;
use App\Domain\Questions\Definitions\TrueFalseDefinition;
use App\Enums\GradingStatus;
use App\Enums\MultipleChoiceScoring;
use App\Events\AttemptGraded;
use App\Models\AttemptAnswer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\GradingFixtures;

/**
 * @return list<Item>
 */
function items(int $count): array
{
    return array_map(fn (int $i): Item => Item::make("Item {$i}"), range(1, $count));
}

describe('every type, through GradeAttempt', function (): void {
    it('grades single choice: full, wrong, empty', function (): void {
        [$a, $b] = items(2);
        $definition = new SingleChoiceDefinition([$a, $b], $a->id);

        expect(GradingFixtures::gradedAnswer($definition, '2.00', ['selected' => $a->id]))->awarded_points->toBe('2.00')->is_correct->toBeTrue()
            ->and(GradingFixtures::gradedAnswer($definition, '2.00', ['selected' => $b->id]))->awarded_points->toBe('0.00')->is_correct->toBeFalse()
            ->and(GradingFixtures::gradedAnswer($definition, '2.00', null))->awarded_points->toBe('0.00')->is_correct->toBeFalse();
    });

    it('grades multiple choice with partial credit and with all-or-nothing', function (): void {
        [$a, $b, $c, $d] = items(4);
        $partial = new MultipleChoiceDefinition([$a, $b, $c, $d], [$a->id, $b->id, $c->id], MultipleChoiceScoring::Partial);
        $strict = new MultipleChoiceDefinition([$a, $b, $c, $d], [$a->id, $b->id, $c->id], MultipleChoiceScoring::AllOrNothing);

        expect(GradingFixtures::gradedAnswer($partial, '1.00', ['selected' => [$a->id, $b->id, $c->id]])->awarded_points)->toBe('1.00')
            // ⅓ of a point: 0.333… → 0.33, ⅔ of a point: 0.666… → 0.67 (half up).
            ->and(GradingFixtures::gradedAnswer($partial, '1.00', ['selected' => [$a->id]]))->awarded_points->toBe('0.33')->is_correct->toBeFalse()
            ->and(GradingFixtures::gradedAnswer($partial, '1.00', ['selected' => [$a->id, $b->id]])->awarded_points)->toBe('0.67')
            // A wrong choice cancels a right one; never below zero.
            ->and(GradingFixtures::gradedAnswer($partial, '1.00', ['selected' => [$a->id, $d->id]])->awarded_points)->toBe('0.00')
            ->and(GradingFixtures::gradedAnswer($partial, '1.00', ['selected' => [$d->id]])->awarded_points)->toBe('0.00')
            ->and(GradingFixtures::gradedAnswer($strict, '1.00', ['selected' => [$a->id, $b->id]])->awarded_points)->toBe('0.00')
            ->and(GradingFixtures::gradedAnswer($strict, '1.00', ['selected' => []])->awarded_points)->toBe('0.00');
    });

    it('grades true/false and numeric with tolerance', function (): void {
        $truth = new TrueFalseDefinition(true);
        $numeric = new NumericDefinition('9.81', '0.05', 'm/s²');

        expect(GradingFixtures::gradedAnswer($truth, '1.00', ['value' => true])->awarded_points)->toBe('1.00')
            ->and(GradingFixtures::gradedAnswer($truth, '1.00', ['value' => false])->awarded_points)->toBe('0.00')
            ->and(GradingFixtures::gradedAnswer($truth, '1.00', ['value' => null])->awarded_points)->toBe('0.00')
            ->and(GradingFixtures::gradedAnswer($numeric, '3.00', ['value' => '9.86'])->awarded_points)->toBe('3.00')
            ->and(GradingFixtures::gradedAnswer($numeric, '3.00', ['value' => '9.87'])->awarded_points)->toBe('0.00')
            ->and(GradingFixtures::gradedAnswer($numeric, '3.00', null)->awarded_points)->toBe('0.00');
    });

    it('grades ordering and matching with fractions of the points', function (): void {
        [$a, $b, $c] = items(3);
        $order = new OrderingDefinition([$c, $a, $b], [$a->id, $b->id, $c->id], MultipleChoiceScoring::Partial);
        [$l1, $l2, $l3] = items(3);
        [$r1, $r2, $r3, $r4] = items(4);
        $matching = new MatchingDefinition([$l1, $l2, $l3], [$r1, $r2, $r3, $r4], [
            ['left' => $l1->id, 'right' => $r1->id],
            ['left' => $l2->id, 'right' => $r2->id],
            ['left' => $l3->id, 'right' => $r3->id],
        ]);

        expect(GradingFixtures::gradedAnswer($order, '2.00', ['order' => [$a->id, $b->id, $c->id]])->awarded_points)->toBe('2.00')
            ->and(GradingFixtures::gradedAnswer($order, '2.00', ['order' => [$a->id, $c->id, $b->id]])->awarded_points)->toBe('0.67')
            ->and(GradingFixtures::gradedAnswer($order, '2.00', ['order' => [$c->id, $a->id, $b->id]])->awarded_points)->toBe('0.00')
            ->and(GradingFixtures::gradedAnswer($matching, '1.00', ['pairs' => [['left' => $l1->id, 'right' => $r1->id]]])->awarded_points)->toBe('0.33')
            ->and(GradingFixtures::gradedAnswer($matching, '1.00', ['pairs' => [['left' => $l1->id, 'right' => $r1->id], ['left' => $l2->id, 'right' => $r2->id], ['left' => $l3->id, 'right' => $r3->id]]])->awarded_points)->toBe('1.00')
            ->and(GradingFixtures::gradedAnswer($matching, '1.00', ['pairs' => []])->awarded_points)->toBe('0.00');
    });

    it('leaves free text for the teacher', function (): void {
        $answer = GradingFixtures::gradedAnswer(new TextDefinition(500, 'Photosynthesis'), '5.00', ['text' => 'Plants make sugar.']);

        expect($answer->needs_manual_grading)->toBeTrue()
            ->and($answer->is_correct)->toBeNull()
            ->and($answer->awarded_points)->toBe('0.00')
            ->and($answer->graded_at)->toBeNull();
    });
});

describe('totals and pass mark', function (): void {
    it('passes on exact equality and fails 0.01 below', function (string $correctPoints, string $otherPoints, string $percent, bool $passed): void {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        [$a, $b] = items(2);
        $test = GradingFixtures::test($teacher, $student, [
            [new SingleChoiceDefinition([$a, $b], $a->id), $correctPoints],
            [new SingleChoiceDefinition([$a, $b], $a->id), $otherPoints],
        ], ['pass_percent' => 50]);

        $attempt = GradingFixtures::submitted($test, $student, [1 => ['selected' => $a->id], 2 => ['selected' => $b->id]]);

        expect($attempt->grading_status)->toBe(GradingStatus::Graded)
            ->and($attempt->score_percent)->toBe($percent)
            ->and($attempt->passed)->toBe($passed)
            ->and($attempt->max_score)->toBe('100.00')
            ->and($attempt->graded_at)->not->toBeNull();
    })->with([
        'exactly 50 %' => ['50.00', '50.00', '50.00', true],
        '0.01 below' => ['49.99', '50.01', '49.99', false],
    ]);

    it('rounds the percentage half up to 2 decimals', function (): void {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        [$a, $b] = items(2);
        $test = GradingFixtures::test($teacher, $student, array_fill(0, 3, [new SingleChoiceDefinition([$a, $b], $a->id), '1.00']));

        // 2 of 3 points: 66.666… % → 66.67 %.
        $attempt = GradingFixtures::submitted($test, $student, [1 => ['selected' => $a->id], 2 => ['selected' => $a->id], 3 => null]);

        expect($attempt->score)->toBe('2.00')->and($attempt->score_percent)->toBe('66.67');
    });
});

describe('mixed attempts and events', function (): void {
    it('stays pending with a text question; after manual grading it is graded with correct totals', function (): void {
        Event::fake([AttemptGraded::class]);
        $teacher = User::factory()->teacher()->withTwoFactor()->create();
        $student = User::factory()->student()->create();
        [$a, $b] = items(2);
        $test = GradingFixtures::test($teacher, $student, [
            [new SingleChoiceDefinition([$a, $b], $a->id), '2.00'],
            [new TextDefinition(500, null), '3.00'],
        ], ['pass_percent' => 60]);

        $attempt = GradingFixtures::submitted($test, $student, [1 => ['selected' => $a->id], 2 => ['text' => 'An essay']]);

        expect($attempt->grading_status)->toBe(GradingStatus::PendingManual)
            ->and($attempt->score)->toBe('2.00')
            ->and($attempt->passed)->toBeNull()
            ->and($attempt->graded_at)->toBeNull();
        Event::assertNotDispatched(AttemptGraded::class);

        $version = AttemptAnswer::query()->where('needs_manual_grading', true)->sole()->gradingVersion();
        $this->actingAs($teacher)->put(route('teacher.grading.answers.update', [$attempt, 2]), ['points' => '1.50', 'version' => $version])->assertSessionHasNoErrors();

        $attempt->refresh();
        expect($attempt->grading_status)->toBe(GradingStatus::Graded)
            ->and($attempt->score)->toBe('3.50')
            ->and($attempt->score_percent)->toBe('70.00')
            ->and($attempt->passed)->toBeTrue();
        Event::assertDispatchedTimes(AttemptGraded::class, 1);
    });

    it('fires AttemptGraded exactly once and only after commit', function (): void {
        Event::fake([AttemptGraded::class]);
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        [$a, $b] = items(2);
        $test = GradingFixtures::test($teacher, $student, [[new SingleChoiceDefinition([$a, $b], $a->id), '1.00']]);

        DB::transaction(function () use ($test, $student, $a): void {
            GradingFixtures::submitted($test, $student, [1 => ['selected' => $a->id]]);
            Event::assertNotDispatched(AttemptGraded::class);
        });

        Event::assertDispatchedTimes(AttemptGraded::class, 1);
    });

    it('is idempotent: grading again changes nothing and fires nothing', function (): void {
        Event::fake([AttemptGraded::class]);
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        [$a, $b] = items(2);
        $test = GradingFixtures::test($teacher, $student, [[new SingleChoiceDefinition([$a, $b], $a->id), '1.00']]);
        $attempt = GradingFixtures::submitted($test, $student, [1 => ['selected' => $a->id]]);
        $before = $attempt->fresh()?->getAttributes();

        // Even with a changed answer row, an already graded attempt is not regraded.
        AttemptAnswer::query()->update(['awarded_points' => '0.00']);
        app(GradeAttempt::class)->handle($attempt);
        app(GradeAttempt::class)->handle($attempt);

        expect($attempt->fresh()?->getAttributes())->toBe($before);
        Event::assertDispatchedTimes(AttemptGraded::class, 1);
    });
});
