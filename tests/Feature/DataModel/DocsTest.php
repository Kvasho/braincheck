<?php

declare(strict_types=1);

use App\Enums\QuestionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('documents every database table in docs/erd.md', function (): void {
    $erd = (string) file_get_contents(base_path('docs/erd.md'));

    // Only this database (the server may host other schemas).
    $undocumented = collect(Schema::getTableListing(schema: DB::connection()->getDatabaseName(), schemaQualified: false))
        ->reject(fn (string $table): bool => preg_match('/\b'.preg_quote($table, '/').'\b/', $erd) === 1)
        ->values()
        ->all();

    expect($undocumented)->toBe([], 'Add these tables to docs/erd.md');
});

it('documents every question type in docs/question-types.md', function (QuestionType $type): void {
    expect((string) file_get_contents(base_path('docs/question-types.md')))->toContain("`{$type->value}`");
})->with(QuestionType::cases());
