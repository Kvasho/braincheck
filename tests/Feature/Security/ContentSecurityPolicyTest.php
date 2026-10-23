<?php

declare(strict_types=1);

/**
 * @return array{0: string, 1: string} [policy, nonce]
 */
function cspFrom(Illuminate\Testing\TestResponse $response, string $header = 'Content-Security-Policy'): array
{
    $policy = (string) $response->headers->get($header);

    expect($policy)->not->toBe('');
    expect(preg_match("/script-src 'self' 'nonce-([A-Za-z0-9+\/=]+)'/", $policy, $matches))->toBe(1);

    return [$policy, $matches[1]];
}

it('enforces a nonce-based CSP outside local', function (): void {
    $response = $this->get(route('home'))->assertOk();

    [$policy] = cspFrom($response);

    expect($policy)
        ->toContain("default-src 'self'")
        ->toContain("style-src 'self' 'unsafe-inline'")
        ->toContain("img-src 'self' data: blob:")
        ->toContain("font-src 'self'")
        ->toContain("connect-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'")
        ->toContain("frame-ancestors 'none'")
        ->not->toContain('unsafe-eval');

    $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
});

it('puts the CSP nonce on every executable script tag of the page', function (): void {
    $response = $this->get(route('home'))->assertOk();

    [, $nonce] = cspFrom($response);

    preg_match_all('/<script\b[^>]*>/i', (string) $response->getContent(), $tags);

    $executable = array_filter($tags[0], fn (string $tag): bool => ! str_contains($tag, 'type="application/json"'));

    expect($executable)->not->toBeEmpty();

    foreach ($executable as $tag) {
        expect($tag)->toContain('nonce="'.$nonce.'"');
    }
});

it('generates a fresh nonce for every request', function (): void {
    [, $first] = cspFrom($this->get(route('home')));
    [, $second] = cspFrom($this->get(route('home')));

    expect($first)->not->toBe($second);
});

it('uses report-only mode in local', function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    $response = $this->get(route('home'))->assertOk();

    cspFrom($response, 'Content-Security-Policy-Report-Only');
    $response->assertHeaderMissing('Content-Security-Policy');
});
