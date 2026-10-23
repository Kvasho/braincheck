<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * XP ledger. users.xp_total is a cache of the sum, maintained only by an Action
 * inside a transaction.
 *
 * Idempotency: unique (user_id, reason, source_type, source_id). NULLs never
 * collide in a unique index, so every award MUST carry a source (e.g. the
 * attempt) to be idempotent; a CHECK keeps source_type/source_id together.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xp_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('amount');
            $table->string('reason', 32);
            // Morph alias (Relation::enforceMorphMap), never a class name.
            $table->nullableMorphs('source');
            $table->dateTime('created_at');

            $table->unique(['user_id', 'reason', 'source_type', 'source_id']);
        });

        $checks = [
            'xp_transactions_amount_non_zero' => 'amount <> 0',
            'xp_transactions_reason_valid' => "reason IN ('attempt_passed', 'perfect_score', 'first_attempt_pass', 'badge_awarded', 'streak')",
            'xp_transactions_source_complete' => '(source_type IS NULL) = (source_id IS NULL)',
        ];

        foreach ($checks as $name => $expression) {
            DB::statement("ALTER TABLE xp_transactions ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('xp_transactions');
    }
};
