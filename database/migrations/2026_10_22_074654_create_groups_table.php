<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Teacher-owned student groups. `invite_code`: 10 characters from an
 * unambiguous uppercase alphabet, generated with a CSPRNG (secret, $hidden).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->char('invite_code', 10)->unique();
            $table->dateTime('invite_code_expires_at')->nullable();
            $table->boolean('joining_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE groups ADD CONSTRAINT groups_invite_code_length CHECK (CHAR_LENGTH(invite_code) = 10)');
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
