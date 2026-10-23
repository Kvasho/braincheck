<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Request teacher access" submissions (flow: Stage 3).
 *
 * One pending request per user: `pending_lock` is 1 while pending and NULL
 * otherwise; the unique (user_id, pending_lock) index allows any number of
 * reviewed requests (NULLs never collide) but only one pending.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_access_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Max length enforced in the application.
            $table->text('message');
            $table->string('status', 16)->default('pending');
            $table->unsignedTinyInteger('pending_lock')
                ->nullable()
                ->storedAs("CASE WHEN status = 'pending' THEN 1 ELSE NULL END");
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'pending_lock']);
        });

        DB::statement("ALTER TABLE teacher_access_requests ADD CONSTRAINT teacher_access_requests_status_valid CHECK (status IN ('pending', 'approved', 'rejected'))");
        // A request is reviewed (has reviewed_at) exactly when it is no longer pending.
        DB::statement(<<<'SQL'
            ALTER TABLE teacher_access_requests ADD CONSTRAINT teacher_access_requests_review_consistent CHECK (
                (status = 'pending' AND reviewed_at IS NULL) OR (status <> 'pending' AND reviewed_at IS NOT NULL)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_access_requests');
    }
};
