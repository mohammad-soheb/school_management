<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser protections for every web response (FR-110).
 *
 * The Content-Security-Policy is skipped in the local environment because the Vite dev server serves assets from
 * another origin. 'unsafe-eval' is required by Alpine.js expressions; moving to the CSP-safe Alpine build is a
 * tracked hardening item (docs/SECURITY.md).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generates the per-request nonce that @vite and the theme bootstrap script attach to their tags.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ];

        if (! app()->environment('local')) {
            $headers['Content-Security-Policy'] = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob:",
                "font-src 'self' data:",
                "connect-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
            ]);
        }

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        // Pages for signed-in users contain children's data: do not let browsers or proxies keep copies.
        // (Symfony pre-fills "no-cache, private", so test for an explicit lifetime / no-store instead of "missing".)
        if ($request->user() !== null
            && ! $response->headers->hasCacheControlDirective('max-age')
            && ! $response->headers->hasCacheControlDirective('no-store')) {
            $headers['Cache-Control'] = 'no-store, private';
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
