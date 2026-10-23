<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 9 (results & analytics):
 * - tests.results_released_at: the owner released the results of a `manual`
 *   test (can't be un-released);
 * - test_question_stats: precomputed per-question analytics of a test's real
 *   graded attempts (disposable: recomputed by RecomputeTestQuestionStats).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table): void {
            $table->dateTime('results_released_at')->nullable()->after('locked_at');
        });

        Schema::create('test_question_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('test_id')->constrained('tests')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('excerpt');
            $table->unsignedInteger('responses')->default(0);
            // Shares (0…1) and D (−1…1) with 4 decimals; null when there is no data.
            $table->decimal('difficulty', 5, 4)->nullable();
            $table->decimal('discrimination', 5, 4)->nullable();
            $table->decimal('average_points', 8, 2)->nullable();
            $table->unsignedInteger('manual_grades')->default(0);
            $table->boolean('needs_review')->default(false);
            // Choice types: per option {id, text, correct, picks, upper, lower, flags}.
            $table->json('distractors')->nullable();
            $table->dateTime('computed_at');

            $table->unique(['test_id', 'question_id']);
            $table->index(['test_id', 'needs_review']);
        });

        $checks = [
            'test_question_stats_difficulty_range' => 'difficulty IS NULL OR difficulty BETWEEN 0 AND 1',
            'test_question_stats_discrimination_range' => 'discrimination IS NULL OR discrimination BETWEEN -1 AND 1',
            'test_question_stats_average_points_non_negative' => 'average_points IS NULL OR average_points >= 0',
        ];

        foreach ($checks as $name => $expression) {
            DB::statement("ALTER TABLE test_question_stats ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('test_question_stats');

        Schema::table('tests', function (Blueprint $table): void {
            $table->dropColumn('results_released_at');
        });
    }
};
