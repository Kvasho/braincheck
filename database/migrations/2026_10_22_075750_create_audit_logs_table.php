<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail. The AuditLog model throws on update and delete;
 * only `created_at` exists. actor_id becomes NULL if the actor is deleted
 * (the entry itself is kept).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // e.g. "user.role_changed"
            $table->string('action', 100)->index();
            // Morph alias (Relation::enforceMorphMap), never a class name.
            $table->nullableMorphs('subject');
            $table->json('properties');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->dateTime('created_at');

            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
