<?php

namespace App\Http\Middleware;

use App\Support\ManufacturingRoles;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware alias: man.department
 *
 * Restricts a MAN route to the supervisor of one production department
 * (knitting / dyeing / finishing). Used for the supervisor quality gates:
 *  - knitting supervisor  → knitting output gate (passFabric)
 *  - dyeing supervisor    → every dyeing stage gate (dye, softener, squeezer, iron)
 *  - finishing supervisor → push inspected packages to logistics
 *
 * Elevated oversight roles (CEO, secretary, special_officer) keep access; a
 * regular department staff member never does (they work through `man.role`).
 */
class EnsureManufacturingDepartment
{
    public function handle(Request $request, Closure $next, string $department): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        // Executive oversight.
        if (
            $user->role === 'CEO'
            || in_array($user->position, ['secretary', 'special_officer'], true)
        ) {
            return $next($request);
        }

        if (
            $user->is_manufacturing_supervisor
            && $user->supervisor_department === $department
        ) {
            return $next($request);
        }

        $label = ManufacturingRoles::departmentLabel($department);

        abort(403, "Only the {$label} supervisor can perform this action.");
    }
}
