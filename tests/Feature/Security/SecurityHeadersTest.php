<?php

declare(strict_types=1);

it('sends the security headers on a normal page', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
});

it('does not send HSTS outside production, even over HTTPS', function (): void {
    $this->get('https://braincheck.test/')
        ->assertOk()
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('sends HSTS in production over HTTPS only', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->get('https://braincheck.test/')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

    $this->get('http://braincheck.test/')
        ->assertHeaderMissing('Strict-Transport-Security');
});
