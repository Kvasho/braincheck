<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Localization\LoadTranslations;
use App\Enums\Locale;
use App\Enums\TeacherRequestStatus;
use App\Http\Resources\AuthUserResource;
use App\Models\TeacherAccessRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => fn (): ?array => $request->user() instanceof User
                    ? (new AuthUserResource($request->user()))->resolve($request)
                    : null,
                // Flat list of permission names. UI-only hint: the backend enforces access.
                'permissions' => fn (): array => $request->user() instanceof User
                    ? $request->user()->getAllPermissions()->pluck('name')->map(fn (mixed $name): string => (string) $name)->sort()->values()->all()
                    : [],
            ],
            'locale' => $this->locale()->value,
            'locales' => Locale::options(),
            // Sent once per locale and remembered by the client across navigations.
            'translations' => Inertia::once(fn (): array => app(LoadTranslations::class)->handle($this->locale()))
                ->as('translations.'.$this->locale()->value),
            // Inertia::flash() data travels in the page's own `flash` field; this shares
            // classic session flashes (e.g. Fortify's `status`).
            'flash' => fn (): array => [
                'status' => $request->session()->get('status'),
            ],
            // Sidebar counters, only for users who may act on them (UI hint only).
            'navBadges' => fn (): array => $this->navBadges($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<string, int>
     */
    private function navBadges(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->can('viewAny', TeacherAccessRequest::class)) {
            return [];
        }

        return [
            'teacherRequests' => TeacherAccessRequest::query()->where('status', TeacherRequestStatus::Pending->value)->count(),
        ];
    }

    /**
     * The locale resolved by SetLocale (always a Locale enum value).
     */
    private function locale(): Locale
    {
        return Locale::tryFrom(app()->getLocale()) ?? Locale::default();
    }
}
