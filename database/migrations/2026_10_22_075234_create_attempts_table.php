<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exam attempts: immutable history. Questions are snapshotted into
 * attempt_questions at start, so later edits never change past attempts.
 *
 * `deadline_at` is computed server-side only (null when untimed).
 * One in-progress attempt per user per test: `active_lock` is 1 while
 * in_progress and NULL otherwise; the unique (test_id, user_id, active_lock)
 * index works like a partial unique index (NULLs never collide).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('test_id')->constrained('tests')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('attempt_number');
            $table->string('status', 16)->default('in_progress');
            $table->string('grading_status', 16)->default('pending_auto');
            $table->unsignedTinyInteger('active_lock')
                ->nullable()
                ->storedAs("CASE WHEN status = 'in_progress' THEN 1 ELSE NULL END");
            $table->dateTime('started_at');
            $table->dateTime('deadline_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('graded_at')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('max_score', 8, 2)->nullable();
            $table->decimal('score_percent', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->string('ip_address', 45)->nullable();
            // Truncated to 255 characters in the application.
            $table->string('user_agent')->nullable();
            $table->timestamps();

            // Prevents duplicate attempts under race conditions.
            $table->unique(['test_id', 'user_id', 'attempt_number']);
            $table->unique(['test_id', 'user_id', 'active_lock']);
            // Auto-finish job: in-progress attempts past their deadline.
            $table->index(['status', 'deadline_at']);
            $table->index(['user_id', 'created_at']);
        });

        $checks = [
            'attempts_number_positive' => 'attempt_number >= 1',
            'attempts_status_valid' => "status IN ('in_progress', 'submitted', 'timed_out')",
            'attempts_grading_status_valid' => "grading_status IN ('pending_auto', 'pending_manual', 'graded')",
            'attempts_deadline_after_start' => 'deadline_at IS NULL OR deadline_at > started_at',
            // Finished attempts have a submission time; attempts in progress don't.
            'attempts_submission_consistent' => "(status = 'in_progress' AND submitted_at IS NULL) OR (status <> 'in_progress' AND submitted_at IS NOT NULL)",
            'attempts_graded_has_time' => "grading_status <> 'graded' OR graded_at IS NOT NULL",
            'attempts_score_non_negative' => 'score IS NULL OR score >= 0',
            'attempts_max_score_non_negative' => 'max_score IS NULL OR max_score >= 0',
            'attempts_score_within_max' => 'score IS NULL OR max_score IS NULL OR score <= max_score',
            'attempts_score_percent_range' => 'score_percent IS NULL OR score_percent BETWEEN 0 AND 100',
        ];

        foreach ($checks as $name => $expression) {
            DB::statement("ALTER TABLE attempts ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attempts');
    }
};
