<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

/**
 * Registered only in the local environment (see AppServiceProvider::registerTelescope).
 */
class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();

        Telescope::filter(fn (IncomingEntry $entry): bool => $this->app->environment('local'));
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     *
     * Applied in every environment, including local, so dev data never leaks secrets.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters([
            '_token',
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'code',
            'recovery_code',
            'secret',
        ]);

        Telescope::hideRequestHeaders([
            'authorization',
            'cookie',
            'set-cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'php-auth-pw',
        ]);

        Telescope::hideResponseParameters([
            'token',
            'secret',
            'recovery_codes',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * Denies everyone outside the local environment.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', fn (): bool => $this->app->environment('local'));
    }
}
