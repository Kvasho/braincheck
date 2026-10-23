<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TwoFactorEnforcement;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends users without confirmed 2FA to the security settings page.
 *
 * Applied only to the /admin and /teacher route groups (bootstrap/app.php), after
 * the role check. Settings, logout and the Fortify 2FA setup routes live outside
 * those groups, so they stay reachable and the redirect can't loop.
 */
final class EnsureTwoFactorEnabled
{
    public function __construct(private readonly TwoFactorEnforcement $enforcement) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->hasConfirmedTwoFactor() || ! $this->enforcement->isEnabled()) {
            return $next($request);
        }

        $message = __('Enable two-factor authentication to access this area.');

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
        }

        Inertia::flash('toast', ['type' => 'warning', 'message' => $message]);

        return redirect()->route('security.edit');
    }
}
