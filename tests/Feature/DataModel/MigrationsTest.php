<?php

declare(strict_types=1);

namespace Tests\Feature\DataModel;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * migrate:fresh followed by a full rollback must succeed (every down() works).
 *
 * Class-based on purpose: DDL auto-commits in MySQL, so this test can't run inside
 * RefreshDatabase's transaction. It restores the schema (and roles) at the end so
 * the rest of the suite is unaffected. Tests\TestCase guarantees the connection is
 * braincheck_testing.
 */
final class MigrationsTest extends TestCase
{
    public function test_all_migrations_run_fresh_and_roll_back_completely(): void
    {
        $this->assertSame('braincheck_testing', DB::connection()->getDatabaseName(), 'Refusing to run outside the testing database.');

        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();

        $domainTables = [
            'categories', 'tags', 'questions', 'question_tag', 'tests', 'test_question', 'groups',
            'group_user', 'group_test', 'teacher_access_requests', 'attempts', 'attempt_questions',
            'attempt_answers', 'attempt_events', 'certificates', 'user_badges', 'xp_transactions', 'audit_logs',
        ];

        foreach ($domainTables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table [{$table}] after migrate:fresh.");
        }

        $this->assertTrue(Schema::hasColumn('users', 'xp_total'));

        try {
            $this->artisan('migrate:rollback', ['--force' => true])->assertSuccessful();

            foreach ([...$domainTables, 'users', 'roles', 'permissions'] as $table) {
                $this->assertFalse(Schema::hasTable($table), "Table [{$table}] survived the rollback.");
            }

            $this->assertSame(0, (int) DB::table('migrations')->count(), 'Some migrations were not rolled back.');
        } finally {
            // Restore the schema for the rest of the suite.
            $this->artisan('migrate', ['--force' => true]);
            $this->seed(RolesAndPermissionsSeeder::class);
        }

        $this->assertTrue(Schema::hasTable('attempts'));
    }
}
