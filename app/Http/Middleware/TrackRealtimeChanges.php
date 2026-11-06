<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackRealtimeChanges
{
    private const VERSION_KEY = 'erp:realtime:data-version';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $routeName = (string) $request->route()?->getName();
        $path = $request->path();
        $isHighFrequencyConversationRequest = str_contains($routeName, '.message')
            || str_contains($routeName, '.chat.')
            || str_contains($routeName, '.calls.')
            || str_starts_with($path, 'messages/')
            || str_starts_with($path, 'chat/');
        $isAuthenticationRequest = str_contains($routeName, 'login')
            || str_contains($routeName, 'logout')
            || str_contains($routeName, 'password');

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            || $response->getStatusCode() >= 400
            || $isHighFrequencyConversationRequest
            || $isAuthenticationRequest
            || $request->session()->has('errors')) {
            return $response;
        }

        try {
            Cache::add(self::VERSION_KEY, 0, now()->addYears(10));
            $version = Cache::increment(self::VERSION_KEY);
            $response->headers->set('X-ERP-Data-Version', (string) $version);
        } catch (Throwable $exception) {
            // Realtime refresh is an enhancement; a cache outage must not block writes.
            report($exception);
        }

        return $response;
    }
}
