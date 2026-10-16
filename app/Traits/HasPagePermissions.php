<?php

namespace App\Traits;

use App\Models\Core\PagePermission;

trait HasPagePermissions
{
    /**
     * Get page permissions for the current user for a specific module.
     * Returns an array of [page => permission_level] (view/edit).
     *
     * - CEO gets full edit on all pages, in every module.
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

        // CEO bypass: full edit on all pages
        if ($user->role === 'CEO') {
            $pages = array_keys(config('module_pages.' . strtolower($module), []));
            return array_fill_keys($pages, 'edit');
        }

        // Vice President bypass: full edit on all CRM pages.
        if (strtoupper($module) === 'CRM'
            && (($user->position ?? '') === 'vice_president' || $user->role === 'COO')) {
            $pages = array_keys(config('module_pages.' . strtolower($module), []));
            return array_fill_keys($pages, 'edit');
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