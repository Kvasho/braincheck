<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Users\AssignInitialRole;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Demo accounts for local development. NOT called from DatabaseSeeder.
 *
 *   php artisan db:seed --class=DemoUsersSeeder
 *
 * All accounts use the password below (local only, documented on purpose):
 *   admin@braincheck.test, teacher1@braincheck.test, teacher2@braincheck.test,
 *   student1@braincheck.test … student5@braincheck.test
 *
 * The admin and teacher areas require confirmed 2FA. For quick demo logins set
 * BRAINCHECK_ENFORCE_TWO_FACTOR=false in your .env file; it is honoured in `local` only. To test
 * with enforcement on, set it to true (or remove it), run `php artisan config:clear`,
 * sign in as the demo admin: you are sent to Settings → Security until 2FA is confirmed.
 */
class DemoUsersSeeder extends Seeder
{
    public const PASSWORD = 'BrainCheck-Demo-2026!';

    public function run(AssignInitialRole $assignInitialRole): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'DemoUsersSeeder only runs in the local or testing environment: it creates accounts with a known password.',
            );
        }

        $this->call(RolesAndPermissionsSeeder::class);

        $accounts = [
            ['admin@braincheck.test', 'Demo Admin', Role::Admin],
            ['teacher1@braincheck.test', 'Demo Teacher 1', Role::Teacher],
            ['teacher2@braincheck.test', 'Demo Teacher 2', Role::Teacher],
        ];

        foreach (range(1, 5) as $number) {
            $accounts[] = ["student{$number}@braincheck.test", "Demo Student {$number}", Role::Student];
        }

        foreach ($accounts as [$email, $name, $role]) {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => self::PASSWORD],
            );

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            if ($user->roleOrNull() === null) {
                $assignInitialRole->handle($user, $role);
            }
        }
    }
}
