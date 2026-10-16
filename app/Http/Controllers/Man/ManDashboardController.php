<?php

namespace App\Http\Controllers\Man;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class ManDashboardController extends Controller
{
    public function staffDashboard()
    {
        $user = auth()->user();

        // Manufacturing supervisor → department overview dashboard
        // (no manufacturing manager; each supervisor sees their own department)
        if ($user->is_manufacturing_supervisor) {
            // The overview is dashboard-gated: never bounce a supervisor
            // whose dashboard grant was revoked into a raw 403.
            if (! $this->hasUsableGrant($user, 'MAN', 'dashboard') && ! $this->hasNativeManAccess($user)) {
                return $this->limitedAccess(
                    'Manufacturing overview unavailable.',
                    'Your overview dashboard grant was revoked. Ask IT Access Control to re-enable the MAN Dashboard page.'
                );
            }

            return Redirect::route('man.manager.dashboard');
        }

        // Normal staff (single role)
        $role = $user->manufacturing_role;

        $routes = [
            'knitting_yarn' => 'man.staff.knitting-yarn.dashboard',
            'knitting_mechanic' => 'man.staff.knitting-mechanic.dashboard',
            'dyeing_color' => 'man.staff.dyeing-color.dashboard',
            'dyeing_fabric_softener' => 'man.staff.dyeing-fabric-softener.dashboard',
            'dyeing_squeezer' => 'man.staff.dyeing-squeezer.dashboard',
            'dyeing_ironing' => 'man.staff.dyeing-ironing.dashboard',
            'dyeing_packaging' => 'man.staff.dyeing-packaging.dashboard',
            'dyeing_lab_chemist' => 'man.staff.dyeing-lab-chemist.dashboard',
            'maintenance_checker' => 'man.staff.maintenance-checker.dashboard',
            'boiler_operator' => 'man.staff.boiler-operator.dashboard',
            'pollution_control_operator' => 'man.staff.pollution-control.dashboard',
            'safety_officer' => 'man.staff.safety-officer.dashboard',
            'checker_quality' => 'man.staff.checker-quality.dashboard',
        ];

        if (isset($routes[$role])) {
            // Every role workspace (including its Dashboard tab) is gated by
            // page.permission:production,view — a Dashboard-only grant can
            // never open it. Stop here with a clear notice instead of
            // redirecting into a "production disabled by IT" 403.
            if (! $this->hasUsableGrant($user, 'MAN', 'production') && ! $this->hasNativeManAccess($user)) {
                return $this->limitedAccess(
                    'Plant workspace unavailable.',
                    'Your account holds the MAN Dashboard page but not Production, and every plant role workspace (including its Dashboard tab) requires the Production grant.',
                    'Ask IT Access Control to enable MAN → Production (view) for your account, then press Check again.'
                );
            }

            return Redirect::route($routes[$role]);
        }

        return Inertia::render('Dashboard/MAN/Employee/Index');
    }

    /**
     * Usable (view/edit, legacy NULL grandfathered as edit) explicit grant
     * for one MAN page — mirrors CheckPagePermission semantics.
     */
    protected function hasUsableGrant($user, string $module, string $page): bool
    {
        if (! method_exists($user, 'pagePermissions')) {
            return false;
        }

        return $user->pagePermissions()
            ->whereIn('module', [$module, strtoupper($module), strtolower($module)])
            ->whereRaw('LOWER(page) = ?', [strtolower($page)])
            ->where(function ($q) {
                $q->whereIn('permission_level', ['view', 'edit'])
                    ->orWhereNull('permission_level');
            })
            ->exists();
    }

    /**
     * Native MAN manager/staff auto-access: applies only when the module has
     * NO explicit rows at all (mirrors CheckPagePermission + AccessGate).
     * The moment IT seeds rows, those rows are the exact access set.
     */
    protected function hasNativeManAccess($user): bool
    {
        if (strtoupper($user->role ?? '') !== 'MAN') {
            return false;
        }
        if (! in_array($user->position, ['manager', 'staff'], true)) {
            return false;
        }
        if (! method_exists($user, 'pagePermissions')) {
            return false;
        }

        return ! $user->pagePermissions()
            ->whereIn('module', ['MAN', 'man'])
            ->exists();
    }

    protected function limitedAccess(string $title, string $message, string $hint = '')
    {
        return Inertia::render('Dashboard/AwaitingAccess', [
            'user' => auth()->user(),
            'title' => $title,
            'message' => $message,
            'hint' => $hint,
        ]);
    }
}
