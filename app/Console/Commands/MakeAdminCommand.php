<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Users\CreateAdministrator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('braincheck:make-admin')]
#[Description('Create a verified administrator (the only way to create an admin)')]
final class MakeAdminCommand extends Command
{
    public function handle(CreateAdministrator $createAdministrator): int
    {
        $name = text(label: 'Name', required: true);
        $email = text(label: 'Email', required: true);
        $password = password(label: 'Password', required: true, hint: 'Min. 12 characters, mixed case, numbers and symbols.');
        $confirmation = password(label: 'Confirm password', required: true);

        try {
            $user = $createAdministrator->handle([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $this->components->info("Administrator [{$user->email}] created (email verified).");

        return self::SUCCESS;
    }
}
