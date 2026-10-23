<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Question bank. `definition` holds the type-specific content including the
 * correct answer (secret; see docs/question-types.md).
 *
 * Allowed `type` / `difficulty` values are frozen here on purpose: a new
 * question type needs its own migration that updates the CHECK constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('type', 32)->index();
            $table->string('difficulty', 16)->index();
            // Rich text, sanitized with Purifier on write (Stage 4).
            $table->text('body');
            $table->text('explanation')->nullable();
            $table->json('definition');
            $table->decimal('points', 6, 2)->default(1);
            // Incremented on content edits; snapshotted into attempts.
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'deleted_at']);
        });

        DB::statement('ALTER TABLE questions ADD CONSTRAINT questions_points_positive CHECK (points > 0)');
        DB::statement('ALTER TABLE questions ADD CONSTRAINT questions_version_positive CHECK (version >= 1)');
        DB::statement(<<<'SQL'
            ALTER TABLE questions ADD CONSTRAINT questions_type_valid CHECK (
                type IN ('single_choice', 'multiple_choice', 'true_false', 'text', 'numeric', 'ordering', 'matching')
            )
            SQL);
        DB::statement("ALTER TABLE questions ADD CONSTRAINT questions_difficulty_valid CHECK (difficulty IN ('easy', 'medium', 'hard'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
