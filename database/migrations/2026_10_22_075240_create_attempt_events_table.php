<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anti-cheat log: client-reported events, timestamped with server time.
 * Append-only; `meta` size is limited in the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('attempts')->cascadeOnDelete();
            $table->string('type', 32);
            $table->dateTime('occurred_at');
            $table->json('meta')->nullable();

            $table->index(['attempt_id', 'occurred_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE attempt_events ADD CONSTRAINT attempt_events_type_valid CHECK (
                type IN ('tab_hidden', 'window_blur', 'copy', 'paste', 'fullscreen_exit', 'devtools_suspected')
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_events');
    }
};
