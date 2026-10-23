<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Awarded badges. The (user_id, badge) primary key makes awarding the same
 * badge twice impossible (idempotent award logic, Stage 11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_badges', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('badge', 32);
            $table->dateTime('awarded_at');

            $table->primary(['user_id', 'badge']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE user_badges ADD CONSTRAINT user_badges_badge_valid CHECK (
                badge IN ('first_test', 'perfect_score', 'ten_tests', 'speed_runner', 'comeback', 'streak_7', 'top_10_group', 'all_types')
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
    }
};
