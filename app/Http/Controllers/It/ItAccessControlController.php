<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\Ceo\PositionChangeRequest;
use App\Models\Core\PagePermission;
use App\Models\Core\User;
use App\Models\Core\UserModuleAccess;
use App\Models\It\ItAccessLog;
use App\Models\Man\ManufacturingSupervisorRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Organization-wide access administration for the IT department.
 * - Account state: direct IT control.
 * - Position: STRICT — requires a pending President/VP command
 *   (position_change_requests) passed as request_id.
 * - Modules: root module locked; staff sees root only; elevated roles
 *   (manager/supervisor/secretary/special_officer) may hold extra modules.
 * - Pages: explicit view/edit/disabled per page; new module grants seed
 *   every page as disabled so IT enables each page manually.
 */
class ItAccessControlController extends Controller
{
    protected $moduleNames = [
        'HRM' => 'Human Resources',
        'CRM' => 'Customer Relations',
        'MAN' => 'Manufacturing',
        'LOG' => 'Logistics',
        'ECO' => 'E-Commerce',
        'ORD' => 'Order Management',
        'SCM' => 'Supply Chain',
        'WAR' => 'Warehouse',
        'INV' => 'Inventory',
        'PRO' => 'Procurement',
        'FIN' => 'Finance',
        'PROJ' => 'Projects',
        'IT' => 'IT & Systems',
        'WRF' => 'Workforce',
    ];

    protected $assignableRoles = [
        'HRM', 'CRM', 'MAN', 'LOG', 'ECO', 'ORD', 'SCM',
        'WAR', 'INV', 'PRO', 'FIN', 'PROJ', 'IT',
    ];

    protected $assignablePositions = ['manager', 'staff', 'secretary', 'special_officer', 'vice_president', 'supervisor'];

    protected $supervisorDepartments = ['knitting', 'dyeing', 'finishing', 'maintenance', 'boiler'];

    protected $coreModules = ['HRM', 'CRM', 'MAN', 'LOG'];

    /**
     * Canonical per-module page map: config file first, WRF appended
     * (it has no entry in config/module_pages.php).
     */
    protected function modulePages(): array
    {
        $pages = config('module_pages', []);
        $pages['wrf'] = [
            'dashboard' => 'Dashboard',
            'scheduler' => 'Scheduler',
            'leave' => 'Leave Management',
            'absent' => 'Absences',
        ];

        $ordered = [];
        foreach (array_keys($this->moduleNames) as $key) {
            $lookup = strtolower($key);
            if (isset($pages[$lookup])) {
                $ordered[$key] = $pages[$lookup];
            }
        }

        return $ordered;
    }

    protected function rootModuleFor(User $user): ?string
    {
        if ($user->is_manufacturing_supervisor) {
            return 'MAN';
        }
        if (! $user->role) {
            return null;
        }
        $roleUpper = strtoupper($user->role);
        foreach ($this->coreModules as $core) {
            if (str_contains($roleUpper, $core)) {
                return $core;
            }
        }

        return str_contains($roleUpper, 'CEO') ? null : $roleUpper;
    }

    protected function isElevated(User $user): bool
    {
        return $user->is_manufacturing_supervisor
            || in_array($user->position, ['manager', 'secretary', 'special_officer', 'vice_president'], true);
    }

    /**
     * Root module whose pages can never be disabled for this account.
     *
     * Secretaries, special officers, managers and manufacturing supervisors
     * are higher-ups with automatic full access on their root module by
     * default — IT may only choose view vs edit there, never disabled.
     * Returns the UPPERCASE module key, or null when no lock applies
     * (staff, vice presidents, CEO-role accounts).
     */
    protected function lockedRootModuleFor(User $user): ?string
    {
        if ($user->is_manufacturing_supervisor) {
            return 'MAN';
        }
        if (! in_array($user->position, ['manager', 'secretary', 'special_officer'], true)) {
            return null;
        }
        $root = $this->rootModuleFor($user);
        return $root ? strtoupper($root) : null;
    }

    /**
     * Maps a manufacturing_role slug to its department (knitting, dyeing,
     * finishing, maintenance, boiler). Mirrors User::getSupervisedRolesAttribute
     * and CheckManufacturingRoleAccess::$departmentRoles.
     */
    protected function manufacturingDepartmentFor(?string $role): ?string
    {
        if (! $role) {
            return null;
        }

        $map = [
            'knitting_yarn' => 'knitting',
            'knitting_mechanic' => 'knitting',
            'dyeing_color' => 'dyeing',
            'dyeing_fabric_softener' => 'dyeing',
            'dyeing_squeezer' => 'dyeing',
            'dyeing_ironing' => 'dyeing',
            'dyeing_packaging' => 'dyeing',
            'dyeing_lab_chemist' => 'dyeing',
            'checker_quality' => 'finishing',
            'maintenance_checker' => 'maintenance',
            'pollution_control_operator' => 'maintenance',
            'safety_officer' => 'maintenance',
            'boiler_operator' => 'boiler',
        ];

        return $map[$role] ?? null;
    }

    public function index()
    {
        $users = User::with(['moduleAccess', 'pagePermissions'])
            ->whereNotIn('role', ['CEO', 'COO'])
            ->orderBy('role')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'employee_id' => $u->employee_id,
                'role' => $u->role,
                'position' => $u->position,
                // MAN supervisors are stored as position=staff + flag.
                // Expose a display position so the UI never shows them as Staff.
                'display_position' => $u->is_manufacturing_supervisor ? 'supervisor' : $u->position,
                'supervisor_department' => $u->supervisor_department,
                'manufacturing_role' => $u->manufacturing_role,
                'manufacturing_department' => $u->is_manufacturing_supervisor
                    ? $u->supervisor_department
                    : $this->manufacturingDepartmentFor($u->manufacturing_role),
                'is_active' => (bool) $u->is_active,
                'suspended_until' => $u->suspended_until,
                'is_manufacturing_supervisor' => (bool) $u->is_manufacturing_supervisor,
                'root_module' => $this->rootModuleFor($u),
                'is_elevated' => $this->isElevated($u),
            ]);

        $pagePerms = PagePermission::whereIn('user_id', $users->pluck('id'))->get();
        $userPagePerms = [];
        foreach ($pagePerms as $pp) {
            $userPagePerms[$pp->user_id][$pp->module][$pp->page] = strtolower($pp->permission_level ?? 'edit');
        }

        $userModules = UserModuleAccess::whereIn('user_id', $users->pluck('id'))
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('module')->values())
            ->toArray();

        $pendingRequests = PositionChangeRequest::with([
                'target:id,name,email,employee_id,role,position',
                'requester:id,name,position',
            ])
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'target_user_id' => $r->target_user_id,
                'target_name' => $r->target?->name,
                'target_email' => $r->target?->email,
                'target_employee_id' => $r->target?->employee_id,
                'target_role' => $r->target?->role,
                'current_position' => $r->current_position,
                'requested_position' => $r->requested_position,
                'current_role' => $r->current_role,
                'requested_role' => $r->requested_role,
                'supervisor_department' => $r->supervisor_department,
                'action' => $r->action,
                'reason' => $r->reason,
                'requested_by_name' => $r->requester?->name,
                'requested_by_position' => $r->requester?->position,
                'created_at' => $r->created_at,
            ]);

        return Inertia::render('Dashboard/IT/Manager/ItAccessControl', [
            'users' => $users,
            'modules' => collect($this->moduleNames)
                ->map(fn ($name, $key) => ['key' => $key, 'name' => $name])
                ->values(),
            'modulePages' => $this->modulePages(),
            'userPagePerms' => $userPagePerms,
            'userModules' => $userModules,
            'assignableRoles' => $this->assignableRoles,
            'assignablePositions' => $this->assignablePositions,
            'pendingRequests' => $pendingRequests,
        ]);
    }

    /**
     * Enable / disable / suspend / restore an account.
     */
    public function setStatus(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'action' => 'required|in:enable,disable,suspend,restore',
            'suspended_until' => 'nullable|date|after:now',
        ]);

        $user = User::findOrFail($data['user_id']);
        if (in_array($user->role, ['CEO', 'COO'], true)) {
            return back()->withErrors(['error' => 'Executive (CEO/COO) accounts cannot be modified here.']);
        }
        if ($user->id === auth()->id() && in_array($data['action'], ['disable', 'suspend'], true)) {
            return back()->withErrors(['error' => 'You cannot disable or suspend your own account.']);
        }
        if ($data['action'] === 'suspend' && empty($data['suspended_until'])) {
            return back()->withErrors(['suspended_until' => 'A suspension end date is required.']);
        }

        $before = ['is_active' => (bool) $user->is_active, 'suspended_until' => $user->suspended_until];

        match ($data['action']) {
            'enable' => (function () use ($user) {
                $user->is_active = true;
                $user->suspended_until = null;
                $user->save();
            })(),
            'disable' => (function () use ($user) {
                $user->is_active = false;
                $user->suspended_until = null;
                $user->save();
            })(),
            'suspend' => (function () use ($user, $data) {
                $user->is_active = false;
                $user->suspended_until = $data['suspended_until'];
                $user->save();
            })(),
            'restore' => (function () use ($user) {
                $user->is_active = true;
                $user->suspended_until = null;
                $user->save();
            })(),
        };

        $labels = [
            'enable' => 'account.enabled',
            'disable' => 'account.disabled',
            'suspend' => 'account.suspended',
            'restore' => 'account.restored',
        ];

        $this->logAction($user->id, $labels[$data['action']], match ($data['action']) {
            'enable' => "Account enabled for {$user->name}.",
            'disable' => "Account disabled for {$user->name} (blocked from all logins).",
            'suspend' => "Account suspended for {$user->name} until {$user->suspended_until}.",
            'restore' => "Account restored for {$user->name}.",
        }, ['before' => $before, 'after' => ['is_active' => (bool) $user->is_active, 'suspended_until' => $user->suspended_until]]);

        return back()->with('success', 'Account status updated.');
    }

    /**
     * Promote / demote position — STRICT: requires a pending
     * President/VP command (position_change_requests.request_id).
     */
    public function updatePosition(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'position' => 'required|in:' . implode(',', $this->assignablePositions),
            'role' => 'nullable|in:' . implode(',', $this->assignableRoles),
            'supervisor_department' => 'nullable|in:' . implode(',', $this->supervisorDepartments),
            'request_id' => 'required|exists:position_change_requests,id',
        ]);

        $user = User::findOrFail($data['user_id']);
        if ($user->role === 'CEO' && $user->position !== 'vice_president') {
            return back()->withErrors(['error' => 'CEO accounts cannot be modified here.']);
        }

        $cmd = PositionChangeRequest::findOrFail($data['request_id']);
        if ($cmd->status !== 'pending') {
            return back()->withErrors(['error' => 'This executive command is no longer pending.']);
        }
        if ((int) $cmd->target_user_id !== (int) $user->id) {
            return back()->withErrors(['error' => 'This command is for a different employee.']);
        }
        if ($cmd->requested_position !== $data['position']) {
            return back()->withErrors(['error' => "Position must match the executive command ({$cmd->requested_position})."]);
        }
        if ($cmd->requested_role && ! empty($data['role']) && $cmd->requested_role !== $data['role']) {
            return back()->withErrors(['error' => "Home module must match the executive command ({$cmd->requested_role})."]);
        }

        // ── Manufacturing-supervisor track (replaces the removed MAN page) ──
        if ($data['position'] === 'supervisor' || $cmd->action === 'remove_supervisor') {
            return $this->fulfilSupervisorCommand($user, $cmd, $data);
        }
        // A current supervisor can only move through supervisor commands —
        // never a plain position change (which would orphan the seat/flag).
        if ($user->is_manufacturing_supervisor) {
            return back()->withErrors(['error' => 'This employee holds a supervisor seat. Fulfil a supervisor assign/remove command instead.']);
        }

        if ($data['position'] === 'secretary') {
            $exists = User::where('position', 'secretary')
                ->where('is_manufacturing_supervisor', 0)
                ->where('id', '!=', $user->id)
                ->first();
            if ($exists) {
                return back()->withErrors(['error' => "A secretary already exists ({$exists->name}). Only one is allowed."]);
            }
        }

        if ($data['position'] === 'manager') {
            $roleForCheck = $data['role'] ?? $cmd->requested_role ?? $user->role;
            $exists = User::where('position', 'manager')
                ->where('role', $roleForCheck)
                ->where('id', '!=', $user->id)
                ->first();
            if ($exists) {
                return back()->withErrors(['error' => "The {$roleForCheck} module already has a manager ({$exists->name})."]);
            }
        }

        if ($data['position'] === 'vice_president') {
            $exists = User::where('position', 'vice_president')
                ->where('id', '!=', $user->id)
                ->first();
            if ($exists) {
                return back()->withErrors(['error' => "A vice president already exists ({$exists->name}). Demote them first."]);
            }
        }

        $before = ['position' => $user->position, 'role' => $user->role];

        DB::transaction(function () use ($user, $data, $cmd) {
            $newRole = $data['role'] ?? $cmd->requested_role ?? $user->role;
            if ($data['position'] === 'vice_president') {
                $user->role = 'COO';
            } elseif (! empty($newRole)) {
                $user->role = $newRole;
            }
            // Demoting a VP back out of the COO role restores the home module.
            if ($user->position === 'vice_president' && $data['position'] === 'staff' && ! empty($newRole)) {
                $user->role = $newRole;
            }
            $user->position = $data['position'];
            $user->save();

            if ($data['position'] === 'staff') {
                $user->moduleAccess()->delete();
                $user->pagePermissions()->delete();
            } elseif ($data['position'] === 'manager') {
                $user->pagePermissions()->delete();
            }

            $cmd->status = 'fulfilled';
            $cmd->reviewed_by = auth()->id();
            $cmd->reviewed_at = now();
            $cmd->save();
        });

        $this->logAction($user->id, 'position.updated',
            "Position updated for {$user->name}: {$before['position']} → {$user->position}" .
            ($before['role'] !== $user->role ? " ({$before['role']} → {$user->role})" : '') .
            " via executive command #{$cmd->id}.",
            ['before' => $before, 'after' => ['position' => $user->position, 'role' => $user->role], 'request_id' => $cmd->id]
        );

        return back()->with('success', 'Position updated per executive command.');
    }

    /**
     * Fulfil a supervisor assign/remove executive command.
     * Supervisors stay position=staff + flag (users.position has no
     * 'supervisor' value); only the flag, department and scoped
     * ManufacturingSupervisorRole rows change. Grants are untouched.
     */
    protected function fulfilSupervisorCommand(User $user, PositionChangeRequest $cmd, array $data)
    {
        if ($user->role !== 'MAN') {
            return back()->withErrors(['error' => 'Supervisor seats are MAN-only.']);
        }

        // ── Removal ──
        if ($cmd->action === 'remove_supervisor') {
            if (! $user->is_manufacturing_supervisor) {
                return back()->withErrors(['error' => "{$user->name} no longer holds a supervisor seat."]);
            }
            $dept = $user->supervisor_department;

            DB::transaction(function () use ($user, $cmd) {
                $user->is_manufacturing_supervisor = false;
                $user->supervisor_department = null;
                $user->save();
                ManufacturingSupervisorRole::where('user_id', $user->id)->delete();

                $cmd->status = 'fulfilled';
                $cmd->reviewed_by = auth()->id();
                $cmd->reviewed_at = now();
                $cmd->save();
            });

            $this->logAction($user->id, 'position.updated',
                "Supervisor seat removed for {$user->name} ({$dept}) via executive command #{$cmd->id}.",
                ['before' => ['supervisor' => $dept], 'after' => ['supervisor' => null], 'request_id' => $cmd->id]
            );

            return back()->with('success', "Supervisor seat removed for {$user->name}.");
        }

        // ── Assignment (action assign_supervisor, position supervisor) ──
        $dept = $data['supervisor_department'] ?? $cmd->supervisor_department;
        if ($cmd->supervisor_department && ! empty($data['supervisor_department'])
            && $data['supervisor_department'] !== $cmd->supervisor_department) {
            return back()->withErrors(['error' => "Department must match the executive command ({$cmd->supervisor_department})."]);
        }
        if (empty($dept)) {
            return back()->withErrors(['error' => 'Choose a supervisor department (knitting, dyeing, finishing, maintenance, boiler).']);
        }

        // One supervisor per department.
        $occupant = User::where('is_manufacturing_supervisor', true)
            ->where('supervisor_department', $dept)
            ->where('id', '!=', $user->id)
            ->first(['id', 'name']);
        if ($occupant) {
            return back()->withErrors(['error' => "The {$dept} seat is held by {$occupant->name}. Remove them first."]);
        }

        $rolesToAssign = match ($dept) {
            'knitting' => ['knitting_yarn', 'knitting_mechanic'],
            'dyeing' => [
                'dyeing_color',
                'dyeing_fabric_softener',
                'dyeing_squeezer',
                'dyeing_ironing',
                'dyeing_packaging',
                'dyeing_lab_chemist',
            ],
            'finishing' => ['checker_quality'],
            'maintenance' => ['maintenance_checker', 'pollution_control_operator', 'safety_officer'],
            'boiler' => ['boiler_operator'],
            default => [],
        };

        $before = ['supervisor' => $user->is_manufacturing_supervisor ? $user->supervisor_department : null];

        DB::transaction(function () use ($user, $cmd, $dept, $rolesToAssign) {
            $user->is_manufacturing_supervisor = true;
            $user->supervisor_department = $dept;
            if ($user->position !== 'staff') {
                $user->position = 'staff';
            }
            $user->save();

            ManufacturingSupervisorRole::where('user_id', $user->id)->delete();
            foreach ($rolesToAssign as $role) {
                ManufacturingSupervisorRole::create([
                    'user_id' => $user->id,
                    'manufacturing_role' => $role,
                ]);
            }

            $cmd->status = 'fulfilled';
            $cmd->reviewed_by = auth()->id();
            $cmd->reviewed_at = now();
            $cmd->save();
        });

        $this->logAction($user->id, 'position.updated',
            "Assigned {$user->name} as {$dept} supervisor via executive command #{$cmd->id}.",
            ['before' => $before, 'after' => ['supervisor' => $dept], 'request_id' => $cmd->id]
        );

        return back()->with('success', "{$user->name} promoted to {$dept} supervisor.");
    }

    /**
     * Reject an executive command without applying it.
     */
    public function rejectRequest(Request $request, int $id)
    {
        $cmd = PositionChangeRequest::findOrFail($id);
        if ($cmd->status !== 'pending') {
            return back()->withErrors(['error' => 'This command is no longer pending.']);
        }
        $cmd->status = 'rejected';
        $cmd->reviewed_by = auth()->id();
        $cmd->reviewed_at = now();
        $cmd->save();

        $this->logAction($cmd->target_user_id, 'position.request_rejected',
            "Executive command #{$cmd->id} for {$cmd->target?->name} rejected by IT.",
            ['request_id' => $cmd->id]
        );

        return back()->with('success', 'Executive command rejected.');
    }

    /**
     * Sync module grants (user_module_access).
     * Rules: root module always kept + locked; staff may hold ONLY root.
     */
    public function updateModules(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'modules' => 'array',
            'modules.*' => 'in:' . implode(',', array_keys($this->moduleNames)),
        ]);

        $user = User::findOrFail($data['user_id']);
        if (in_array($user->role, ['CEO', 'COO'], true)) {
            return back()->withErrors(['error' => 'Executive (CEO/COO) accounts cannot be modified here.']);
        }

        $root = $this->rootModuleFor($user);
        $elevated = $this->isElevated($user);

        $before = $user->moduleAccess()->pluck('module')->toArray();
        $modules = array_values(array_unique($data['modules'] ?? []));

        // Root is always granted (locked in UI, enforced here too).
        if ($root && ! in_array($root, $modules, true)) {
            $modules[] = $root;
        }

        // Staff hold ONLY their root module — extra grants are stripped.
        if (! $elevated) {
            $modules = $root ? [$root] : [];
        }

        // Manufacturing supervisors keep MAN root.
        if ($user->is_manufacturing_supervisor && ! in_array('MAN', $modules, true)) {
            $modules[] = 'MAN';
        }

        $modules = array_values(array_unique($modules));

        $prunedPages = 0;
        DB::transaction(function () use ($user, $modules, &$prunedPages) {
            $user->moduleAccess()->delete();
            foreach ($modules as $module) {
                UserModuleAccess::create([
                    'user_id' => $user->id,
                    'module' => $module,
                    'permission_level' => 'edit',
                    'granted_by' => auth()->id(),
                ]);
            }
            // Revoking a module revokes its pages too: drop every page row
            // whose module is no longer granted (case-insensitive), so stale
            // view/edit rows can't linger, inflate counts, re-open access,
            // or re-provision the module shell on the next Pages save.
            $kept = array_map('strtoupper', $modules);
            $staleIds = PagePermission::where('user_id', $user->id)
                ->get(['id', 'module'])
                ->reject(fn ($pp) => in_array(strtoupper((string) $pp->module), $kept, true))
                ->pluck('id');
            if ($staleIds->isNotEmpty()) {
                $prunedPages = $staleIds->count();
                PagePermission::whereIn('id', $staleIds)->delete();
            }
            // Seed every page of every granted module as disabled so IT
            // must explicitly enable each page (view/edit) afterwards —
            // except a higher-up's locked root, which seeds as edit
            // (their default; it can never be disabled).
            $lockedRoot = $this->lockedRootModuleFor($user);
            $this->seedDisabledPages($user, $modules, $lockedRoot);
        });

        $this->logAction($user->id, 'modules.updated',
            'Module grants updated for ' . $user->name . ': [' . implode(', ', $modules) . '].'
            . ($prunedPages ? " {$prunedPages} page grant(s) of revoked module(s) removed." : ''),
            ['before' => $before, 'after' => $modules, 'pruned_pages' => $prunedPages]
        );

        return back()->with('success', 'Module grants updated.'
            . ($prunedPages ? " {$prunedPages} page grant(s) of the removed module(s) were revoked too." : ''));
    }

    /**
     * Sync per-page view/edit/disabled grants.
     * Only the root module + granted extra modules may receive grants.
     */
    public function updatePages(Request $request)
    {
        $map = $this->modulePages();
        $validPairs = [];
        foreach ($map as $module => $pages) {
            foreach (array_keys($pages) as $page) {
                $validPairs[] = $module . '.' . strtolower($page);
            }
        }

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'grants' => 'array',
            'grants.*.module' => 'required|string',
            'grants.*.page' => 'required|string',
            'grants.*.level' => 'required|in:view,edit,disabled',
        ]);

        $user = User::findOrFail($data['user_id']);
        if (in_array($user->role, ['CEO', 'COO'], true)) {
            return back()->withErrors(['error' => 'Executive (CEO/COO) accounts cannot be modified here.']);
        }

        $root = $this->rootModuleFor($user);
        $grantedModules = $user->moduleAccess()->pluck('module')->map(fn ($m) => strtoupper($m))->toArray();
        $allowedModules = array_unique(array_filter(array_map('strtoupper', array_merge($grantedModules, $root ? [$root, $user->role] : [$user->role]))));

        // Staff may only receive grants for their home module.
        if (! $this->isElevated($user)) {
            $allowedModules = [strtoupper($user->role)];
        } else {
            // Elevated accounts (manager/secretary/special_officer/VP/
            // supervisor) may hold extra modules. The Pages tab lets IT set
            // a page before the Modules tab was ever saved, and the old code
            // silently dropped those rows here — IT believed access was
            // granted while the sidebar kept hiding the page. Treat a
            // submitted usable (view/edit) grant as an implicit module grant
            // instead, and provision the module row below.
            foreach ($data['grants'] ?? [] as $grant) {
                $module = strtoupper($grant['module'] ?? '');
                $page = strtolower($grant['page'] ?? '');
                $level = strtolower($grant['level'] ?? '');
                if ($module === '' || ! in_array($level, ['view', 'edit'], true)) {
                    continue;
                }
                if (! in_array($module . '.' . $page, $validPairs, true)) {
                    continue;
                }
                if (! in_array($module, $allowedModules, true)) {
                    $allowedModules[] = $module;
                }
            }
        }

        $before = PagePermission::where('user_id', $user->id)
            ->get()
            ->map(fn ($pp) => $pp->module . '.' . $pp->page . ':' . strtolower($pp->permission_level ?? 'edit'))
            ->toArray();

        // Higher-up root pages can never be disabled (default full access) —
        // a submitted 'disabled' there is coerced to the default 'edit',
        // guarding direct POSTs as well as the UI (which hides DISABLE).
        $lockedRoot = $this->lockedRootModuleFor($user);

        $summary = [];
        $coerced = 0;
        $provisionedModules = [];
        DB::transaction(function () use ($user, $data, $validPairs, $allowedModules, $lockedRoot, &$summary, &$coerced, &$provisionedModules) {
            $user->pagePermissions()->delete();
            $usableModules = [];
            foreach ($data['grants'] ?? [] as $grant) {
                $module = strtoupper($grant['module']);
                $page = strtolower($grant['page']);
                if (! in_array($module . '.' . $page, $validPairs, true)) {
                    continue;
                }
                if (! in_array($module, $allowedModules, true)) {
                    continue;
                }
                $level = strtolower($grant['level']);
                if ($lockedRoot && $module === $lockedRoot && $level === 'disabled') {
                    $level = 'edit';
                    $coerced++;
                }
                PagePermission::create([
                    'user_id' => $user->id,
                    'module' => $module,
                    'page' => $page,
                    'permission_level' => $level,
                ]);
                $summary[] = "{$module}.{$page}:{$level}";
                if (in_array($level, ['view', 'edit'], true)) {
                    $usableModules[$module] = true;
                }
            }
            // Provision the module shell for every usable grant so the module
            // gate (CheckModuleAccess) and the sidebar module condition stay
            // in sync with the pages IT just enabled — no second Modules save
            // required.
            foreach (array_keys($usableModules) as $module) {
                $exists = $user->moduleAccess()->whereIn('module', [$module, strtoupper($module), strtolower($module)])->exists();
                if (! $exists) {
                    UserModuleAccess::create([
                        'user_id' => $user->id,
                        'module' => $module,
                        'permission_level' => 'edit',
                        'granted_by' => auth()->id(),
                    ]);
                    $provisionedModules[] = $module;
                }
            }
        });

        $this->logAction($user->id, 'pages.updated',
            'Page permissions updated for ' . $user->name . ' (' . count($summary) . ' grants'
            . ($coerced ? ", {$coerced} locked-root DISABLED coerced to edit" : '')
            . ($provisionedModules ? ', module shell granted for [' . implode(', ', $provisionedModules) . ']' : '') . ').',
            ['before' => $before, 'after' => $summary, 'provisioned_modules' => $provisionedModules]
        );

        $notice = 'Page permissions updated.'
            . ($coerced ? " ({$coerced} root page(s) kept enabled — higher-up default.)" : '')
            . ($provisionedModules ? ' Module access granted for [' . implode(', ', $provisionedModules) . '].' : '');

        return back()->with('success', $notice);
    }

    /**
     * Ensure every page of each granted module has a row — default disabled.
     * Existing view/edit choices are preserved.
     *
     * A higher-up's locked root ($lockedRoot) instead seeds missing pages as
     * 'edit' — their root access is default-on and can never be disabled.
     */
    protected function seedDisabledPages(User $user, array $modules, ?string $lockedRoot = null): void
    {
        $map = $this->modulePages();
        $existing = PagePermission::where('user_id', $user->id)
            ->get()
            ->mapWithKeys(fn ($pp) => [strtoupper($pp->module) . '.' . strtolower($pp->page) => strtolower($pp->permission_level ?? 'edit')]);

        foreach ($modules as $module) {
            $upper = strtoupper($module);
            $default = ($lockedRoot && $upper === strtoupper($lockedRoot)) ? 'edit' : 'disabled';
            foreach (array_keys($map[$upper] ?? []) as $page) {
                $key = $upper . '.' . strtolower($page);
                if (! $existing->has($key)) {
                    PagePermission::create([
                        'user_id' => $user->id,
                        'module' => $upper,
                        'page' => strtolower($page),
                        'permission_level' => $default,
                    ]);
                }
            }
        }
    }

    /**
     * Audit trail of every change made in IT Access Control.
     */
    public function logs(Request $request)
    {
        $query = ItAccessLog::with(['actor:id,name', 'target:id,name'])
            ->latest();

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('details', 'like', "%{$search}%")
                    ->orWhereHas('target', fn ($t) => $t->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('actor', fn ($a) => $a->where('name', 'like', "%{$search}%"));
            });
        }

        return Inertia::render('Dashboard/IT/Manager/AccessLogs', [
            'logs' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['action', 'search']),
            'actions' => array_merge(ItAccessLog::ACTIONS, ['position.request_rejected']),
        ]);
    }

    protected function logAction(int $targetUserId, string $action, string $details, array $meta = []): void
    {
        ItAccessLog::create([
            'actor_id' => auth()->id(),
            'target_user_id' => $targetUserId,
            'action' => $action,
            'details' => $details,
            'meta' => $meta,
            'ip_address' => request()->ip(),
        ]);
    }
}
