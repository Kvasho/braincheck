<?php

declare(strict_types=1);

namespace App\Actions\Localization;

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

final class UpdateLocale
{
    /**
     * Remember the locale for this session and, for a signed-in user, as their preference.
     */
    public function handle(Locale $locale, Session $session, ?User $user = null): void
    {
        $session->put(Locale::SESSION_KEY, $locale->value);

        if ($user !== null) {
            $user->locale = $locale;
            $user->save();
        }
    }
}
