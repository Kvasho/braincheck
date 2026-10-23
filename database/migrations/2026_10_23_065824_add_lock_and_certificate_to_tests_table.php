<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 5:
 * - `issues_certificate`: passing grants a certificate (Stage 10).
 * - `locked_at`: set when the first *real* attempt starts (Stage 7). From then
 *   on the grading-relevant settings and the questions are immutable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table): void {
            $table->boolean('issues_certificate')->default(false)->after('show_correct_answers');
            $table->dateTime('locked_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table): void {
            $table->dropColumn(['issues_certificate', 'locked_at']);
        });
    }
};
