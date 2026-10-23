<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Cache of SUM(xp_transactions.amount); updated only by an Action in a transaction.
            $table->unsignedInteger('xp_total')->default(0)->after('blocked_reason');
            $table->index('xp_total');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['xp_total']);
            $table->dropColumn('xp_total');
        });
    }
};
