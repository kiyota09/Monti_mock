<?php

namespace App\Http\Middleware;

use App\Models\Core\PagePermission;
use App\Models\Core\User;
use App\Models\Work\WorkforcePermission;
use App\Models\Crm\CrmPagePermission;
use App\Models\Core\UserModuleAccess;
use App\Models\Crm\CrmClientAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        $userData = null;
        $pagePermissionsList = [];
        $assignedClientIds = [];

        // Portal guards (client / supplier / applicant) authenticate with
        // their own models — none of the employee permission machinery
        // below applies to them. Their data is shared via getGuardUser().
        // Without this gate, any portal visit crashes on employee-only
        // relations (e.g. hrmDepartment) with a RelationNotFoundException.
        if ($user instanceof User) {
            // 1. Fetch explicit permissions from DB (raw — includes 'disabled'
            //    rows so the auto-grant check below sees the exact access set).
            $explicitPermissions = PagePermission::where('user_id', $user->id)
                ->get(['module', 'page', 'permission_level'])
                ->toArray();

            // 2. Auto‑grant full access for module managers/staff on their own module
            //    (skipped when ANY explicit rows exist for that module — even
            //    all-'disabled' ones, which mean "no access yet").
            $augmentedPermissions = $this->augmentPermissionsForModuleUser($user, $explicitPermissions);

            // 3. Drop 'disabled' rows — they grant nothing. The frontend treats
            //    presence in this list as a grant (sidebar filtering, usePageAccess),
            //    so a disabled row must never be shared. A user whose every row
            //    is disabled therefore shares an empty list → empty sidebar →
            //    AwaitingAccess landing page.
            $usablePermissions = array_values(array_filter(
                $augmentedPermissions,
                fn ($perm) => strtolower($perm['permission_level'] ?? 'edit') !== 'disabled'
            ));

            // ---- View/edit levels are significant ----
            // Every entry keeps its real permission_level ('view' or 'edit';
            // legacy NULL rows are treated as 'edit'). The frontend uses these
            // levels to hide mutating buttons from view-only users, while
            // CheckPagePermission enforces them on the backend. Presence-only
            // consumers (sidebar filtering) are unaffected.
            $pagePermissionsList = array_map(function ($perm) {
                $perm['permission_level'] = strtolower($perm['permission_level'] ?? 'edit');
                if (! in_array($perm['permission_level'], ['view', 'edit'], true)) {
                    $perm['permission_level'] = 'edit';
                }
                return $perm;
            }, $usablePermissions);

            // 3. Group by module (for backward compatibility with frontend consumers)
            $permissionsGrouped = collect($pagePermissionsList)
                ->groupBy('module')
                ->map(fn ($perms) => $perms->pluck('page'));

            // 4. Modules with ANY explicit row (including 'disabled'). The
            //    sidebar uses this to decide whether explicit grants override
            //    the native manager/staff full-access shortcut — mirroring
            //    CheckPagePermission: explicit rows are the exact access set.
            $explicitModules = collect($explicitPermissions)
                ->map(fn ($perm) => strtoupper((string) ($perm['module'] ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();

            // Fetch workforce permissions
            $workforcePermissions = WorkforcePermission::where('user_id', $user->id)->get();

            // Fetch CRM page permissions
            $crmPagePermissions = [];
            if (in_array($user->role, ['CRM', 'CEO', 'COO'])) {
                $crmPagePermissions = CrmPagePermission::where('user_id', $user->id)->pluck('page')->toArray();
            }

            // Fetch granted modules for secretary/general manager
            $grantedModules = UserModuleAccess::where('user_id', $user->id)->pluck('module')->toArray();

            // Fetch assigned client IDs for CRM staff
            if ($user->role === 'CRM' && $user->position === 'staff') {
                $assignedClientIds = CrmClientAssignment::where('staff_id', $user->id)
                    ->pluck('client_id')
                    ->toArray();
            }

            // Department display name (HRM-assigned department first, then the
            // legacy department string, then manufacturing supervisor scope,
            // then a role-derived label) so profile/sidebar consumers never
            // render a blank department for module-native accounts.
            $user->loadMissing('hrmDepartment:id,name');

            // Merge user attributes with permissions
            $userData = array_merge($user->toArray(), [
                'department_name'        => $this->resolveDepartmentName($user),
                'permissions'            => $permissionsGrouped,
                'workforce_permissions'  => $workforcePermissions,
                'crmPagePermissions'     => $crmPagePermissions,
                'granted_modules'        => $grantedModules,
                'page_permissions'       => $pagePermissionsList, // usable (view/edit) grants only
                'explicit_modules'       => $explicitModules, // modules with ANY row, incl. disabled
                'root_module'            => $this->getRootModuleForUser($user), // secretary/GM home module
            ]);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user'                => $userData,
                'page_permissions'    => $pagePermissionsList,
                'assigned_client_ids' => $assignedClientIds,
                'client'              => $this->getGuardUser('client'),
                'supplier'            => $this->getGuardUser('supplier'),
                'applicant'           => $this->getGuardUser('applicant'),
                'agency'              => $this->getGuardUser('agency'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
            ],
            // Executive inbox badge (CEO role only; everyone else gets 0).
            'notifications_unread' => function () use ($user) {
                if (! $user || ($user->role ?? null) !== 'CEO') {
                    return 0;
                }

                return \App\Models\Ceo\ExecutiveNotification::where('is_read', false)->count();
            },
        ];
    }

    protected function getGuardUser(string $guard)
    {
        try {
            return Auth::guard($guard)->user();
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    /**
     * Augment the permission list so that managers/staff of a module get
     * full access on all pages of that module — UNLESS explicit permission
     * rows already exist for them in that module, in which case the explicit
     * grants are the exact access set (no auto-grant of the remaining pages).
     */
    protected function augmentPermissionsForModuleUser($user, array $explicitPermissions): array
    {
        $result = $explicitPermissions;

        // President & Vice President hold full (edit) access on every CRM
        // page. Rows are injected here so ALL frontend consumers
        // (usePageAccess, sidebar filters, grouped permissions) see them —
        // mirroring the CheckPagePermission bypass. An explicit row for a
        // page (including IT's 'disabled') always wins and is never
        // overwritten; other modules are untouched (overseer model).
        if ($user->role === 'CEO'
            || $user->role === 'COO'
            || ($user->position ?? '') === 'vice_president') {
            foreach ($this->getModulePages('CRM') as $page) {
                $hasRow = collect($result)->first(fn ($perm) =>
                    strtoupper((string) ($perm['module'] ?? '')) === 'CRM'
                    && strtolower((string) ($perm['page'] ?? '')) === strtolower($page));
                if (! $hasRow) {
                    $result[] = [
                        'module'           => 'CRM',
                        'page'             => $page,
                        'permission_level' => 'edit',
                    ];
                }
            }
        }

        $module = strtoupper($user->role);
        $position = $user->position;

        if (!in_array($position, ['manager', 'staff'])) {
            return $result;
        }

        $hasExplicit = collect($explicitPermissions)->contains(function ($perm) use ($module) {
            return strtoupper((string) ($perm['module'] ?? '')) === $module;
        });

        if ($hasExplicit) {
            return $result;
        }

        $modulePages = $this->getModulePages($module);

        foreach ($modulePages as $page) {
            $exists = collect($result)->firstWhere(function ($perm) use ($module, $page) {
                return $perm['module'] === $module && $perm['page'] === $page;
            });

            if (!$exists) {
                $result[] = [
                    'module'           => $module,
                    'page'             => $page,
                    'permission_level' => 'edit', // full access
                ];
            }
        }

        return $result;
    }

    /**
     * Home (root) module for a secretary / special officer — mirrors
     * CheckPagePermission::getRootModuleForUser(). Only this module carries
     * automatic full access; extra granted modules are filtered per page.
     */
    protected function getRootModuleForUser($user): ?string
    {
        $coreModules = ['HRM', 'CRM', 'MAN', 'LOG'];

        if (! empty($user->is_manufacturing_supervisor)) {
            return 'MAN';
        }

        $roleUpper = strtoupper($user->role ?? '');
        foreach ($coreModules as $core) {
            if (str_contains($roleUpper, $core)) {
                return $core;
            }
        }

        return null;
    }

    /**
     * Display name for the employee's department: HRM-assigned department
     * name first, then the legacy `department` string (populated at
     * self-registration), then the manufacturing supervisor's scoped
     * department, then a role-derived label (IT Department, HR Department,
     * Customer Relationship Department, …). Null only when nothing is known.
     */
    protected function resolveDepartmentName($user): ?string
    {
        $hrmName = $user->hrmDepartment?->name;
        if (is_string($hrmName) && trim($hrmName) !== '') {
            return trim($hrmName);
        }

        $dept = $user->department;
        if (is_string($dept) && trim($dept) !== '') {
            return trim($dept);
        }

        if (! empty($user->is_manufacturing_supervisor) && ! empty($user->supervisor_department)) {
            return ucfirst((string) $user->supervisor_department).' Department';
        }

        $map = [
            'IT'  => 'IT Department',
            'HRM' => 'HR Department',
            'CRM' => 'Customer Relationship Department',
            'SCM' => 'Supply Chain Department',
            'FIN' => 'Finance Department',
            'MAN' => 'Manufacturing Department',
            'LOG' => 'Logistics Department',
            'WAR' => 'Warehouse Department',
            'INV' => 'Inventory Department',
            'ORD' => 'Order Management Department',
            'ECO' => 'E-Commerce Department',
            'PRO' => 'Procurement Department',
            'PROJ' => 'Project Department',
            'WRF' => 'Workforce Department',
            'CEO' => 'Office of the CEO',
            'COO' => 'Office of the COO',
        ];

        $role = strtoupper((string) ($user->role ?? ''));
        if (isset($map[$role])) {
            return $map[$role];
        }
        if ($role !== '') {
            return ucfirst(strtolower($role)).' Department';
        }

        return null;
    }

    /**
     * Get all page names for a given module.
     */
    protected function getModulePages(string $module): array
    {
        $map = [
            'HRM' => ['dashboard', 'employee', 'application', 'interview', 'trainee', 'onboarding', 'payroll', 'analytics'],
            'CRM' => ['dashboard', 'leads', 'customer_profiles', 'opportunities', 'inquiry', 'approvals', 'quotations', 'activities', 'cases', 'campaigns', 'investigation'],
            'SCM' => ['dashboard', 'sales', 'procurement', 'planning', 'purchase', 'deliveries', 'vendor', 'analytics'],
            'FIN' => ['dashboard', 'receivables', 'payables', 'expenses', 'payroll', 'reports', 'approvals'],
            'MAN' => ['dashboard', 'production', 'reject', 'inventory'],
            'INV' => ['dashboard', 'materials', 'products', 'bom', 'checker'],
            'ORD' => ['dashboard', 'orders', 'productions', 'delivery', 'returns'],
            'WAR' => ['warehouse', 'receiving', 'monitor', 'packages', 'reject'],
            'ECO' => ['dashboard', 'store', 'supplier', 'client', 'credit', 'push'],
            'PRO' => ['dashboard', 'requests', 'quotations', 'receipt', 'tracking'],
            'PROJ' => ['dashboard'],
            'IT' => ['dashboard', 'tickets', 'assets', 'monitoring', 'knowledge', 'changes', 'access', 'access_control', 'access_logs'],
            'LOG' => ['dashboard', 'load', 'dispatch', 'fleet', 'drivers', 'routes', 'tracking', 'proof', 'reports'],
            'WRF' => ['dashboard', 'scheduler', 'leave', 'absent'],
        ];

        return $map[$module] ?? [];
    }
}