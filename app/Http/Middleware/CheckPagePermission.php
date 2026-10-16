<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPagePermission
{
    /**
     * Handle an incoming request.
     *
     * Permission levels: 'view' grants read-only access, 'edit' grants
     * read + write, 'disabled' explicitly blocks access (set by IT).
     * 'edit' implies 'view'. Role-based shortcuts (CEO,
     * module-native manager/staff) still grant full access — UNLESS an
     * explicit 'disabled' row exists, which always wins.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $page  The page identifier (e.g., 'dashboard', 'employees')
     * @param  string|null  $requiredLevel  'view' (default) or 'edit'.
     */
    public function handle(Request $request, Closure $next, string $page, ?string $requiredLevel = null): Response
    {
        $requiredLevel = strtolower($requiredLevel ?? 'view');
        if (! in_array($requiredLevel, ['view', 'edit'], true)) {
            $requiredLevel = 'view';
        }

        $user = Auth::user();

        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        // NOTE (overseer model): the President has no page bypass — executive
        // oversight runs through the CEO module (dashboard, reports, audit,
        // approvals), not direct module pages.

        // Determine the module from the route name or URI
        $module = $this->detectModule($request);

        // Fetch the explicit record first — an explicit 'disabled' row set by
        // IT always denies, even for native managers/staff or secretaries.
        $record = null;
        $grantedLevel = null;
        if ($module && method_exists($user, 'pagePermissions')) {
            $record = $user->pagePermissions()
                ->whereIn('module', [$module, strtoupper($module), strtolower($module)])
                ->get(['module', 'page', 'permission_level'])
                ->first(fn ($row) => strtolower((string) $row->page) === strtolower($page));
            if ($record !== null) {
                $grantedLevel = strtolower($record->permission_level ?? 'edit');
                if ($grantedLevel === 'disabled') {
                    abort(403, "The '{$page}' page in the {$module} module is disabled for this account by IT Access Control.");
                }
            }
        }

        // Module-native managers and staff automatically have full access —
        // UNLESS explicit page_permissions rows exist for them in this module.
        // Explicit grants are then the exact access set (this is what makes
        // "give this employee only 2 pages" actually restrict them).
        if ($module && strtoupper($user->role) === $module) {
            if (in_array($user->position, ['manager', 'staff'])) {
                if (! $this->hasExplicitModuleGrants($user, $module)) {
                    return $next($request);
                }
                // Fall through to the strict row check below.
            }
        }

        // Secretaries and Special Officers have full access to their root module
        if (in_array($user->position, ['secretary', 'special_officer'])) {
            $rootModule = $this->getRootModuleForUser($user);
            if ($module && $rootModule && strtoupper($module) === $rootModule) {
                return $next($request);
            }
        }

        // Vice President: no HRM pages — the COO role is standalone (own VP
        // module + full CRM, like the President) and has no job in HRM.
        if ($module && strtoupper($module) === 'HRM'
            && (in_array($user->role, ['COO'], true)
                || ($user->position ?? '') === 'vice_president')) {
            abort(403, "You do not have permission to access the '{$page}' page in the {$module} module.");
        }

        // President & Vice President have full (edit) access to every CRM
        // page. An explicit 'disabled' row (handled above) still wins, so
        // IT can revoke this per page when needed.
        if ($module && strtoupper($module) === 'CRM'
            && (in_array($user->role, ['CEO', 'COO'], true)
                || ($user->position ?? '') === 'vice_president')) {
            return $next($request);
        }

        // Fallback: HRM staff can always view the dashboard (legacy)
        if ($page === 'dashboard' && $user->role === 'HRM' && $user->position === 'staff') {
            return $next($request);
        }

        if (!$module) {
            abort(403, 'Could not determine module for permission check.');
        }

        // Check the user's explicit permission record for this page and
        // enforce the required level ('edit' implies 'view').
        // NOTE: first() (not value()) is used so a legacy row whose
        // permission_level is NULL is still recognised as a grant.
        // Module/page matching is case-tolerant: writers store UPPER module
        // codes but legacy rows may differ.
        // ($record already loaded above so the 'disabled' short-circuit ran
        // before any native shortcut.)
        if ($record !== null) {
            // Legacy rows created before permission_level existed have NULL;
            // grandfather them as 'edit' to avoid locking out existing users.
            $grantedLevel = strtolower($record->permission_level ?? 'edit');

            $sufficient = $requiredLevel === 'view'
                ? in_array($grantedLevel, ['view', 'edit'], true)
                : $grantedLevel === 'edit';

            if ($sufficient) {
                return $next($request);
            }

            abort(403, "The '{$page}' page in the {$module} module requires '{$requiredLevel}' permission; this account only has '{$grantedLevel}'.");
        }

        abort(403, "You do not have permission to access the '{$page}' page in the {$module} module.");
    }

    /**
     * Whether the user has ANY explicit page_permissions rows for a module
     * (case-tolerant). Used to decide if explicit grants override the
     * native manager/staff full-access shortcut.
     */
    protected function hasExplicitModuleGrants($user, string $module): bool
    {
        if (! method_exists($user, 'pagePermissions')) {
            return false;
        }

        return $user->pagePermissions()
            ->whereIn('module', [$module, strtoupper($module), strtolower($module)])
            ->exists();
    }

    /**
     * Detect the module name from the request URI or route name.
     *
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    protected function detectModule(Request $request): ?string
    {
        $path = $request->path();
        $routeName = $request->route() ? $request->route()->getName() : '';

        // 1. Try route name (first segment before dot)
        if ($routeName) {
            $parts = explode('.', $routeName);
            $firstPart = strtoupper($parts[0] ?? '');
            // Staff-side applicant master reuses the canonical HRM
            // `application` page (routes/Applicants.php) — resolve to HRM.
            // Portal routes are `applicant.*` (singular) and never match.
            if ($firstPart === 'APPLICANTS') {
                return 'HRM';
            }
            if ($this->isValidModule($firstPart)) {
                return $firstPart;
            }
        }

        // 1b. Staff-side applicant master by path (dashboard/applicants*,
        // applicants/* profile alias) — also HRM. The public applicant
        // portal lives under singular `applicant/*` and never matches.
        if (preg_match('#^(dashboard/applicants|applicants)(/|$)#i', $path)) {
            return 'HRM';
        }

        // 2. Check for /dashboard/{module} pattern
        if (preg_match('#^dashboard/(hrm|crm|man|log|eco|ord|scm|war|inv|pro|wrf|fin|proj|it|ceo)#i', $path, $matches)) {
            return strtoupper($matches[1]);
        }

        // 3. Check for direct module prefix (e.g., /hrm/...)
        if (preg_match('#^(hrm|crm|man|log|eco|ord|scm|war|inv|pro|wrf|fin|proj|it|ceo)/#i', $path, $matches)) {
            return strtoupper($matches[1]);
        }

        // 4. Special case for CEO access routes
        if (str_starts_with($path, 'dashboard/ceo')) {
            return 'CEO';
        }

        return null;
    }

    /**
     * Check if a string is a valid module identifier.
     *
     * @param string $module
     * @return bool
     */
    protected function isValidModule(string $module): bool
    {
        $validModules = [
            'HRM', 'CRM', 'MAN', 'LOG', 'ECO', 'ORD', 'SCM',
            'WAR', 'INV', 'PRO', 'WRF', 'FIN', 'PROJ', 'IT', 'CEO'
        ];
        return in_array($module, $validModules);
    }

    /**
     * Get the root module for a secretary or general manager.
     * Mirrors the logic in CeoAccessController.
     *
     * @param \App\Models\Core\User $user
     * @return string|null
     */
    protected function getRootModuleForUser($user): ?string
    {
        $coreModules = ['HRM', 'CRM', 'MAN', 'LOG'];

        if ($user->is_manufacturing_supervisor) {
            return 'MAN';
        }

        $roleUpper = strtoupper($user->role);
        foreach ($coreModules as $core) {
            if (str_contains($roleUpper, $core)) {
                return $core;
            }
        }

        return null;
    }
}