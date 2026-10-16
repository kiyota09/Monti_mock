<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        // CEO (President) has unrestricted access to all modules, including
        // role:COO gates — this preserves presidential oversight of the VP
        // workspace after the role split. COO itself is granted explicitly
        // per route (role:COO) and bypasses nothing else.
        if ($user->role === 'CEO') {
            return $next($request);
        }

        // If no specific roles are required, deny access (fail closed)
        if (empty($roles)) {
            abort(403, 'No roles specified for this route.');
        }

        // Convert allowed roles to uppercase for case-insensitive matching
        $allowedRoles = array_map('strtoupper', $roles);

        // For secretaries and general managers: they retain their original role's permissions
        if (in_array($user->position, ['secretary', 'special_officer'])) {
            if (in_array($user->role, $allowedRoles)) {
                return $next($request);
            }
        }

        // Normal check: user's role must be in the allowed roles list
        if (in_array($user->role, $allowedRoles)) {
            return $next($request);
        }

        abort(403, 'You do not have permission to access this resource.');
    }
}