<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Localization\UpdateLocale;
use App\Http\Requests\UpdateLocaleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class LocaleController extends Controller
{
    public function update(UpdateLocaleRequest $request, UpdateLocale $updateLocale): RedirectResponse
    {
        $user = $request->user();

        $updateLocale->handle(
            $request->locale(),
            $request->session(),
            $user instanceof User ? $user : null,
        );

        // back() trusts the Referer header; only return to our own origin (no open redirect).
        $previous = url()->previous();

        return str_starts_with($previous, url('/').'/') || $previous === url('/')
            ? redirect()->to($previous)
            : redirect()->route('home');
    }
}
