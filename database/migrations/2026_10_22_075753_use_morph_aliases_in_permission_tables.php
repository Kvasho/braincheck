<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Relation::enforceMorphMap() (AppServiceProvider) stores short aliases instead
 * of class names. spatie/laravel-permission already stored `App\Models\User`
 * in its morph columns: rewrite those rows to the `user` alias, otherwise
 * existing role assignments would no longer be found.
 */
return new class extends Migration
{
    /** @var array<string, string> class name => alias */
    private array $aliases = [
        'App\\Models\\User' => 'user',
    ];

    public function up(): void
    {
        foreach (['model_has_roles', 'model_has_permissions'] as $table) {
            foreach ($this->aliases as $class => $alias) {
                DB::table($table)->where('model_type', $class)->update(['model_type' => $alias]);
            }
        }
    }

    public function down(): void
    {
        foreach (['model_has_roles', 'model_has_permissions'] as $table) {
            foreach ($this->aliases as $class => $alias) {
                DB::table($table)->where('model_type', $alias)->update(['model_type' => $class]);
            }
        }
    }
};
