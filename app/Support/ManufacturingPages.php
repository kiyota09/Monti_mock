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

    /** Supervisor-owned pages shown separately from department staff pages. */
    public const SUPERVISOR_ACCOUNT_PAGES = [
        'knitting' => [
            'dashboard' => 'Dashboard',
            'production' => 'Production Orders',
            'assignments' => 'Job Order Assignments',
            'reject' => 'Rejected Items',
            'inventory' => 'Production Inventory',
            'recipes' => 'Recipes',
            'knitting_machines' => 'Knitting Machines',
        ],
        'dyeing' => [
            'dashboard' => 'Dashboard',
            'production' => 'Production Orders',
            'assignments' => 'Job Order Assignments',
            'reject' => 'Rejected Items',
            'inventory' => 'Production Inventory',
            'recipes' => 'Recipes',
            'dyeing_machines' => 'Dyeing Machines',
            'dyeing_workflow' => 'Dyeing Workflow',
        ],
        'finishing' => [
            'dashboard' => 'Dashboard',
            'production' => 'Production Orders',
            'assignments' => 'Job Order Assignments',
            'reject' => 'Rejected Items',
            'inventory' => 'Production Inventory',
            'recipes' => 'Recipes',
            'finishing_machines' => 'Finishing Machines',
            'finishing_workflow' => 'Finishing Workflow',
        ],
        'maintenance' => self::OVERVIEW,
        'boiler' => [
            'dashboard' => 'Dashboard',
            'production' => 'Production Orders',
            'reject' => 'Rejected Items',
            'inventory' => 'Production Inventory',
            'recipes' => 'Recipes',
            'boiler_machines' => 'Boiler Machines',
        ],
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
        'knitting_inspector' => [
            'dashboard' => 'Dashboard',
            'knitting_inspector' => 'Knitting Inspection',
            'history' => 'My History',
            'reports' => 'Reports',
        ],
        'knitting_sample' => [
            'dashboard' => 'Dashboard',
            'knitting_sample' => 'Knitting Sample',
            'history' => 'My History',
            'reports' => 'Reports',
        ],
        'dyeing_color' => [
            'dashboard' => 'Dashboard',
            'dyeing_color' => 'Dyeing Color',
            'history' => 'My History',
            'reports' => 'Reports',
        ],
        'dyeing_bleaching' => [
            'dashboard' => 'Dashboard',
            'dyeing_bleaching' => 'Dyeing Bleaching',
            'history' => 'My History',
            'reports' => 'Reports',
        ],
        'dyeing_pre_treatment' => [
            'dashboard' => 'Dashboard',
            'dyeing_pre_treatment' => 'Dyeing Pre Treatment',
            'history' => 'History',
            'reports' => 'Reports',
        ],
        'dyeing_treatment' => [
            'dashboard' => 'Dashboard',
            'dyeing_treatment' => 'Dyeing Post Treatment',
            'history' => 'History',
            'reports' => 'Reports',
        ],
        'finishing_squeezer' => [
            'dashboard' => 'Dashboard',
            'finishing_squeezer' => 'Finishing Squeezer',
            'history' => 'My History',
            'reports' => 'Reports',
        ],
        'finishing_ironing' => [
            'dashboard' => 'Dashboard',
            'finishing_ironing' => 'Finishing Heatsetting',
            'history' => 'My History',
            'reports' => 'Reports',
        ],
        'finishing_hydro' => [
            'dashboard' => 'Dashboard',
            'finishing_hydro' => 'Finishing Hydro',
            'history' => 'History',
            'reports' => 'Reports',
        ],
        'finishing_tubetex' => [
            'dashboard' => 'Dashboard',
            'finishing_tubetex' => 'Finishing Tubetex',
            'history' => 'History',
            'reports' => 'Reports',
        ],
        'dyeing_lab_chemist' => [
            'dashboard' => 'Dashboard',
            'dyeing_lab_chemist' => 'Color Request',
            'history' => 'My History',
            'pantones' => 'Pantones',
            'knit_fabric_requests' => 'Receive Fabric Sample Knit',
            'lab_tests' => 'Tests & CoA',
            'lab_transfer' => 'Bulk Transfer',
            'lab_inventory' => 'Lab Inventory',
            'recipes' => 'Recipes',
        ],
        'finishing_packaging' => [
            'dashboard' => 'Dashboard',
            'finishing_packaging' => 'Finishing Packaging',
            'history' => 'My History',
            'reports' => 'Reports',
        ],
        'finishing_inspector' => [
            'dashboard' => 'Dashboard',
            'finishing_inspector' => 'Finishing Shrinkage Inspection',
            'history' => 'History',
            'reports' => 'Reports',
        ],
        'checker_quality' => [
            'dashboard' => 'Dashboard',
            'checker_quality' => 'Finishing Quality Checker',
            'history' => 'History',
            'reports' => 'Reports',
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
        'knitting' => ['knitting_yarn', 'knitting_mechanic', 'knitting_inspector', 'knitting_sample'],
        'dyeing' => [
            'dyeing_color', 'dyeing_bleaching', 'dyeing_pre_treatment', 'dyeing_treatment', 'dyeing_lab_chemist',
        ],
        'finishing' => ['finishing_squeezer', 'finishing_ironing', 'finishing_hydro', 'finishing_tubetex', 'finishing_packaging', 'finishing_inspector', 'checker_quality'],
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
     * Page catalog available to IT when configuring one department supervisor.
     * The supervisor can be granted access to any workspace handled by their
     * department's staff, while other departments' staff pages stay excluded.
     */
    public static function forSupervisorDepartment(?string $department): array
    {
        $pages = self::OVERVIEW;
        foreach (self::DEPARTMENT_ROLES[$department] ?? [] as $role) {
            foreach (self::ROLE_PAGES[$role] ?? [] as $key => $label) {
                $pages[$key] ??= $label;
            }
        }

        // These page keys are shared by all staff workspaces in the department.
        foreach (['dashboard' => 'Staff Dashboards', 'history' => 'Staff History', 'reports' => 'Staff Reports'] as $key => $label) {
            if (array_key_exists($key, $pages)) {
                $pages[$key] = $label;
            }
        }

        return $pages;
    }

    public static function forSupervisorAccountDepartment(?string $department): array
    {
        return self::SUPERVISOR_ACCOUNT_PAGES[$department] ?? self::OVERVIEW;
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

    /** Resolve the staff workspace role from its canonical named route. */
    public static function roleForRouteName(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        foreach (array_keys(self::ROLE_PAGES) as $role) {
            $slug = $role === 'pollution_control_operator'
                ? 'pollution-control'
                : str_replace('_', '-', $role);
            if (str_starts_with($routeName, 'man.staff.' . $slug . '.')) {
                return $role;
            }
        }

        return null;
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
