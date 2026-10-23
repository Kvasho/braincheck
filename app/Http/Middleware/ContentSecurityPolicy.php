<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nonce-based Content Security Policy.
 *
 * - Enforced everywhere except `local`, where it is Report-Only and also allows the Vite dev server.
 * - `style-src 'unsafe-inline'`: sonner, Inertia's progress bar and Radix inject <style> elements
 *   and style attributes without nonce support. Styles cannot execute script, so this is an
 *   accepted trade-off while `script-src` stays strictly nonce-based.
 */
final class ContentSecurityPolicy
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $header = app()->isLocal()
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $response->headers->set($header, $this->policy($nonce));

        return $response;
    }

    private function policy(string $nonce): string
    {
        $devServer = $this->viteDevServer();
        $devSocket = $devServer === null ? null : (string) preg_replace('/^http/', 'ws', $devServer);

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", $devServer],
            'style-src' => ["'self'", "'unsafe-inline'", $devServer],
            'img-src' => ["'self'", 'data:', 'blob:', $devServer],
            'font-src' => ["'self'", $devServer],
            'connect-src' => ["'self'", $devServer, $devSocket],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', array_filter($sources)))
            ->implode('; ');
    }

    /**
     * The Vite dev server origin, only in local and only while `npm run dev` is running.
     */
    private function viteDevServer(): ?string
    {
        if (! app()->isLocal()) {
            return null;
        }

        $hotFile = Vite::hotFile();

        if (! is_file($hotFile)) {
            return null;
        }

        $url = trim((string) file_get_contents($hotFile));

        return preg_match('#^https?://[^\s;\'"]+$#', $url) === 1 ? rtrim($url, '/') : null;
    }
}
