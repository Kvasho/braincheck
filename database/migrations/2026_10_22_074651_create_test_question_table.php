<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_question', function (Blueprint $table): void {
            $table->foreignId('test_id')->constrained('tests')->cascadeOnDelete();
            // RESTRICT: a question used in a test can't be hard-deleted.
            $table->foreignId('question_id')->constrained('questions')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->decimal('points_override', 6, 2)->nullable();

            $table->primary(['test_id', 'question_id']);
            $table->unique(['test_id', 'position']);
        });

        DB::statement('ALTER TABLE test_question ADD CONSTRAINT test_question_points_override_positive CHECK (points_override IS NULL OR points_override > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('test_question');
    }
};
