<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckManufacturingManagerAccess
{
    /**
     * Handle an incoming request.
     *
     * There is NO manufacturing manager: the MAN overview pages (dashboard,
     * production, rejected, inventory, access) are handled by the 4 department
     * supervisors (knitting, dyeing, finishing, maintenance). Each supervisor
     * only sees their own department's data (scoped in the controllers).
     *
     * NOTE (overseer model): the President (CEO) keeps access here as a
     * personnel-governance exception — supervisor appointment/removal
     * (fulfilled via the CEO module + IT Access Control) requires a
     * cross-department authority, and without it a department with no
     * supervisor could never get one. All other MAN operating pages are
     * still driven by department scope.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthorized');
        }

        // Allow if:
        // 1. User is the CEO (global oversight)
        // 2. User is a manufacturing department supervisor
        // 3. User is Secretary or Special Officer AND has 'MAN' in granted_modules
        //    (or has no grants yet and MAN is their home module — same
        //    home-module fallback as CheckModuleAccess)
        if ($user->role === 'CEO' ||
            $user->is_manufacturing_supervisor ||
            (in_array($user->position, ['secretary', 'special_officer']) &&
              (in_array('MAN', $user->granted_modules ?? []) ||
                (empty($user->granted_modules ?? []) && $user->role === 'MAN')))) {
            return $next($request);
        }

        abort(403, 'Unauthorized - Only manufacturing department supervisors (knitting, dyeing, finishing, maintenance) or authorized oversight roles can access this page.');
    }
}