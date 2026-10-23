<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Question/test categories. Maximum depth is 2 (category → subcategory) and a
 * category can't be its own parent; both are enforced in the application (Stage 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            // Localized name: {"ka": "...", "en": "..."}.
            $table->json('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Both translations required and non-empty (COALESCE: a missing key must fail, not pass as NULL).
        DB::statement(<<<'SQL'
            ALTER TABLE categories ADD CONSTRAINT categories_name_localized CHECK (
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.ka')), '') <> ''
                AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')), '') <> ''
            )
            SQL);

        // "Not its own parent" can't be a CHECK: MySQL forbids CHECKs on AUTO_INCREMENT columns.
        // It is enforced in the application together with the depth rule.
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
