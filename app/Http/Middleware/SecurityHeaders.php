<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(self), microphone=(), camera=()');

        // Content-Security-Policy is only enforced in production. During local
        // development the Vite dev server serves the JS/CSS bundle from a
        // different origin (e.g. http://[::1]:5175) with an HMR websocket, so a
        // strict `script-src 'self'` policy blocks the app and yields a blank
        // screen. Guarding on the environment keeps dev working while still
        // hardening production.
        $viteDevServerActive = is_file(public_path('hot'));

        if (config('app.env') === 'production' && ! $viteDevServerActive) {
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
                "font-src 'self' data: https://fonts.bunny.net",
                "img-src 'self' data: blob: https://*.tile.openstreetmap.org https://*.basemaps.cartocdn.com",
                "connect-src 'self' https://*.tile.openstreetmap.org https://*.basemaps.cartocdn.com",
                "frame-ancestors 'self'",
                "base-uri 'self'",
                "form-action 'self'",
            ]);
            $response->headers->set('Content-Security-Policy', $csp);
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
