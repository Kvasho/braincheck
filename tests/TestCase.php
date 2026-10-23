<?php

declare(strict_types=1);

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * The only database tests are ever allowed to connect to.
     */
    private const TESTING_DATABASE = 'braincheck_testing';

    /**
     * RefreshDatabase seeds roles and permissions (from the enums) for every test.
     */
    protected bool $seed = true;

    protected string $seeder = RolesAndPermissionsSeeder::class;

    /**
     * Boot the application and refuse to run against anything but the testing database.
     *
     * This runs before RefreshDatabase, so a misconfigured environment can never
     * wipe the development database.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if (! $app->environment('testing') || $database !== self::TESTING_DATABASE) {
            throw new RuntimeException(sprintf(
                'Refusing to run tests: expected APP_ENV=testing and database [%s], got APP_ENV=%s and database [%s].',
                self::TESTING_DATABASE,
                $app->environment(),
                is_string($database) ? $database : gettype($database),
            ));
        }

        return $app;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
