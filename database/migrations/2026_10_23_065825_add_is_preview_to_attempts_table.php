<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 5: a teacher's "preview as student" attempt. Preview attempts are
 * excluded from every statistic, leaderboard, XP award, certificate and
 * attempt count (Attempt::real()), never lock a test and are pruned after 24 h.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table): void {
            $table->boolean('is_preview')->default(false)->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table): void {
            $table->dropIndex(['is_preview']);
            $table->dropColumn('is_preview');
        });
    }
};
