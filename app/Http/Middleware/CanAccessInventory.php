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

        // IT can grant Inventory pages directly to an employee whose home
        // role is in another module. Keep this legacy module gate consistent
        // with CheckModuleAccess: a usable INV page grant is sufficient to
        // enter the module, while page.permission still limits the exact page.
        if (method_exists($user, 'pagePermissions') && $user->pagePermissions()
            ->whereIn('module', ['INV', 'inv'])
            ->where(function ($query) {
                $query->whereIn('permission_level', ['view', 'edit'])
                    ->orWhereNull('permission_level');
            })
            ->exists()) {
            return $next($request);
        }

        // For others, check if they have inventory access (direct permission or via warehouse assignment)
        if ($user->hasInventoryAccess() || $user->hasWarehouseAccess()) {
            return $next($request);
        }

        abort(403, 'You do not have permission to access the Inventory module.');
    }
}
