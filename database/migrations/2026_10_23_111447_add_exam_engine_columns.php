<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 7 (exam engine):
 * - attempt_answers.flagged: the student's "come back later" marker;
 * - attempts.session_hash: SHA-256 of the session that started the attempt
 *   (a different session later records a `session_changed` event);
 * - attempts.last_seen_at: last autosave;
 * - attempts.event_overflow: per-type counts of anti-cheat events beyond the
 *   per-attempt row cap (counted, not stored as rows);
 * - attempt_events.type gains the server-side `session_changed` (the CHECK
 *   freezes enum values, so a new case needs this migration).
 */
return new class extends Migration
{
    private const string OLD_TYPES = "'tab_hidden', 'window_blur', 'copy', 'paste', 'fullscreen_exit', 'devtools_suspected'";

    public function up(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table): void {
            $table->boolean('flagged')->default(false)->after('answered_at');
        });

        Schema::table('attempts', function (Blueprint $table): void {
            $table->char('session_hash', 64)->nullable()->after('user_agent');
            $table->dateTime('last_seen_at')->nullable()->after('session_hash');
            $table->json('event_overflow')->nullable()->after('last_seen_at');
        });

        DB::statement('ALTER TABLE attempt_events DROP CONSTRAINT attempt_events_type_valid');
        DB::statement('ALTER TABLE attempt_events ADD CONSTRAINT attempt_events_type_valid CHECK (type IN ('.self::OLD_TYPES.", 'session_changed'))");
    }

    public function down(): void
    {
        DB::table('attempt_events')->where('type', 'session_changed')->delete();
        DB::statement('ALTER TABLE attempt_events DROP CONSTRAINT attempt_events_type_valid');
        DB::statement('ALTER TABLE attempt_events ADD CONSTRAINT attempt_events_type_valid CHECK (type IN ('.self::OLD_TYPES.'))');

        Schema::table('attempts', function (Blueprint $table): void {
            $table->dropColumn(['session_hash', 'last_seen_at', 'event_overflow']);
        });

        Schema::table('attempt_answers', function (Blueprint $table): void {
            $table->dropColumn('flagged');
        });
    }
};
