<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CanAccessWarehouse
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        // CEO, secretary, general manager always have access
        if ($user->position === 'secretary' || $user->position === 'special_officer') {
            return $next($request);
        }

        // For other positions (manager, supervisor, staff), check if they have any warehouse assigned
        if ($user->hasWarehouseAccess()) {
            return $next($request);
        }

        abort(403, 'You do not have permission to access the Warehouse module.');
    }
}