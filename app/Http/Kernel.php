<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        'signed' => \App\Http\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,

        // Custom middleware
        'role' => \App\Http\Middleware\CheckRole::class,
        'position' => \App\Http\Middleware\CheckPosition::class,
        'can.access.eco' => \App\Http\Middleware\CheckEcoAccess::class,
        'can.access.ord' => \App\Http\Middleware\CanAccessOrd::class,
        'can.access.warehouse' => \App\Http\Middleware\CanAccessWarehouse::class,
        'can.access.inventory' => \App\Http\Middleware\CanAccessInventory::class,
        'can.access.scm' => \App\Http\Middleware\CanAccessScm::class,
        'can.access.procurement' => \App\Http\Middleware\CanAccessProcurement::class,
        'can.access.logistics' => \App\Http\Middleware\CheckLogisticsAccess::class,
        'module.access' => \App\Http\Middleware\CheckModuleAccess::class,
        'can.access.man.manager' => \App\Http\Middleware\CheckManufacturingManagerAccess::class, // NEW
        'page.permission' => \App\Http\Middleware\CheckPagePermission::class,
        'man.role' => \App\Http\Middleware\CheckManufacturingRoleAccess::class,
    ];
}