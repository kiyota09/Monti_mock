<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Register custom middleware aliases
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'position' => \App\Http\Middleware\CheckPosition::class,
            'geofence' => \App\Http\Middleware\GeofenceAccessMiddleware::class, // ✅ ADDED THIS LINE
            'can.access.eco' => \App\Http\Middleware\CheckEcoAccess::class,
            'can.access.ord' => \App\Http\Middleware\CanAccessOrd::class,
            'can.access.warehouse' => \App\Http\Middleware\CanAccessWarehouse::class,
            'can.access.inventory' => \App\Http\Middleware\CanAccessInventory::class,
            'can.access.scm' => \App\Http\Middleware\CanAccessScm::class,
            'man.ensure-role' => \App\Http\Middleware\EnsureManufacturingRole::class,
            'can.access.procurement' => \App\Http\Middleware\CanAccessProcurement::class,
            'can.access.logistics' => \App\Http\Middleware\CheckLogisticsAccess::class,
            'module.access' => \App\Http\Middleware\CheckModuleAccess::class,
            'can.access.man.manager' => \App\Http\Middleware\CheckManufacturingManagerAccess::class,
            'page.permission' => \App\Http\Middleware\CheckPagePermission::class,
            'man.role' => \App\Http\Middleware\CheckManufacturingRoleAccess::class,
        ]);

        /**
         * ✅ Dynamic Redirection Logic
         * This solves the redirect loop. It checks which user is logged in
         * and sends them to the correct dashboard.
         */
        $middleware->redirectUsersTo(function () {
            if (Auth::guard('client')->check()) {
                return route('client.dashboard');
            }
            return route('dashboard');
        });

        /**
         * ✅ Guest Redirection Logic
         * If a guest tries to access a protected page, redirect them to the
         * correct login page based on the URL prefix.
         */
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('partner/*') || $request->is('client/*')) {
                return route('client.login');
            }
            if ($request->is('applicant/*')) {
                return route('applicant.login');
            }
            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A 403 for an account that holds NO usable grant at all (every page
        // still 'disabled' by IT) becomes the friendly waiting page instead
        // of a raw error — this covers stale sidebar links/bookmarks held by
        // an already-logged-in staffer, whose menu only refreshes on reload.
        // Accounts WITH access keep their exact 403s (e.g. view-only hitting
        // an edit route).
        $exceptions->render(function (Symfony\Component\HttpKernel\Exception\HttpException $e, Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 403) {
                return null;
            }
            if ($request->routeIs('awaiting.access') || $request->routeIs('dashboard')) {
                return null;
            }
            $user = $request->user();
            if (! $user) {
                return null;
            }
            if (\App\Support\AccessGate::hasAnyAccess($user)) {
                return null;
            }

            return redirect()->route('awaiting.access');
        });
    })->create();