<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Public identifier for tags (admin tag management, Stage 3). Existing rows are
 * backfilled before the column becomes NOT NULL + unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->ulid('ulid')->nullable()->after('id');
        });

        DB::table('tags')->whereNull('ulid')->orderBy('id')->chunkById(500, function ($tags): void {
            foreach ($tags as $tag) {
                DB::table('tags')->where('id', $tag->id)->update(['ulid' => (string) Str::ulid()]);
            }
        });

        Schema::table('tags', function (Blueprint $table): void {
            $table->ulid('ulid')->nullable(false)->change();
            $table->unique('ulid');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->dropUnique(['ulid']);
            $table->dropColumn('ulid');
        });
    }
};
