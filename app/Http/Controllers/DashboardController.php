<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Core\PagePermission;
use App\Models\Logistics\Driver;
use App\Support\AccessGate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $role = strtoupper($user->role);
        $position = strtolower($user->position);

        // ---- Redirect drivers and conductors to their portals ----
        if ($role === 'LOG' && $position === 'staff') {
            $isDriver = DB::table('drivers')->where('user_id', $user->id)->exists();
            $isConductor = DB::table('conductors')->where('user_id', $user->id)->exists();

            if (!$isDriver && !$isConductor && property_exists($user, 'log_role')) {
                if ($user->log_role === 'driver') $isDriver = true;
                if ($user->log_role === 'conductor') $isConductor = true;
            }

            // Auto-create driver record if user has log_role = 'driver' but no driver record
            if (!$isDriver && !$isConductor && property_exists($user, 'log_role') && $user->log_role === 'driver') {
                Driver::create([
                    'user_id' => $user->id,
                    'license_number' => 'DRV-' . strtoupper(uniqid()),
                    'is_available' => true,
                ]);
                $isDriver = true;
            }

            if ($isDriver) {
                return redirect()->route('logistics.driver.portal');
            }
            if ($isConductor) {
                return redirect()->route('logistics.conductor.portal');
            }
        }

        // ---- Trainee redirection ----
        if ($position === 'trainee') {
            return Inertia::render('Dashboard/TRAINEE/index', [
                'user' => $user,
                'stats' => [
                    'progress' => 45,
                    'assigned_modules' => 5,
                    'days_remaining' => 12,
                ],
            ]);
        }

        // ---- Secretary redirect (own executive workspace) ----
        if ($position === 'secretary') {
            return redirect()->route('secretary.dashboard');
        }

        // ---- Vice President redirect (own execution workspace) ----
        if ($user->position === 'vice_president') {
            return redirect()->route('vp.operations');
        }

        // ---- CEO redirect ----
        if ($role === 'CEO') {
            return redirect()->route('ceo.dashboard');
        }

        // ---- No-access gate ----
        // When IT has left every page on 'disabled' (the seeded default) and
        // the account holds no other grant, land on a waiting page instead of
        // bouncing the user into a module dashboard that would only 403.
        // The sidebar stays empty until an admin enables at least one page.
        if (! $this->hasAnyAccess($user)) {
            return Inertia::render('Dashboard/AwaitingAccess', [
                'user' => $user,
            ]);
        }

        // ---- Role & position to route mapping ----
        // Define the route name for each combination of role and position.
        // All route names must exist in web.php.
        $routeMap = [
            'HRM' => [
                'manager' => 'hrm.dashboard',
                'staff'   => 'hrm.dashboard',
                // secretaries/general managers are handled below
            ],
            'CRM' => [
                'manager' => 'crm.dashboard',
                'staff'   => 'crm.dashboard',
            ],
            'SCM' => [
                'manager' => 'scm.sales-orders',   // No separate dashboard, use sales orders
                'staff'   => 'scm.sales-orders',
            ],
            'FIN' => [
                'manager' => 'fin.manager.dashboard',
                'staff'   => 'fin.employee.dashboard',
            ],
            'MAN' => [
                'manager' => 'man.manager.dashboard',
                'staff'   => 'man.employee.dashboard', // this exists as a redirect to role-specific
            ],
            'INV' => [
                'manager' => 'inv.dashboard',
                'staff'   => 'inv.dashboard', // staff share the same dashboard
            ],
            'ORD' => [
                'manager' => 'ord.orders',
                'staff'   => 'ord.orders',
            ],
            'WAR' => [
                'manager' => 'warehouse.index',
                'staff'   => 'warehouse.index',
            ],
            'ECO' => [
                'manager' => 'eco.dashboard',
                'staff'   => 'eco.dashboard',
            ],
            'PRO' => [
                'manager' => 'pro.manager.dashboard',
                'staff'   => 'pro.manager.dashboard', // staff may not have a separate dashboard; adjust if needed
            ],
            'PROJ' => [
                'manager' => 'proj.manager.dashboard',
                'staff'   => 'proj.employee.dashboard',
            ],
            'IT' => [
                'manager' => 'it.dashboard',
                'staff'   => 'it.dashboard',
            ],
            'LOG' => [
                'manager' => 'logistics.dashboard',
                'staff'   => 'logistics.dashboard', // staff (non-driver/conductor) go to logistics dashboard
            ],
        ];

        // Check for secretary or special_officer positions – they might have granted modules,
        // but we'll redirect to the dashboard of their primary role if set, else to the generic dashboard.
        if (in_array($position, ['secretary', 'special_officer'])) {
            // If the user has a module access grant, we could redirect to that module's main page.
            // For simplicity, redirect to the root dashboard (or to a default).
            // But we can also use the role mapping if they have a primary role.
            if (isset($routeMap[$role])) {
                $default = $routeMap[$role]['manager'] ?? $routeMap[$role]['staff'] ?? null;
                if ($default) {
                    return redirect()->route($default);
                }
            }
            // Fallback for secretaries/GMs: go to CEO dashboard? Or generic dashboard view.
            return redirect()->route('dashboard'); // This route may not exist; better to use a fallback view.
        }

        // ---- Explicit-grant landing ----
        // IT may grant a subset that excludes the native dashboard (e.g. a FIN
        // staffer with receivables + payables only). The native landing below
        // would 403 for them, so send them to their first approved page
        // instead. Native auto-access (no explicit rows for the own module)
        // keeps the routing below unchanged.
        $explicitLanding = $this->explicitGrantLanding($user, $role, $position);
        if ($explicitLanding) {
            return redirect()->route($explicitLanding);
        }

        // Normal manager/staff redirection
        if (isset($routeMap[$role]) && isset($routeMap[$role][$position])) {
            return redirect()->route($routeMap[$role][$position]);
        }

        // ---- Fallback if no mapping found ----
        // Render a generic Inertia dashboard (if you have one) or redirect to home.
        return Inertia::render('Dashboard', [
            'stats' => [
                'total_tasks' => 0,
                'pending_tasks' => 0,
                'completed_tasks' => 0,
            ],
            'user' => $user,
        ]);
    }

    /**
     * Landing route for accounts holding an explicit per-page set.
     *
     * Returns null when the native landing still applies (no explicit rows
     * for the user's own module, or the native dashboard itself is granted).
     * Otherwise returns the first approved page's route — own module first
     * (sidebar order, dashboard preferred), then any other granted module —
     * so the user never lands on a page that would only 403. Unmapped pages
     * (e.g. parameterised routes) are skipped; nothing mapped → null.
     */
    protected function explicitGrantLanding($user, string $role, string $position): ?string
    {
        $nativeModule = strtoupper($role);

        $explicitModules = PagePermission::where('user_id', $user->id)
            ->distinct()
            ->pluck('module')
            ->map(fn ($m) => strtoupper((string) $m))
            ->all();

        $nativeUpper = strtoupper($nativeModule);
        if (! in_array($nativeUpper, $explicitModules, true)) {
            return null;
        }

        $usableByModule = PagePermission::where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereIn('permission_level', ['view', 'edit'])
                    ->orWhereNull('permission_level');
            })
            ->get(['module', 'page'])
            ->groupBy(fn ($row) => strtoupper((string) $row->module))
            ->map(fn ($rows) => $rows->map(fn ($r) => strtolower((string) $r->page))->unique()->values()->all())
            ->all();

        $orderedModules = array_values(array_unique(array_merge(
            [$nativeUpper],
            $explicitModules,
            array_keys($usableByModule)
        )));

        foreach ($orderedModules as $module) {
            foreach ($this->landingPagesFor($module, $position) as $page => $routeName) {
                if (! in_array($page, $usableByModule[$module] ?? [], true)) {
                    continue;
                }
                // MAN overview pages (except the staff entry dashboard) require
                // department-supervisor scope — plain MAN staff can only land
                // on their staff dashboard; their role pages live outside the
                // page-permission model.
                if ($module === 'MAN' && $page !== 'dashboard'
                    && strtolower($position) === 'staff'
                    && empty($user->is_manufacturing_supervisor)) {
                    continue;
                }
                // Plant staff entry is only a redirector into the
                // production-gated role workspace: a Dashboard-only grant
                // (production disabled/missing, no native auto-access) can
                // never open anything behind it, so skip it here and let the
                // user land on another usable page — or on the staff entry's
                // friendly limited-access notice instead of a raw 403.
                if ($module === 'MAN' && $page === 'dashboard'
                    && strtolower($position) === 'staff'
                    && empty($user->is_manufacturing_supervisor)
                    && ! $this->manRoleWorkspaceReachable($user)) {
                    continue;
                }
                if (Route::has($routeName)) {
                    return $routeName;
                }
            }
        }

        return null;
    }

    /**
     * Approved page → landing route map, in sidebar order (dashboard first).
     * Route names mirror the sidebar modules; parameterised routes (e.g.
     * warehouse.monitor) are intentionally absent.
     */
    protected function landingPagesFor(string $module, string $position): array
    {
        $isStaff = strtolower($position) === 'staff';

        $map = [
            'HRM' => [
                'dashboard' => 'hrm.dashboard',
                'employee' => 'hrm.employees.index',
                'application' => 'hrm.applications.index',
                'interview' => 'hrm.interview.index',
                'trainee' => 'hrm.trainee.index',
                'onboarding' => 'hrm.onboarding.index',
                'payroll' => 'hrm.payroll',
                'analytics' => 'hrm.analytics',
            ],
            'CRM' => [
                'dashboard' => 'crm.dashboard',
                'leads' => 'crm.lead',
                'customer_profiles' => 'crm.customerprofile.index',
                'opportunities' => 'crm.opportunities',
                'inquiry' => 'crm.inquiries',
                'approvals' => 'crm.approval.index',
                'quotations' => 'crm.quotations',
                'activities' => 'crm.activities',
                'cases' => 'crm.cases',
                'campaigns' => 'crm.campaigns',
                'investigation' => 'crm.investigation.index',
            ],
            'MAN' => [
                'dashboard' => $isStaff ? 'man.employee.dashboard' : 'man.manager.dashboard',
                'production' => 'man.manager.production',
                'reject' => 'man.manager.rejected',
                'inventory' => 'man.inventory.index',
            ],
            'LOG' => [
                'dashboard' => 'logistics.dashboard',
                'load' => 'logistics.load.index',
                'dispatch' => 'logistics.dispatch.index',
                'fleet' => 'logistics.fleet.index',
                'drivers' => 'logistics.drivers.index',
                'routes' => 'logistics.routes',
                'proof' => 'logistics.proof.index',
                'reports' => 'logistics.reports.index',
            ],
            'ECO' => [
                'dashboard' => 'eco.dashboard',
                'store' => 'eco.store',
                'supplier' => 'eco.suppliers',
                'client' => 'eco.clients',
                'credit' => 'eco.credit',
                'push' => 'eco.push',
            ],
            'ORD' => [
                'orders' => 'ord.orders',
                'productions' => 'ord.productions',
                'delivery' => 'ord.delivery',
            ],
            'SCM' => [
                'sales' => 'scm.sales-orders',
                'procurement' => 'scm.procurement-orders',
                'vendor' => 'scm.vendors',
            ],
            'WAR' => [
                'warehouse' => 'warehouse.index',
                'receiving' => 'warehouse.receiving',
                'packages' => 'warehouse.packages',
                'reject' => 'warehouse.rejects',
            ],
            'INV' => [
                'dashboard' => 'inv.dashboard',
                'materials' => 'inv.materials',
                'products' => 'inv.products',
                'bom' => 'inv.bom',
                'checker' => 'inv.checker',
            ],
            'PRO' => [
                'dashboard' => 'pro.manager.dashboard',
                'requests' => 'pro.manager.material-requests',
                'quotations' => 'pro.manager.supplier-quotations',
                'receipt' => 'pro.manager.receipt',
            ],
            'FIN' => [
                'dashboard' => $isStaff ? 'fin.employee.dashboard' : 'fin.manager.dashboard',
                'receivables' => 'fin.manager.receivables',
                'payables' => 'fin.manager.payables',
                'expenses' => 'fin.manager.expenses',
                'payroll' => 'fin.manager.payroll',
                'reports' => 'fin.manager.reports',
            ],
            'PROJ' => [
                'dashboard' => $isStaff ? 'proj.employee.dashboard' : 'proj.manager.dashboard',
            ],
            'IT' => [
                'dashboard' => 'it.dashboard',
                'tickets' => 'it.tickets',
                'assets' => 'it.assets',
                'monitoring' => 'it.monitoring',
                'knowledge' => 'it.knowledge',
                'changes' => 'it.changes',
                'access_control' => 'it.access-control',
                'access_logs' => 'it.access-logs',
            ],
        ];

        return $map[strtoupper($module)] ?? [];
    }

    /**
     * Whether plain MAN plant staff can actually open their role workspace:
     * a usable MAN production grant, or native auto-access (MAN staff with
     * zero explicit MAN rows). Mirrors CheckPagePermission semantics.
     */
    protected function manRoleWorkspaceReachable($user): bool
    {
        if (strtoupper($user->role ?? '') !== 'MAN') {
            return false;
        }

        $usableProduction = PagePermission::where('user_id', $user->id)
            ->whereIn('module', ['MAN', 'man'])
            ->whereRaw('LOWER(page) = ?', ['production'])
            ->where(function ($q) {
                $q->whereIn('permission_level', ['view', 'edit'])
                    ->orWhereNull('permission_level');
            })
            ->exists();

        if ($usableProduction) {
            return true;
        }

        $hasManExplicit = PagePermission::where('user_id', $user->id)
            ->whereIn('module', ['MAN', 'man'])
            ->exists();

        return ! $hasManExplicit && in_array(strtolower($user->position ?? ''), ['manager', 'staff'], true);
    }

    /**
     * Whether the account holds ANY usable grant.
     * Delegates to the shared gate (also used for friendly 403 handling).
     */
    protected function hasAnyAccess($user): bool
    {
        return AccessGate::hasAnyAccess($user);
    }

    /**
     * Whether the user holds any usable page grant (view/edit; legacy NULL
     * levels grandfathered as edit, mirroring CheckPagePermission and
     * CheckModuleAccess). Module matching is case-tolerant.
     */
    protected function hasUsablePageGrants($user, ?string $module = null): bool
    {
        return AccessGate::hasUsablePageGrants($user, $module);
    }
}
