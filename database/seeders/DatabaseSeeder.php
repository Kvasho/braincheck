<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Production-safe: only roles and permissions. No users are created here
     * (demo users: `php artisan db:seed --class=DemoUsersSeeder`, local only;
     * admins: `php artisan braincheck:make-admin`).
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
    }
}
