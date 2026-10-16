<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CanAccessInventory
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

        // For others, check if they have inventory access (direct permission or via warehouse assignment)
        if ($user->hasInventoryAccess() || $user->hasWarehouseAccess()) {
            return $next($request);
        }

        abort(403, 'You do not have permission to access the Inventory module.');
    }
}