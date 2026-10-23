<?php

declare(strict_types=1);

use App\Enums\QuestionType;
use App\Models\Attempt;
use App\Models\Category;
use App\Models\Group;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use Database\Seeders\DemoContentSeeder;

it('refuses to run DemoContentSeeder in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    // Invoke directly: `db:seed` itself would first ask for confirmation in production.
    expect(fn () => app(DemoContentSeeder::class)->setContainer(app())->__invoke())
        ->toThrow(RuntimeException::class, 'only runs in the local or testing environment');

    expect(Question::query()->count())->toBe(0);
});

it('seeds realistic demo content cleanly in testing', function (): void {
    $this->seed(DemoContentSeeder::class);

    expect(User::query()->count())->toBe(8)
        ->and(Category::query()->whereNull('parent_id')->count())->toBe(6)
        ->and(Question::query()->count())->toBeGreaterThanOrEqual(60)
        ->and(Test::query()->count())->toBe(8)
        ->and(Group::query()->count())->toBe(3)
        ->and(Attempt::query()->whereNotNull('score_percent')->distinct()->count('score_percent'))->toBeGreaterThan(5);

    foreach (QuestionType::cases() as $type) {
        foreach (['easy', 'medium', 'hard'] as $difficulty) {
            expect(Question::query()->where('type', $type)->where('difficulty', $difficulty)->exists())
                ->toBeTrue("No {$difficulty} {$type->value} question");
        }
    }

    // Every question was owned by a demo teacher and has a valid definition.
    $questions = Question::query()->with('owner')->get();
    expect($questions->every(fn (Question $q): bool => $q->owner->isTeacher()))->toBeTrue();
    $questions->each(fn (Question $q) => $q->definition);
});

it('can run again without duplicating demo content', function (): void {
    $this->seed(DemoContentSeeder::class);
    $counts = [Question::query()->count(), Test::query()->count(), Attempt::query()->count()];

    $this->seed(DemoContentSeeder::class);

    expect([Question::query()->count(), Test::query()->count(), Attempt::query()->count()])->toBe($counts);
});
