<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The questions of an attempt, frozen at start time.
 *
 * `snapshot` (secret, $hidden): the question's type, body, explanation,
 * definition, points and version at start, with option order already shuffled
 * when enabled. Grading and review always read the snapshot, never the live question.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('attempts')->cascadeOnDelete();
            // SET NULL: history survives even if the question is somehow removed.
            $table->foreignId('question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->unsignedSmallInteger('position');
            $table->json('snapshot');
            $table->decimal('points', 6, 2);

            $table->unique(['attempt_id', 'position']);
            $table->unique(['attempt_id', 'question_id']);
        });

        DB::statement('ALTER TABLE attempt_questions ADD CONSTRAINT attempt_questions_points_positive CHECK (points > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_questions');
    }
};
