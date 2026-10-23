<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Http\Middleware\EnsureUserIsNotBlocked;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Role areas. The role middleware is the outer fence; each route inside also
            // requires a specific permission. EnsureUserIsNotBlocked runs on the web group.
            // Admin and teacher areas also require confirmed 2FA (after the role check, so
            // other roles still get a plain 403).
            $areas = [
                'admin' => [Role::Admin, [EnsureTwoFactorEnabled::class]],
                'teacher' => [Role::Teacher, [EnsureTwoFactorEnabled::class]],
                'student' => [Role::Student, []],
            ];

            foreach ($areas as $prefix => [$role, $extra]) {
                Route::middleware(['web', 'auth', 'verified', 'role:'.$role->value, ...$extra])
                    ->prefix($prefix)
                    ->name($prefix.'.')
                    ->group(base_path("routes/{$prefix}.php"));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        $middleware->append([
            SecurityHeaders::class,
            ContentSecurityPolicy::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            // Covers every authenticated web route (incl. vendor routes); guests pass through.
            EnsureUserIsNotBlocked::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Styled, translated error pages (Synapse). 403/404/419/429 always; 500/503 only
        // when debug is off, so local development keeps Laravel's detailed error page.
        // Only for requests that went through the web stack (session + Inertia shared data).
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            $status = $response->getStatusCode();
            $styled = [403, 404, 419, 429, 500, 503];

            if (! in_array($status, $styled, true)
                || ($status >= 500 && config('app.debug') === true)
                || ($request->expectsJson() && ! $request->header('X-Inertia'))
                || ! $request->hasSession()) {
                return $response;
            }

            return Inertia::render('error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
