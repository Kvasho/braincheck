<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exactly one answer row per attempt question (unique FK), updated by autosave.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_question_id')->unique()->constrained('attempt_questions')->cascadeOnDelete();
            $table->json('answer')->nullable();
            $table->dateTime('answered_at')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('awarded_points', 6, 2)->nullable();
            $table->boolean('needs_manual_grading')->default(false);
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('graded_at')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE attempt_answers ADD CONSTRAINT attempt_answers_awarded_points_non_negative CHECK (awarded_points IS NULL OR awarded_points >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
    }
};
