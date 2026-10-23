<?php

declare(strict_types=1);

use App\Contracts\HasLabel;
use App\Enums\Badge;
use App\Enums\QuestionType;

/**
 * @return list<class-string<HasLabel&BackedEnum>>
 */
function labelledEnums(): array
{
    return collect(glob(dirname(__DIR__, 2).'/app/Enums/*.php') ?: [])
        ->map(fn (string $path): string => 'App\\Enums\\'.pathinfo($path, PATHINFO_FILENAME))
        ->filter(fn (string $class): bool => is_subclass_of($class, HasLabel::class))
        ->values()
        ->all();
}

it('translates every enum label into Georgian and English', function (string $enum): void {
    foreach ($enum::cases() as $case) {
        app()->setLocale('en');
        $english = $case->label();

        app()->setLocale('ka');
        $georgian = $case->label();

        expect($english)->not->toBe('')
            ->and($georgian)->not->toBe('')
            ->and($georgian)->not->toBe($english, "{$enum}::{$case->name} has no Georgian translation");
    }
})->with(labelledEnums());

it('has unique labels within each enum', function (string $enum): void {
    app()->setLocale('en');
    $labels = array_map(fn (HasLabel $case): string => $case->label(), $enum::cases());

    expect($labels)->toBe(array_values(array_unique($labels)));
})->with(labelledEnums());

it('auto-grades every question type except free text', function (): void {
    $manual = array_values(array_filter(QuestionType::cases(), fn (QuestionType $type): bool => ! $type->isAutoGradable()));

    expect($manual)->toBe([QuestionType::Text]);
});

it('gives every badge a translated description, an icon and a positive XP reward', function (Badge $badge): void {
    app()->setLocale('ka');
    $georgian = $badge->description();
    app()->setLocale('en');

    expect($badge->description())->not->toBe('')->not->toBe($georgian)
        ->and($badge->icon())->toMatch('/^[a-z0-9]+(-[a-z0-9]+)*$/')
        ->and($badge->xpReward())->toBeGreaterThan(0);
})->with(Badge::cases());

it('uses a distinct icon for every badge', function (): void {
    $icons = array_map(fn (Badge $badge): string => $badge->icon(), Badge::cases());

    expect($icons)->toBe(array_values(array_unique($icons)));
});
