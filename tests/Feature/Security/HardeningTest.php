<?php

declare(strict_types=1);

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\Route;

it('throttles repeated login attempts', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
    }

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertTooManyRequests();
});

it('runs models in strict mode', function (): void {
    User::factory()->count(2)->create();

    expect(fn () => User::query()->get()->first()?->passkeys)
        ->toThrow(LazyLoadingViolationException::class);

    expect(fn () => User::query()->select('id')->firstOrFail()->email)
        ->toThrow(MissingAttributeException::class);

    expect(fn () => new User(['email_verified_at' => now()]))
        ->toThrow(MassAssignmentException::class);
});

it('keeps unverified users away from the dashboard', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

it('never shows stack traces or environment details in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    config(['app.debug' => true]);
    (new AppServiceProvider(app()))->boot();

    Route::get('/_boom', fn () => throw new RuntimeException('secret-internal-detail'));

    $response = $this->get('/_boom')->assertServerError();

    $content = (string) $response->getContent();

    expect(config('app.debug'))->toBeFalse()
        ->and($content)->not->toContain('secret-internal-detail')
        ->and($content)->not->toContain('RuntimeException')
        ->and($content)->not->toContain(base_path())
        ->and($content)->not->toContain('APP_KEY');
});
