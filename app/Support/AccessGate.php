<?php

namespace App\Support;

use App\Models\Core\PagePermission;
use App\Models\Core\UserModuleAccess;
use App\Models\Work\WorkforcePermission;

/**
 * Shared "does this account hold ANY usable grant?" check.
 *
 * Only 'view' / 'edit' PagePermission rows count — 'disabled' rows (the
 * IT-seeded default) grant nothing, and neither does a module-access row
 * on its own once an explicit per-page set exists. Native manager/staff
 * auto-access applies solely when their own module has NO explicit rows;
 * the moment IT seeds rows for that module, those rows are the exact
 * access set (mirrors CheckPagePermission). With zero usable grants the
 * sidebars render empty and the user belongs on AwaitingAccess instead of
 * a module page that would only 403.
 */
class AccessGate
{
    public static function hasAnyAccess($user): bool
    {
        if (! $user) {
            return false;
        }

        $position = strtolower($user->position ?? '');

        // Dedicated workspaces outside the page-permission model.
        if (in_array($position, ['secretary', 'special_officer', 'vice_president', 'trainee'], true)) {
            return true;
        }

        // The President oversees through the CEO module, never page grants.
        if (strtoupper($user->role ?? '') === 'CEO') {
            return true;
        }

        // Any usable (view/edit, legacy NULL grandfathered as edit) page grant.
        if (static::hasUsablePageGrants($user)) {
            return true;
        }

        // Workforce grants.
        if (WorkforcePermission::where('user_id', $user->id)->exists()) {
            return true;
        }

        $hasAnyExplicit = PagePermission::where('user_id', $user->id)->exists();

        if (! $hasAnyExplicit) {
            // No explicit per-page set anywhere: legacy boolean module flags
            // still count (pre-seeding accounts).
            foreach (['has_warehouse_access', 'has_inventory_access', 'has_ord_access', 'logistics_access'] as $flag) {
                if (! empty($user->{$flag})) {
                    return true;
                }
            }

            // A module-access row with zero explicit page rows (legacy) counts.
            if (UserModuleAccess::where('user_id', $user->id)->exists()) {
                return true;
            }

            // Native manager/staff (incl. MAN role holders and department
            // supervisors) auto-grant every page of their own module.
            if (in_array($position, ['manager', 'staff'], true)) {
                return true;
            }

            return false;
        }

        // Explicit rows exist somewhere: the native module still auto-grants,
        // but ONLY when that module itself has no explicit rows. (E.g. an HRM
        // staffer whose extra WAR grant is still all-'disabled' keeps HRM
        // access; a FIN staffer whose FIN pages are all-'disabled' does not.)
        $nativeModule = strtoupper($user->role ?? '');
        if ($nativeModule !== '' && in_array($position, ['manager', 'staff'], true)) {
            $hasNativeExplicit = PagePermission::where('user_id', $user->id)
                ->whereIn('module', [$nativeModule, strtolower($nativeModule), ucfirst(strtolower($nativeModule))])
                ->exists();
            if (! $hasNativeExplicit) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the user holds any usable page grant (view/edit; legacy NULL
     * levels grandfathered as edit, mirroring CheckPagePermission and
     * CheckModuleAccess). Module matching is case-tolerant.
     */
    public static function hasUsablePageGrants($user, ?string $module = null): bool
    {
        $query = PagePermission::where('user_id', $user->id);

        if ($module !== null) {
            $query->whereIn('module', [$module, strtoupper($module), strtolower($module)]);
        }

        return $query->where(function ($q) {
            $q->whereIn('permission_level', ['view', 'edit'])
                ->orWhereNull('permission_level');
        })->exists();
    }
}
