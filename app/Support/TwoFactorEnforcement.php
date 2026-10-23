<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;

/**
 * Whether the admin and teacher areas require confirmed two-factor authentication.
 *
 * Always true outside the `local` environment: `braincheck.enforce_two_factor`
 * can switch it off for local demo logins only, never in testing, staging or production.
 */
final readonly class TwoFactorEnforcement
{
    public function __construct(
        private Application $app,
        private Config $config,
    ) {}

    public function isEnabled(): bool
    {
        if (! $this->app->environment('local')) {
            return true;
        }

        return $this->config->get('braincheck.enforce_two_factor', true) !== false;
    }
}
