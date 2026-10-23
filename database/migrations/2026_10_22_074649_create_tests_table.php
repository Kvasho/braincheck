<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tests (exams) built by teachers from their questions.
 *
 * Note (MySQL 8): CHECK constraints may not reference columns used in FK
 * referential actions, so none of the checks below touch owner_id/category_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tests', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('draft');
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            // null = unlimited attempts.
            $table->unsignedTinyInteger('max_attempts')->nullable();
            $table->unsignedTinyInteger('pass_percent');
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->string('selection_mode', 16)->default('fixed');
            $table->unsignedSmallInteger('random_question_count')->nullable();
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
            $table->string('results_visibility', 16)->default('immediately');
            $table->boolean('show_correct_answers')->default(false);
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'status']);
            $table->index(['status', 'opens_at', 'closes_at']);
        });

        $checks = [
            'tests_status_valid' => "status IN ('draft', 'published', 'archived')",
            'tests_time_limit_range' => 'time_limit_minutes IS NULL OR time_limit_minutes BETWEEN 1 AND 600',
            'tests_max_attempts_positive' => 'max_attempts IS NULL OR max_attempts >= 1',
            'tests_pass_percent_range' => 'pass_percent BETWEEN 0 AND 100',
            'tests_selection_mode_valid' => "selection_mode IN ('fixed', 'random_pool')",
            'tests_random_count_positive' => 'random_question_count IS NULL OR random_question_count >= 1',
            // A random pool must say how many questions to draw.
            'tests_random_pool_needs_count' => "selection_mode <> 'random_pool' OR random_question_count IS NOT NULL",
            'tests_window_order' => 'opens_at IS NULL OR closes_at IS NULL OR closes_at > opens_at',
            'tests_results_visibility_valid' => "results_visibility IN ('immediately', 'after_close', 'manual', 'never')",
        ];

        foreach ($checks as $name => $expression) {
            DB::statement("ALTER TABLE tests ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tests');
    }
};
