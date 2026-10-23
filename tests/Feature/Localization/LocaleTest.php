<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Symfony's test client sends "Accept-Language: en-us" by default; start from a clean slate.
beforeEach(function (): void {
    $this->withHeader('Accept-Language', '');
});

/**
 * @return array<string, mixed>
 */
function pageProps(Illuminate\Testing\TestResponse $response): array
{
    return $response->assertOk()->viewData('page')['props'];
}

describe('switching the locale', function (): void {
    it('rejects an unsupported locale with 422', function (): void {
        $this->postJson(route('locale.update'), ['locale' => 'de'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('locale');
    });

    it('rejects path-traversal input and never stores or applies it', function (string $payload): void {
        $this->postJson(route('locale.update'), ['locale' => $payload])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('locale');

        expect(session(Locale::SESSION_KEY))->toBeNull()
            ->and(app()->getLocale())->toBe(Locale::default()->value);
    })->with(['../../etc', '../../../etc/passwd', 'ka/../../en', '%2e%2e%2f', 'EN ', 'ka-GE']);

    it('only ever stores an enum value (TrimStrings strips control characters first)', function (): void {
        $this->postJson(route('locale.update'), ['locale' => "en\0"])->assertRedirect();

        expect(session(Locale::SESSION_KEY))->toBe(Locale::English->value);
    });

    it('requires a locale', function (): void {
        $this->postJson(route('locale.update'), [])->assertUnprocessable();
    });

    it('stores the locale in the session for guests', function (): void {
        $this->from(route('home'))
            ->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect(route('home'));

        expect(session(Locale::SESSION_KEY))->toBe('en');
        expect(pageProps($this->get(route('home')))['locale'])->toBe('en');
    });

    it('persists the preference for an authenticated user', function (): void {
        $user = User::factory()->student()->create();

        $this->actingAs($user)
            ->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect();

        expect($user->refresh()->locale)->toBe(Locale::English);

        // A fresh session (e.g. another device) still gets the saved preference.
        $this->flushSession();
        expect(pageProps($this->actingAs($user)->get(route('dashboard')))['locale'])->toBe('en');
    });

    it('never redirects to another origin', function (): void {
        $this->withHeader('referer', 'https://evil.example/phish')
            ->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect(route('home'));
    });

    it('is CSRF-protected and rate limited', function (): void {
        $route = Route::getRoutes()->getByName('locale.update');

        expect($route?->gatherMiddleware())->toContain('web', 'throttle:locale');

        foreach (range(1, 10) as $attempt) {
            $this->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();
        }

        $this->post(route('locale.update'), ['locale' => 'en'])->assertTooManyRequests();
    });
});

describe('resolving the locale (first match wins)', function (): void {
    it('falls back to the default: Georgian', function (): void {
        $response = $this->get(route('home'));

        expect(pageProps($response)['locale'])->toBe('ka');
        $response->assertSee('<html lang="ka"', escape: false);
    });

    it('uses Accept-Language when nothing else is set', function (): void {
        $response = $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->get(route('home'));

        expect(pageProps($response)['locale'])->toBe('en');
        $response->assertSee('<html lang="en"', escape: false);
    });

    it('ignores unsupported Accept-Language values', function (): void {
        $response = $this->withHeader('Accept-Language', 'de-DE,fr;q=0.8')->get(route('home'));

        expect(pageProps($response)['locale'])->toBe('ka');
    });

    it('prefers the session over Accept-Language', function (): void {
        $response = $this->withSession([Locale::SESSION_KEY => 'ka'])
            ->withHeader('Accept-Language', 'en-US')
            ->get(route('home'));

        expect(pageProps($response)['locale'])->toBe('ka');
    });

    it('ignores a tampered session value', function (): void {
        $response = $this->withSession([Locale::SESSION_KEY => '../../etc'])
            ->withHeader('Accept-Language', 'en')
            ->get(route('home'));

        expect(pageProps($response)['locale'])->toBe('en');
    });

    it('prefers the user preference over the session', function (): void {
        $user = User::factory()->student()->create(['locale' => Locale::English]);

        $response = $this->actingAs($user)
            ->withSession([Locale::SESSION_KEY => 'ka'])
            ->withHeader('Accept-Language', 'ka')
            ->get(route('dashboard'));

        expect(pageProps($response)['locale'])->toBe('en');
    });
});

describe('shared translations', function (): void {
    it('shares the active locale UI translations and the locale options', function (): void {
        $props = pageProps($this->get(route('home')));

        expect($props['translations']['Log in'])->toBe('შესვლა')
            ->and($props['locales'])->toBe(Locale::options());

        $english = pageProps($this->withHeader('Accept-Language', 'en')->get(route('home')));

        expect($english['translations']['Log in'])->toBe('Log in');
    });

    it('translates server-side messages too', function (): void {
        $this->postJson(route('locale.update'), ['locale' => 'xx'])
            ->assertJsonPath('errors.locale.0', fn (string $message): bool => $message !== 'validation.enum'
                && ! str_contains($message, 'The selected'));
    });
});
