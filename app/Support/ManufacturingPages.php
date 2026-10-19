<?php

namespace App\Support;

use App\Models\Core\User;

/**
 * Canonical page catalog for the Manufacturing (MAN) module.
 *
 * IT Access Control needs to expose every page a plant employee actually
 * works with. Historically all staff workspaces were gated by the single
 * "production" page, so IT could not grant e.g. only "Tests & CoA" to a
 * Dyeing Lab Chemist. This class is the single source of truth for the
 * role -> page mapping used by:
 *  - routes/Man.php (page.permission middleware)
 *  - ItAccessControlController (page list + seeding)
 *  - HandleInertiaRequests (native access auto-grant)
 *  - resources/js/Layouts/modules/SidebarMan.vue (menu filtering)
 *  - resources/js/Pages/Dashboard/IT/Manager/ItAccessControl.vue
 */
class ManufacturingPages
{
    /**
     * Shared overview pages (department supervisors / legacy MAN staff).
     */
    public const OVERVIEW = [
        'dashboard' => 'Dashboard',
        'production' => 'Production Orders',
        'reject' => 'Rejected Items',
        'inventory' => 'Production Inventory',
        'recipes' => 'Recipes',
    ];

    /**
     * Role slug => ordered page catalog (key => label).
     *
     * The work-page key is the role slug itself so the route, middleware and
     * IT Access Control agree on one identifier.
     */
    public const ROLE_PAGES = [
        'knitting_yarn' => [
            'dashboard' => 'Dashboard',
            'knitting_yarn' => 'Knitting Yarn',
            'history' => 'My History',
            'reports' => 'Reports',
            'flag_reports' => 'Flag Reports',
        ],
        'knitting_mechanic' => [
            'dashboard' => 'Dashboard',
            'knitting_mechanic' => 'Machine Setups',
            'history' => 'My History',
            'reports' => 'Reports',
        ],
        'dyeing_color' => [
            'dashboard' => 'Dashboard',
            'dyeing_color' => 'Dyeing Color',
            'history' => 'My History',
            'reports' => 'Reports',
        ],
        'dyeing_fabric_softener' => [
            'dashboard' => 'Dashboard',
            'dyeing_fabric_softener' => 'Fabric Softener',
            'history' => 'My History',
            'reports' => 'Reports',
        ],
        'dyeing_squeezer' => [
            'dashboard' => 'Dashboard',
            'dyeing_squeezer' => 'Dyeing Squeezer',
            'history' => 'My History',
            'reports' => 'Reports',
        ],
        'dyeing_ironing' => [
            'dashboard' => 'Dashboard',
            'dyeing_ironing' => 'Dyeing Ironing',
            'history' => 'My History',
            'reports' => 'Reports',
        ],
        'dyeing_lab_chemist' => [
            'dashboard' => 'Dashboard',
            'dyeing_lab_chemist' => 'Shade Development',
            'lab_tests' => 'Tests & CoA',
            'lab_inventory' => 'Lab Inventory',
            'lab_transfer' => 'Bulk Transfer',
            'history' => 'My History',
            'recipes' => 'Recipes',
        ],
        'finishing_packaging' => [
            'dashboard' => 'Dashboard',
            'finishing_packaging' => 'Finishing Packaging',
            'history' => 'My History',
            'reports' => 'Reports',
        ],
        'checker_quality' => [
            'dashboard' => 'Dashboard',
            'checker_quality' => 'Quality Inspection',
        ],
        'maintenance_checker' => [
            'dashboard' => 'Dashboard',
            'maintenance_checker' => 'Maintenance',
            'reports' => 'Reports',
        ],
        'pollution_control_operator' => [
            'dashboard' => 'Dashboard',
            'pollution_control' => 'Pollution Control Logs',
            'history' => 'My History',
        ],
        'safety_officer' => [
            'dashboard' => 'Dashboard',
            'safety_officer' => 'Safety Incidents',
            'history' => 'My History',
        ],
        'boiler_operator' => [
            'dashboard' => 'Dashboard',
            'boiler_operator' => 'Boiler Logs',
            'history' => 'My History',
            'reports' => 'Reports',
        ],
    ];

    /**
     * Department slug => manufacturing_role slugs (mirrors
     * ManufacturingRoles::DEPARTMENTS; kept here for supervisor scoping).
     */
    public const DEPARTMENT_ROLES = [
        'knitting' => ['knitting_yarn', 'knitting_mechanic'],
        'dyeing' => [
            'dyeing_color', 'dyeing_fabric_softener', 'dyeing_squeezer',
            'dyeing_ironing', 'dyeing_lab_chemist',
        ],
        'finishing' => ['finishing_packaging', 'checker_quality'],
        'maintenance' => [
            'maintenance_checker', 'pollution_control_operator', 'safety_officer',
        ],
        'boiler' => ['boiler_operator'],
    ];

    /**
     * Every MAN page (overview + all roles), keyed by page key.
     */
    public static function allPages(): array
    {
        $pages = self::OVERVIEW;
        foreach (self::ROLE_PAGES as $rolePages) {
            $pages += $rolePages;
        }

        return $pages;
    }

    /**
     * @return array<int, string>
     */
    public static function allPageKeys(): array
    {
        return array_keys(self::allPages());
    }

    /**
     * Ordered page catalog for one manufacturing_role slug.
     */
    public static function forRole(?string $role): array
    {
        if (! $role) {
            return self::OVERVIEW;
        }

        return self::ROLE_PAGES[$role] ?? self::OVERVIEW;
    }

    /**
     * Ordered page catalog for a specific employee.
     *
     * Supervisors and MAN accounts without a plant role keep the shared
     * overview (their sidebar opens staff workspaces through the department
     * dropdowns, which are Production-gated).
     */
    public static function forUser(?User $user): array
    {
        if (! $user) {
            return self::OVERVIEW;
        }
        if ($user->is_manufacturing_supervisor) {
            return self::OVERVIEW;
        }
        if (strtoupper((string) $user->role) !== 'MAN') {
            return self::OVERVIEW;
        }

        return self::forRole($user->manufacturing_role);
    }

    /**
     * Work-page key for a manufacturing_role (the role slug itself), or null.
     */
    public static function workspaceKeyFor(?string $role): ?string
    {
        if (! $role) {
            return null;
        }

        return isset(self::ROLE_PAGES[$role]) ? $role : null;
    }

    /**
     * Whether a page key belongs to a role workspace (not the shared
     * overview). Used by CheckPagePermission for supervisor/legacy
     * Production grants.
     */
    public static function isRolePage(?string $page): bool
    {
        if (! $page) {
            return false;
        }
        $key = strtolower($page);
        if (array_key_exists($key, self::OVERVIEW)) {
            return false;
        }

        return array_key_exists($key, self::allPages());
    }

    /**
     * Role slugs supervised in a department.
     *
     * @return array<int, string>
     */
    public static function rolesForDepartment(?string $department): array
    {
        if (! $department) {
            return [];
        }

        return self::DEPARTMENT_ROLES[$department] ?? [];
    }
}
