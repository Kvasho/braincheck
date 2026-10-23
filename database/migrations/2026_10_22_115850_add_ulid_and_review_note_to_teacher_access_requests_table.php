<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Teacher access request review (Stage 3): a public identifier for the admin
 * approve/reject routes, and the reviewer's optional note (max length enforced
 * in the application). A note only exists on a reviewed request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_access_requests', function (Blueprint $table): void {
            $table->ulid('ulid')->nullable()->after('id');
            $table->text('review_note')->nullable()->after('reviewed_at');
        });

        DB::table('teacher_access_requests')->whereNull('ulid')->orderBy('id')->chunkById(500, function ($requests): void {
            foreach ($requests as $request) {
                DB::table('teacher_access_requests')->where('id', $request->id)->update(['ulid' => (string) Str::ulid()]);
            }
        });

        Schema::table('teacher_access_requests', function (Blueprint $table): void {
            $table->ulid('ulid')->nullable(false)->change();
            $table->unique('ulid');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE teacher_access_requests ADD CONSTRAINT teacher_access_requests_note_reviewed CHECK (
                review_note IS NULL OR status <> 'pending'
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE teacher_access_requests DROP CONSTRAINT teacher_access_requests_note_reviewed');

        Schema::table('teacher_access_requests', function (Blueprint $table): void {
            $table->dropUnique(['ulid']);
            $table->dropColumn(['ulid', 'review_note']);
        });
    }
};
