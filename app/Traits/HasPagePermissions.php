<?php

namespace App\Traits;

use App\Models\Core\PagePermission;

trait HasPagePermissions
{
    /**
     * Get page permissions for the current user for a specific module.
     * Returns an array of [page => permission_level] (view/edit).
     *
     * - A manager of the module itself (user->role === $module,
     *   user->position === 'manager') gets full edit on all pages
     *   within that module, same as CEO.
     * - Everyone else falls back to their explicit PagePermission records.
     */
    protected function getPagePermissionsForModule(string $module): array
    {
        $user = auth()->user();

        if (!$user) {
            return [];
        }

        // Module manager bypass: a manager is automatically granted full
        // edit access to every page within their own module — UNLESS explicit
        // PagePermission rows exist for them, in which case those rows are
        // the exact access set (mirrors CheckPagePermission).
        if (strtoupper($user->role) === strtoupper($module) && $user->position === 'manager') {
            $hasExplicit = PagePermission::where('user_id', $user->id)
                ->whereIn('module', [$module, strtoupper($module), strtolower($module)])
                ->exists();
            if (! $hasExplicit) {
                $pages = array_keys(config('module_pages.' . strtolower($module), []));
                return array_fill_keys($pages, 'edit');
            }
        }

        $permissions = PagePermission::where('user_id', $user->id)
            ->where('module', $module)
            ->get()
            ->pluck('permission_level', 'page')
            ->toArray();

        return $permissions;
    }
}
