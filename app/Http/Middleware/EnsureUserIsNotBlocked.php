<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of a user who was blocked after logging in.
 *
 * Registered on the whole `web` group, so it covers every authenticated route
 * (including vendor routes); guests pass straight through.
 */
final class EnsureUserIsNotBlocked
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isBlocked()) {
            return $next($request);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = __('Your session has ended. Please log in again.');

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message], Response::HTTP_UNAUTHORIZED);
        }

        // Neutral wording: never reveal that (or why) the account is blocked.
        return redirect()->route('login')->with('status', $message);
    }
}
