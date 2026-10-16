<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        // NOTE: A previous revision skipped this gate entirely when
        // APP_ENV=local, which silently disabled module authorization on any
        // deployment running with that env value. The bypass has been removed:
        // authorization is now enforced in every environment. For local
        // development seeding, grant access via user_module_access rows instead.
        $user = Auth::user();

        if (! $user) {
            abort(403, 'Unauthorized');
        }

        // ── Admin has access to everything ──────────────────────────────────
        if (in_array($user->role, ['admin'])) {
            return $next($request);
        }

        // ── President & Vice President: full access to the CRM module ───────
        // The President (role CEO) and the Vice President (position
        // vice_president / role COO) may enter the CRM module shell. Every
        // page inside is still gated by CheckPagePermission, which grants
        // them full access there as well.
        if ($module === 'CRM' && in_array($user->role, ['CEO', 'COO'], true)) {
            return $next($request);
        }
        if ($module === 'CRM' && ($user->position ?? '') === 'vice_president') {
            return $next($request);
        }

        // ── Vice President: no HRM access ────────────────────────────────
        // The COO role is standalone (own VP module + full CRM, like the
        // President) and has no job in the HRM module — not even with a
        // legacy per-page grant row.
        if ($module === 'HRM'
            && (in_array($user->role, ['COO'], true)
                || ($user->position ?? '') === 'vice_president')) {
            abort(403, "You don't have access to the {$module} module.");
        }

        // ── Secretary or Special Officer: check granted_modules ───────────────
        // With no grants saved yet, they fall back to their home (root)
        // module — mirrors User::canAccessModule(). Once the CEO assigns
        // explicit grants, only those modules are allowed.
        if (in_array($user->position, ['secretary', 'special_officer'])) {
            $granted = $user->moduleAccess->pluck('module')->toArray();
            if (empty($granted) && $user->role === $module) {
                return $next($request);
            }
            if (in_array($module, $granted)) {
                return $next($request);
            }
            // A usable per-page grant is enough to enter the module shell —
            // the sidebar shows exactly those pages and page.permission still
            // gates each page. Without this, a Pages-only save (module row
            // missing) shows the item but 403s on open.
            if ($this->hasUsablePageGrants($user, $module)) {
                return $next($request);
            }
            abort(403, "You don't have access to the {$module} module.");
        }

        // ── Manufacturing Supervisor ──────────────────────────────────────────
        // Supervisors always have implicit access to the MAN module because
        // their entire role revolves around it. For any OTHER module they
        // must still have an explicit grant.
        if ($user->is_manufacturing_supervisor) {
            if ($module === 'MAN') {
                return $next($request);
            }

            // Non-MAN modules require an explicit grant (module row or a
            // usable per-page grant — same shell rule as everyone else).
            $granted = $user->moduleAccess->pluck('module')->toArray();
            if (in_array($module, $granted)) {
                return $next($request);
            }

            if ($this->hasUsablePageGrants($user, $module)) {
                return $next($request);
            }

            abort(403, "You don't have access to the {$module} module.");
        }

        // ── Explicit per-page grants ──────────────────────────────────────────
        // Anyone holding usable (view/edit) page rows for this module may
        // enter it — e.g. a manager granted pages of another module. The
        // sidebar shows exactly those pages and page.permission still gates
        // each page, so this grants no more than the explicit set.
        // 'disabled'-only sets grant nothing.
        if ($this->hasUsablePageGrants($user, $module)) {
            return $next($request);
        }

        // ── Regular manager: role must match module name ──────────────────────
        if ($user->role === $module && $user->position === 'manager') {
            return $next($request);
        }

        // ── Staff: role must match module name ────────────────────────────────
        // e.g. a MAN staff member has role = 'MAN' and position = 'staff'
        if ($user->role === $module && $user->position === 'staff') {
            return $next($request);
        }

        abort(403, "You don't have access to the {$module} module.");
    }

    /**
     * Whether the user holds any usable (view/edit) page grant for a module.
     * Legacy NULL levels are grandfathered as 'edit', mirroring
     * CheckPagePermission. Module matching is case-tolerant.
     */
    protected function hasUsablePageGrants($user, string $module): bool
    {
        if (! method_exists($user, 'pagePermissions')) {
            return false;
        }

        return $user->pagePermissions()
            ->whereIn('module', [$module, strtoupper($module), strtolower($module)])
            ->where(function ($q) {
                $q->whereIn('permission_level', ['view', 'edit'])
                    ->orWhereNull('permission_level');
            })
            ->exists();
    }
}