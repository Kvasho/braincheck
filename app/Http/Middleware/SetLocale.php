<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Locale;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale. First match wins:
 * 1. the authenticated user's saved preference,
 * 2. the session,
 * 3. the Accept-Language header,
 * 4. the default (Locale::default()).
 *
 * Every candidate goes through the Locale enum: raw input never reaches App::setLocale().
 */
final class SetLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        App::setLocale($locale->value);
        Date::setLocale($locale->value);

        return $next($request);
    }

    public function resolve(Request $request): Locale
    {
        $user = $request->user();

        if ($user instanceof User && $user->locale instanceof Locale) {
            return $user->locale;
        }

        $session = $request->hasSession() ? $request->session()->get(Locale::SESSION_KEY) : null;

        if (is_string($session) && ($fromSession = Locale::tryFrom($session)) !== null) {
            return $fromSession;
        }

        foreach ($request->getLanguages() as $language) {
            if (($fromHeader = Locale::fromLanguageTag($language)) !== null) {
                return $fromHeader;
            }
        }

        return Locale::default();
    }
}
