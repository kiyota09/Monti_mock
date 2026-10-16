<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureManufacturingRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $requiredRole): Response
    {
        $user = Auth::user();

        if (! $user || $user->role !== 'MAN') {
            abort(403, 'Not a manufacturing staff.');
        }

        // Manufacturing supervisors can access any role within their department
        if ($user->isManufacturingSupervisor()) {
            $assignedRoles = $user->getAssignedManufacturingRoles();
            if (in_array($requiredRole, $assignedRoles)) {
                return $next($request);
            }
            abort(403, "You are not authorized to access {$requiredRole}.");
        }

        // Regular staff: must have exactly that role
        if ($user->manufacturing_role !== $requiredRole) {
            abort(403, "You don't have permission for {$requiredRole}.");
        }

        return $next($request);
    }
}