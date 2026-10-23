<?php

declare(strict_types=1);

arch('php preset')->preset()->php();

arch('security preset')->preset()->security();

/*
 * Laravel preset exceptions:
 * - App\Providers: AppServiceProvider registers the local-only TelescopeServiceProvider,
 *   so App\Providers is "used" from within itself.
 */
arch('laravel preset')->preset()->laravel()->ignoring([
    'App\Providers\TelescopeServiceProvider',
]);
