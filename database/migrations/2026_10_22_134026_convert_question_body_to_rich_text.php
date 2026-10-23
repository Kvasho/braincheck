<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 4: question `body` and `explanation` become rich text (ProseMirror JSON,
 * validated by App\Domain\Shared\RichTextDocument), plus `body_text`: the plain
 * text for search and listings, with a prefix index for `LIKE 'term%'`.
 *
 * Existing plain-text values are wrapped into documents (one paragraph per
 * blank-line-separated block). The conversion is inlined on purpose so this
 * migration never changes when the application code does.
 *
 * Attempt snapshots are history and stay as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->json('body_doc')->nullable()->after('difficulty');
            $table->json('explanation_doc')->nullable()->after('body_doc');
            $table->text('body_text')->nullable()->after('explanation_doc');
        });

        DB::table('questions')->select(['id', 'body', 'explanation'])->orderBy('id')->chunkById(200, function ($questions): void {
            foreach ($questions as $question) {
                $body = (string) $question->body;

                DB::table('questions')->where('id', $question->id)->update([
                    'body_doc' => json_encode($this->document($body), JSON_UNESCAPED_UNICODE),
                    'explanation_doc' => $question->explanation === null || trim((string) $question->explanation) === ''
                        ? null
                        : json_encode($this->document((string) $question->explanation), JSON_UNESCAPED_UNICODE),
                    'body_text' => mb_substr(trim($body), 0, 10_000),
                ]);
            }
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->dropColumn(['body', 'explanation']);
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->renameColumn('body_doc', 'body');
            $table->renameColumn('explanation_doc', 'explanation');
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->json('body')->nullable(false)->change();
            $table->text('body_text')->nullable(false)->change();
        });

        // Prefix index: TEXT columns can only be indexed on a prefix.
        DB::statement('CREATE INDEX questions_body_text_prefix ON questions (body_text(191))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX questions_body_text_prefix ON questions');

        Schema::table('questions', function (Blueprint $table): void {
            $table->text('body_plain')->nullable()->after('difficulty');
            $table->text('explanation_plain')->nullable()->after('body_plain');
        });

        DB::table('questions')->select(['id', 'body_text', 'explanation'])->orderBy('id')->chunkById(200, function ($questions): void {
            foreach ($questions as $question) {
                DB::table('questions')->where('id', $question->id)->update([
                    'body_plain' => $question->body_text,
                    'explanation_plain' => $question->explanation === null ? null : $this->plain((string) $question->explanation),
                ]);
            }
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->dropColumn(['body', 'explanation', 'body_text']);
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->renameColumn('body_plain', 'body');
            $table->renameColumn('explanation_plain', 'explanation');
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->text('body')->nullable(false)->change();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function document(string $text): array
    {
        $content = [];

        foreach (preg_split('/\R{2,}/u', trim($text)) ?: [] as $paragraph) {
            if (trim($paragraph) === '') {
                continue;
            }

            $inline = [];

            foreach (preg_split('/\R/u', $paragraph) ?: [] as $index => $line) {
                if ($index > 0) {
                    $inline[] = ['type' => 'hardBreak'];
                }

                if ($line !== '') {
                    $inline[] = ['type' => 'text', 'text' => $line];
                }
            }

            $content[] = ['type' => 'paragraph', 'content' => $inline];
        }

        return ['type' => 'doc', 'content' => $content];
    }

    private function plain(string $json): string
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return '';
        }

        $text = [];
        array_walk_recursive($decoded, function (mixed $value, string|int $key) use (&$text): void {
            if ($key === 'text' && is_string($value)) {
                $text[] = $value;
            }
        });

        return implode(' ', $text);
    }
};
