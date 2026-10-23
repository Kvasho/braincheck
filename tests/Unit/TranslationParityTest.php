<?php

declare(strict_types=1);

use App\Enums\Locale;

/**
 * @return array<string, mixed>
 */
function jsonTranslations(Locale $locale): array
{
    $path = dirname(__DIR__, 2)."/lang/{$locale->value}.json";

    return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

it('has the same keys in en.json and ka.json', function (): void {
    $english = jsonTranslations(Locale::English);
    $georgian = jsonTranslations(Locale::Georgian);

    expect(array_keys(array_diff_key($english, $georgian)))->toBe([], 'Missing in ka.json')
        ->and(array_keys(array_diff_key($georgian, $english)))->toBe([], 'Missing in en.json');
});

it('has no empty translations', function (Locale $locale): void {
    $empty = array_keys(array_filter(jsonTranslations($locale), fn (mixed $value): bool => ! is_string($value) || trim($value) === ''));

    expect($empty)->toBe([]);
})->with(Locale::cases());

it('ships framework translation files for every locale', function (): void {
    $files = fn (Locale $locale): array => array_map(
        'basename',
        glob(dirname(__DIR__, 2)."/lang/{$locale->value}/*.php") ?: [],
    );

    expect($files(Locale::Georgian))->toBe($files(Locale::English))->not->toBeEmpty();
});
