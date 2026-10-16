<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware alias: man.role
 *
 * Controls granular access to manufacturing STAFF sub-pages.
 *
 * There is NO manufacturing manager: the module is handled by 4 department
 * supervisors (knitting, dyeing, finishing, maintenance), each overseeing
 * only their own department's staff pages.
 *
 * Access rules (in priority order):
 *  1. Secretary / special_officer → always allowed (executive oversight)
 *  2. Manufacturing supervisor (is_manufacturing_supervisor=true) → allowed on all pages
 *     whose role slug belongs to their assigned supervisor_department
 *  3. Regular staff → allowed only if their manufacturing_role exactly matches the required $role
 *
 * NOTE (overseer model): the President (CEO) is intentionally NOT bypassed
 * here — the executive acts on the floor through approvals, not direct access.
 */
class CheckManufacturingRoleAccess
{
    /**
     * Maps each supervisor_department value to the manufacturing_role slugs it covers.
     *
     * Keep this in sync with User::getSupervisedRolesAttribute and
     * ManAccessController::getDepartmentFromRole.
     */
    protected array $departmentRoles = [
        'knitting' => [
            'knitting_yarn',
            'knitting_mechanic',
        ],
        'dyeing' => [
            'dyeing_color',
            'dyeing_fabric_softener',
            'dyeing_squeezer',
            'dyeing_ironing',
            'dyeing_packaging',
            'dyeing_lab_chemist',
        ],
        'finishing' => [
            'checker_quality',
        ],
        'maintenance' => [
            'maintenance_checker',
            'pollution_control_operator',
            'safety_officer',
        ],
        'boiler' => [
            'boiler_operator',
        ],
    ];

    /**
     * Handle an incoming request.
     *
     * @param  string  $role  The manufacturing_role slug required for this route
     *                        e.g. 'dyeing_color', 'dyeing_ironing', 'knitting_yarn', etc.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        // ── 1. Elevated roles always have full access ─────────────────────────
        // (President excluded by design — see class docblock.)
        if (
            in_array($user->position, ['secretary', 'special_officer'])
        ) {
            return $next($request);
        }

        // ── 2. Manufacturing supervisor ───────────────────────────────────────
        // A supervisor of a given department can access every staff-role page
        // that belongs to that department.
        if ($user->is_manufacturing_supervisor) {
            $dept         = $user->supervisor_department; // e.g. 'dyeing'
            $allowedRoles = $this->departmentRoles[$dept] ?? [];

            if (in_array($role, $allowedRoles, true)) {
                return $next($request);
            }

            abort(403, "You are not authorized to access the {$role} section. "
                . "Your supervised department is '{$dept}'.");
        }

        // ── 3. Regular staff — exact role match ───────────────────────────────
        if ($user->manufacturing_role === $role) {
            return $next($request);
        }

        abort(403, "You are not authorized to access {$role}.");
    }
}