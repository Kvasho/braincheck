<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Certificates for passed attempts. One per attempt (unique attempt_id).
 * holder_name, test_title and score_percent are snapshots taken at issue time,
 * so later renames never change an issued certificate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('attempt_id')->unique()->constrained('attempts')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('test_id')->constrained('tests')->restrictOnDelete();
            // Public verification code: 32 characters from a CSPRNG.
            $table->char('verification_code', 32)->unique();
            $table->string('holder_name');
            $table->string('test_title');
            $table->decimal('score_percent', 5, 2);
            $table->dateTime('issued_at');
            $table->dateTime('revoked_at')->nullable();
            $table->string('revoked_reason', 500)->nullable();
            $table->timestamps();
        });

        $checks = [
            'certificates_code_length' => 'CHAR_LENGTH(verification_code) = 32',
            'certificates_score_percent_range' => 'score_percent BETWEEN 0 AND 100',
            'certificates_revoked_reason_needs_revocation' => 'revoked_reason IS NULL OR revoked_at IS NOT NULL',
        ];

        foreach ($checks as $name => $expression) {
            DB::statement("ALTER TABLE certificates ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
