<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assignment of tests to groups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_test', function (Blueprint $table): void {
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('test_id')->constrained('tests')->cascadeOnDelete();
            $table->dateTime('assigned_at');

            $table->primary(['group_id', 'test_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_test');
    }
};
