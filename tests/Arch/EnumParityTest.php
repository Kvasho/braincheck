<?php

declare(strict_types=1);

use App\Enums\AntiCheatEvent;
use App\Enums\AttemptStatus;
use App\Enums\AuditAction;
use App\Enums\Badge;
use App\Enums\Difficulty;
use App\Enums\GradingStatus;
use App\Enums\InviteExpiry;
use App\Enums\MultipleChoiceScoring;
use App\Enums\Permission;
use App\Enums\QuestionSelectionMode;
use App\Enums\QuestionType;
use App\Enums\ResultsVisibility;
use App\Enums\Role;
use App\Enums\TeacherRequestStatus;
use App\Enums\TestStatus;
use App\Enums\XpReason;

/**
 * Values of a `export const NAME = [...] as const;` array in a TS types file.
 *
 * @return list<string>
 */
function tsConstArray(string $file, string $name): array
{
    $path = dirname(__DIR__, 2).'/resources/js/types/'.$file;
    $source = (string) file_get_contents($path);

    if (preg_match('/export const '.$name.' = \[(.*?)\] as const;/s', $source, $match) !== 1) {
        throw new RuntimeException("Array [{$name}] not found in resources/js/types/{$file}");
    }

    preg_match_all("/'([^']+)'/", $match[1], $values);

    return $values[1];
}

/**
 * PHP enum ↔ TypeScript `as const` array it is mirrored by.
 *
 * @return array<string, array{class-string<BackedEnum>, string, string}>
 */
function mirroredEnums(): array
{
    return [
        'Role' => [Role::class, 'authorization.ts', 'ROLES'],
        'Permission' => [Permission::class, 'authorization.ts', 'PERMISSIONS'],
        'QuestionType' => [QuestionType::class, 'enums.ts', 'QUESTION_TYPES'],
        'MultipleChoiceScoring' => [MultipleChoiceScoring::class, 'enums.ts', 'MULTIPLE_CHOICE_SCORINGS'],
        'Difficulty' => [Difficulty::class, 'enums.ts', 'DIFFICULTIES'],
        'TestStatus' => [TestStatus::class, 'enums.ts', 'TEST_STATUSES'],
        'QuestionSelectionMode' => [QuestionSelectionMode::class, 'enums.ts', 'QUESTION_SELECTION_MODES'],
        'ResultsVisibility' => [ResultsVisibility::class, 'enums.ts', 'RESULTS_VISIBILITIES'],
        'AttemptStatus' => [AttemptStatus::class, 'enums.ts', 'ATTEMPT_STATUSES'],
        'GradingStatus' => [GradingStatus::class, 'enums.ts', 'GRADING_STATUSES'],
        'AntiCheatEvent' => [AntiCheatEvent::class, 'enums.ts', 'ANTI_CHEAT_EVENTS'],
        'XpReason' => [XpReason::class, 'enums.ts', 'XP_REASONS'],
        'Badge' => [Badge::class, 'enums.ts', 'BADGES'],
        'TeacherRequestStatus' => [TeacherRequestStatus::class, 'enums.ts', 'TEACHER_REQUEST_STATUSES'],
        'AuditAction' => [AuditAction::class, 'enums.ts', 'AUDIT_ACTIONS'],
        'InviteExpiry' => [InviteExpiry::class, 'enums.ts', 'INVITE_EXPIRIES'],
    ];
}

it('keeps the TypeScript mirror in sync with the PHP enum', function (string $enum, string $file, string $const): void {
    expect(tsConstArray($file, $const))
        ->toBe(array_map(fn (BackedEnum $case): string => (string) $case->value, $enum::cases()));
})->with(mirroredEnums());

it('has exactly one frontend module per question type (PHP enum = TS enum = registry)', function (): void {
    $root = dirname(__DIR__, 2).'/resources/js/question-types';
    $registry = (string) file_get_contents("{$root}/registry.ts");

    preg_match('/export const questionTypes[^=]*= \{(.*)
\};/s', $registry, $body);
    preg_match_all('/^    (\w+): \{$/m', $body[1] ?? '', $keys);

    $types = array_map(fn (QuestionType $type): string => $type->value, QuestionType::cases());

    expect($keys[1])->toBe($types)
        ->and(tsConstArray('enums.ts', 'QUESTION_TYPES'))->toBe($types);

    foreach ($types as $type) {
        foreach (['Editor.tsx', 'Player.tsx', 'Review.tsx', 'types.ts'] as $file) {
            expect(is_file("{$root}/{$type}/{$file}"))->toBeTrue("Missing question-types/{$type}/{$file}");
        }
    }
});

it('mirrors the rich text allowlist', function (): void {
    expect(tsConstArray('rich-text.ts', 'RICH_TEXT_NODES'))->toBe(App\Domain\Shared\RichTextDocument::NODES)
        ->and(tsConstArray('rich-text.ts', 'RICH_TEXT_MARKS'))->toBe(App\Domain\Shared\RichTextDocument::MARKS);

    // The schema table covers exactly the allowed nodes.
    $children = (new ReflectionClassConstant(App\Domain\Shared\RichTextDocument::class, 'CHILDREN'))->getValue();
    expect(array_keys($children))->toEqualCanonicalizing(App\Domain\Shared\RichTextDocument::NODES);
});

it('mirrors every string-backed enum used by the frontend', function (): void {
    $mirrored = array_map(fn (array $definition): string => $definition[0], mirroredEnums());

    // Locale is mirrored by AppLocale in use-translation.ts and shared via Locale::options().
    $notMirrored = [App\Enums\Locale::class];

    $all = collect(glob(dirname(__DIR__, 2).'/app/Enums/*.php') ?: [])
        ->map(fn (string $path): string => 'App\\Enums\\'.pathinfo($path, PATHINFO_FILENAME))
        ->reject(fn (string $class): bool => in_array($class, $notMirrored, true))
        ->values()
        ->all();

    expect(array_diff($all, array_values($mirrored)))->toBe([]);
});
