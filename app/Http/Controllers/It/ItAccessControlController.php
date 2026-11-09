<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\Ceo\PositionChangeRequest;
use App\Models\Core\PagePermission;
use App\Models\Core\User;
use App\Models\Core\UserModuleAccess;
use App\Models\It\ItAccessLog;
use App\Models\Man\ManufacturingSupervisorRole;
use App\Support\ManufacturingPages;
use App\Support\ManufacturingRoles;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Organization-wide access administration for the IT department.
 * - Account state: direct IT control.
 * - Position: IT can directly promote/demote non-executive accounts.
 * - Modules: employees retain their home module; managers, supervisors,
 *   special officers, secretaries, the President and Vice President may
 *   hold additional modules.
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

    protected $assignablePositions = ['manager', 'staff', 'secretary', 'special_officer', 'supervisor'];

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

        // Keep the ECO RMA grant available even when a deployment is still
        // using a cached module_pages config from before Returns was added.
        $ordered['ECO']['returns'] ??= 'Returns & RMA Management';

        return $ordered;
    }

    protected function rootModuleFor(User $user): ?string
    {
        if (strtoupper((string) $user->role) === 'CEO') {
            return 'CEO';
        }
        if (strtoupper((string) $user->role) === 'COO' || $user->position === 'vice_president') {
            return 'VP';
        }
        if ($user->position === 'secretary') {
            return 'SEC';
        }
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
            || in_array($user->position, ['manager', 'secretary', 'special_officer', 'vice_president', 'president'], true)
            || in_array(strtoupper((string) $user->role), ['CEO', 'COO'], true);
    }

    /** Root pages that should be enabled by default when first provisioned. */
    protected function lockedRootModuleFor(User $user): ?string
    {
        if ($user->is_manufacturing_supervisor || in_array($user->position, ['manager', 'special_officer'], true)) {
            $root = $this->rootModuleFor($user);
            return $root ? strtoupper($root) : null;
        }

        // President/VP/Secretary workspaces are fixed and are not represented
        // by the configurable module page catalog.
        return null;
    }

    /**
     * Maps a manufacturing_role slug to its department (knitting, dyeing,
     * finishing, maintenance, boiler). Mirrors User::getSupervisedRolesAttribute
     * and CheckManufacturingRoleAccess::$departmentRoles.
     */
    protected function manufacturingDepartmentFor(?string $role): ?string
    {
        return ManufacturingRoles::departmentForRole($role);
    }

    /**
     * Resolve a LOG staff member's plant role (driver vs conductor).
     * Prefers the users.log_role column; falls back to the driver/conductor
     * records (loaded via withExists) for accounts provisioned before the
     * column was populated. Non-LOG accounts always return null.
     */
    protected function logRoleFor(User $user): ?string
    {
        if (strtoupper((string) $user->role) !== 'LOG') {
            return null;
        }
        if (in_array($user->log_role, ['driver', 'conductor'], true)) {
            return $user->log_role;
        }
        if (! empty($user->driver_exists)) {
            return 'driver';
        }
        if (! empty($user->conductor_exists)) {
            return 'conductor';
        }

        return null;
    }

    public function index()
    {
        $users = User::with(['moduleAccess', 'pagePermissions'])
            ->withExists(['driver', 'conductor'])
            ->where(function ($query) {
                $query->whereIn('role', array_keys($this->moduleNames))
                    ->orWhereIn('role', ['CEO', 'COO']);
            })
            ->orderBy('role')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'photo_url' => $u->profile_photo_path
                    ? (str_starts_with($u->profile_photo_path, 'http://') || str_starts_with($u->profile_photo_path, 'https://')
                        ? $u->profile_photo_path
                        : url('/storage/' . ltrim(str_replace('/storage/', '', $u->profile_photo_path), '/')))
                    : null,
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
                // Logistics plant-staff track: driver vs conductor label.
                'log_role' => $this->logRoleFor($u),
                'is_active' => (bool) $u->is_active,
                'suspended_until' => $u->suspended_until,
                'is_manufacturing_supervisor' => (bool) $u->is_manufacturing_supervisor,
                'root_module' => $this->rootModuleFor($u),
                'is_elevated' => $this->isElevated($u),
                'is_executive' => in_array(strtoupper((string) $u->role), ['CEO', 'COO'], true)
                    || in_array($u->position, ['president', 'vice_president'], true),
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

        $supervisorStaffAccess = User::where('is_manufacturing_supervisor', true)
            ->get(['id', 'supervisor_department'])
            ->mapWithKeys(fn (User $supervisor) => [
                $supervisor->id => $this->supervisorStaffAccessFor($supervisor),
            ])
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
            // Role-aware MAN page catalog so IT sees every page a plant
            // employee handles (e.g. Dyeing Lab Chemist -> Shades, Tests &
            // CoA, Lab Inventory, Bulk Transfer, History, Recipes).
            'manRolePages' => ManufacturingPages::ROLE_PAGES,
            'manOverviewPages' => ManufacturingPages::OVERVIEW,
            'manSupervisorAccountPages' => collect(ManufacturingPages::SUPERVISOR_ACCOUNT_PAGES)
                ->mapWithKeys(fn ($roles, $department) => [
                    $department => ManufacturingPages::forSupervisorAccountDepartment($department),
                ]),
            'manSupervisorStaffAccess' => $supervisorStaffAccess,
        ]);
    }

    /** Build the role-by-role staff page matrix for one supervisor. */
    protected function supervisorStaffAccessFor(User $supervisor): array
    {
        $roles = ManufacturingPages::rolesForDepartment($supervisor->supervisor_department);
        $configured = DB::table('man_supervisor_staff_page_permissions')
            ->where('supervisor_user_id', $supervisor->id)
            ->get(['manufacturing_role', 'page', 'permission_level'])
            ->groupBy('manufacturing_role')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($row) => [
                strtolower($row->page) => strtolower($row->permission_level),
            ]));

        $legacy = PagePermission::where('user_id', $supervisor->id)
            ->where('module', 'MAN')
            ->get(['page', 'permission_level'])
            ->mapWithKeys(fn ($row) => [strtolower($row->page) => strtolower($row->permission_level ?? 'edit')]);
        $legacyFallback = $legacy->get('production', 'disabled');

        return [
            'department' => $supervisor->supervisor_department,
            'department_label' => ManufacturingRoles::departmentLabel($supervisor->supervisor_department),
            'roles' => collect($roles)->map(function ($role) use ($configured, $legacyFallback) {
                $pages = ManufacturingPages::ROLE_PAGES[$role] ?? [];
                $permissions = [];
                foreach ($pages as $page => $_label) {
                    $permissions[$page] = $configured->get($role)?->get($page) ?? $legacyFallback;
                }

                return [
                    'key' => $role,
                    'label' => ManufacturingRoles::LABELS[$role] ?? ucwords(str_replace('_', ' ', $role)),
                    'pages' => $pages,
                    'permissions' => $permissions,
                ];
            })->values(),
        ];
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

    /** Promote or demote a non-executive employee directly from IT Access Control. */
    public function updatePosition(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'position' => 'required|in:' . implode(',', $this->assignablePositions),
            'role' => 'nullable|in:' . implode(',', $this->assignableRoles),
            'supervisor_department' => 'nullable|in:' . implode(',', $this->supervisorDepartments),
        ]);

        $user = User::findOrFail($data['user_id']);
        if (in_array(strtoupper((string) $user->role), ['CEO', 'COO'], true)
            || in_array($user->position, ['president', 'vice_president'], true)) {
            return back()->withErrors(['error' => 'CEO and COO accounts cannot be promoted or demoted by IT.']);
        }

        $newRole = strtoupper((string) ($data['role'] ?? $user->role));
        if (in_array($newRole, ['CEO', 'COO'], true)) {
            return back()->withErrors(['error' => 'IT cannot assign the CEO or COO role.']);
        }

        if ($data['position'] === 'supervisor') {
            if ($newRole !== 'MAN') {
                return back()->withErrors(['error' => 'Manufacturing supervisor seats are MAN-only.']);
            }
            $department = $data['supervisor_department'] ?? null;
            if (! $department) {
                return back()->withErrors(['supervisor_department' => 'Choose a supervisor department.']);
            }
            $occupant = User::where('is_manufacturing_supervisor', true)
                ->where('supervisor_department', $department)
                ->where('id', '!=', $user->id)
                ->first(['id', 'name']);
            if ($occupant) {
                return back()->withErrors(['supervisor_department' => "The {$department} supervisor seat is held by {$occupant->name}."]);
            }
        } elseif ($user->is_manufacturing_supervisor) {
            // A direct demotion from a supervisor seat must clear the special
            // role flag and its scoped page grants as well as the position.
            $department = $user->supervisor_department;
        } else {
            $department = null;
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
            $roleForCheck = $newRole;
            $exists = User::where('position', 'manager')
                ->where('role', $roleForCheck)
                ->where('id', '!=', $user->id)
                ->first();
            if ($exists) {
                return back()->withErrors(['error' => "The {$roleForCheck} module already has a manager ({$exists->name})."]);
            }
        }

        $before = [
            'position' => $user->is_manufacturing_supervisor ? 'supervisor' : $user->position,
            'role' => $user->role,
            'supervisor_department' => $user->supervisor_department,
        ];

        DB::transaction(function () use ($user, $data, $newRole, $department) {
            $user->role = $newRole;
            if ($data['position'] === 'supervisor') {
                $user->position = 'staff';
                $user->is_manufacturing_supervisor = true;
                $user->supervisor_department = $department;
            } else {
                $user->position = $data['position'];
                if ($user->is_manufacturing_supervisor) {
                    $user->is_manufacturing_supervisor = false;
                    $user->supervisor_department = null;
                    ManufacturingSupervisorRole::where('user_id', $user->id)->delete();
                    DB::table('man_supervisor_staff_page_permissions')
                        ->where('supervisor_user_id', $user->id)
                        ->delete();
                }
            }
            $user->save();

            if ($data['position'] === 'supervisor') {
                ManufacturingSupervisorRole::where('user_id', $user->id)->delete();
                foreach (ManufacturingRoles::rolesForDepartment($department) as $role) {
                    ManufacturingSupervisorRole::create([
                        'user_id' => $user->id,
                        'manufacturing_role' => $role,
                    ]);
                }
            }

            if ($data['position'] === 'staff') {
                $user->moduleAccess()->delete();
                $user->pagePermissions()->delete();
            } elseif ($data['position'] === 'manager') {
                $user->pagePermissions()->delete();
            }
        });

        $this->logAction($user->id, 'position.updated',
            "Position updated for {$user->name}: {$before['position']} → " . ($user->is_manufacturing_supervisor ? 'supervisor' : $user->position) .
            ($before['role'] !== $user->role ? " ({$before['role']} → {$user->role})" : ''),
            ['before' => $before, 'after' => [
                'position' => $user->is_manufacturing_supervisor ? 'supervisor' : $user->position,
                'role' => $user->role,
                'supervisor_department' => $user->supervisor_department,
            ]]
        );

        return back()->with('success', 'Employee position updated.');
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

        $rolesToAssign = ManufacturingRoles::rolesForDepartment($dept);

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
     * Home workspaces are fixed; approved elevated roles may hold add-on modules.
     */
    public function updateModules(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'modules' => 'array',
            'modules.*' => 'string',
        ]);

        $user = User::findOrFail($data['user_id']);
        $root = $this->rootModuleFor($user);
        $elevated = $this->isElevated($user);

        $before = $user->moduleAccess()->pluck('module')->toArray();

        // Only configurable module codes are stored here. Virtual roots such
        // as SEC/VP/CEO are re-added below; sending them back from the UI used
        // to fail validation and silently block the whole save.
        $knownModules = array_map('strtoupper', array_keys($this->moduleNames));
        $modules = collect($data['modules'] ?? [])
            ->map(fn ($module) => strtoupper(trim((string) $module)))
            ->filter(fn ($module) => in_array($module, $knownModules, true))
            ->unique()
            ->values()
            ->all();

        // Preserve the account's home module while allowing add-ons.
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
        DB::transaction(function () use ($user, $request, $modules, &$prunedPages) {
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
            // Seed add-on pages disabled. Home module pages for managers and
            // supervisors start enabled, but IT can change them like any page.
            // A combined save skips seeding entirely: the submitted page
            // levels are authoritative, so saving one page can never switch on
            // (or create) every page of a home module.
            if (! $request->boolean('skip_seed')) {
                $lockedRoot = $this->lockedRootModuleFor($user);
                $this->seedDisabledPages($user, $modules, $lockedRoot);
            }
        });

        $this->logAction($user->id, 'modules.updated',
            'Module grants updated for ' . $user->name . ': [' . implode(', ', $modules) . '].'
            . ($prunedPages ? " {$prunedPages} page grant(s) of revoked module(s) removed." : ''),
            ['before' => $before, 'after' => $modules, 'pruned_pages' => $prunedPages]
        );

        return back()->with('success', 'Module grants updated.'
            . ($prunedPages ? " {$prunedPages} page grant(s) of the removed module(s) were revoked too." : ''));
    }

    /** Save module and page settings in one request so the page grants cannot be skipped. */
    public function updateAccess(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'modules' => 'array',
            'modules.*' => 'string',
            'page_grants' => 'array',
            'page_grants.*.module' => 'required|string',
            'page_grants.*.page' => 'required|string',
            'page_grants.*.level' => 'required|in:view,edit,disabled',
            'supervisor_grants' => 'array',
            'supervisor_grants.*.role' => 'required|string',
            'supervisor_grants.*.page' => 'required|string',
            'supervisor_grants.*.level' => 'required|in:view,edit,disabled',
        ]);

        $user = User::findOrFail($data['user_id']);
        $returnGrant = collect($data['page_grants'] ?? [])->first(fn ($grant) =>
            strtoupper((string) $grant['module']) === 'ECO'
            && strtolower((string) $grant['page']) === 'returns'
            && in_array(strtolower((string) ($grant['level'] ?? '')), ['view', 'edit'], true)
        );
        if ($returnGrant) {
            $ecoPages = $this->modulePages()['ECO'] ?? [];
            if (! array_key_exists('returns', $ecoPages)) {
                throw ValidationException::withMessages(['page_grants' => 'Returns & RMA Management is missing from the ECO permission catalog.']);
            }

            $hasEcoModule = in_array('ECO', array_map('strtoupper', $data['modules'] ?? []), true)
                || strtoupper((string) $user->role) === 'ECO'
                || $this->isElevated($user);
            if (! $hasEcoModule) {
                throw ValidationException::withMessages(['page_grants' => 'This employee is not eligible for ECO module access.']);
            }
        }

        // Module, page and supervisor saves must succeed or fail together.
        // Previously a failure in the page step left the module step committed,
        // which made it look like "only one page saved" while silently granting
        // (or revoking) a whole module.
        DB::transaction(function () use ($request, $data, $user) {
            // Use independent request bags for each legacy sync path. A PHP
            // shallow clone shares Symfony's input bags, so replace() on one
            // clone can overwrite the source request used by the next sync.
            $moduleRequest = Request::createFrom($request);
            $this->replaceRequestInput($moduleRequest, [
                'user_id' => $user->id,
                'modules' => $data['modules'] ?? [],
                // Combined saves only apply the submitted page levels; no
                // home-module page seeding, so one page cannot grant a module.
                'skip_seed' => true,
            ]);
            $this->updateModules($moduleRequest);

            $pageRequest = Request::createFrom($request);
            $this->replaceRequestInput($pageRequest, ['user_id' => $user->id, 'grants' => $data['page_grants'] ?? []]);
            $this->updatePages($pageRequest);

            if ($user->is_manufacturing_supervisor) {
                $supervisorRequest = Request::createFrom($request);
                $this->replaceRequestInput($supervisorRequest, ['user_id' => $user->id, 'grants' => $data['supervisor_grants'] ?? []]);
                $this->updateSupervisorStaffPages($supervisorRequest);
            }
        });

        return back()->with('success', 'Modules and page permissions saved.');
    }

    /** Replace input on a cloned request while preserving Laravel's JSON input source. */
    private function replaceRequestInput(Request $request, array $input): void
    {
        $request->replace($input);
        if ($request->isJson()) {
            $request->json()->replace($input);
        }
    }

    /**
     * Sync per-page view/edit/disabled grants.
     * Only the root module + granted extra modules may receive grants.
     */
    public function updatePages(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'grants' => 'array',
            'grants.*.module' => 'required|string',
            'grants.*.page' => 'required|string',
            'grants.*.level' => 'required|in:view,edit,disabled',
        ]);

        $user = User::findOrFail($data['user_id']);
        $map = $this->modulePages();
        $validPairs = [];
        foreach ($map as $module => $pages) {
            if (strtoupper($module) === 'MAN') {
                continue;
            }
            foreach (array_keys($pages) as $page) {
                $validPairs[] = $module . '.' . strtolower($page);
            }
        }

        // Supervisors can receive page grants only for staff workspaces in
        // their own department. A regular MAN staff member remains scoped to
        // their manufacturing role; elevated MAN accounts keep the full MAN
        // catalog available to IT.
        $manPages = $map['MAN'] ?? ManufacturingPages::OVERVIEW;
        if ($user->is_manufacturing_supervisor) {
            $manPages = ManufacturingPages::forSupervisorAccountDepartment($user->supervisor_department);
        } elseif (strtoupper((string) $user->role) === 'MAN' && $user->position === 'staff' && $user->manufacturing_role) {
            $manPages = ManufacturingPages::forUser($user);
        } elseif (strtoupper((string) $user->role) === 'MAN' && $this->isElevated($user)) {
            $manPages = ManufacturingPages::allPages();
        }
        foreach (array_keys($manPages) as $page) {
            $validPairs[] = 'MAN.' . strtolower($page);
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

        $submittedEcoReturns = collect($data['grants'] ?? [])->first(fn ($grant) =>
            strtoupper((string) ($grant['module'] ?? '')) === 'ECO'
            && strtolower((string) ($grant['page'] ?? '')) === 'returns'
            && in_array(strtolower((string) ($grant['level'] ?? '')), ['view', 'edit'], true)
        );
        if ($submittedEcoReturns) {
            if (! in_array('ECO.returns', $validPairs, true)) {
                throw ValidationException::withMessages(['grants' => 'Returns & RMA Management is missing from the ECO permission catalog.']);
            }
            if (! in_array('ECO', $allowedModules, true)) {
                throw ValidationException::withMessages(['grants' => 'This employee is not eligible for ECO module access.']);
            }
        }

        $before = PagePermission::where('user_id', $user->id)
            ->get()
            ->map(fn ($pp) => $pp->module . '.' . $pp->page . ':' . strtolower($pp->permission_level ?? 'edit'))
            ->toArray();

        $summary = [];
        $provisionedModules = [];
        $allowedUpper = array_values(array_unique(array_map('strtoupper', $allowedModules)));
        DB::transaction(function () use ($user, $data, $validPairs, $allowedModules, $allowedUpper, &$summary, &$provisionedModules) {
            $existing = PagePermission::where('user_id', $user->id)->get(['id', 'module', 'page']);

            // Module access was revoked: drop that module's page rows. Page
            // rows for modules the account still holds are never wiped here —
            // a save that only touches one page must leave every other page
            // exactly as it was.
            $staleIds = $existing
                ->reject(fn ($pp) => in_array(strtoupper((string) $pp->module), $allowedUpper, true))
                ->pluck('id');
            if ($staleIds->isNotEmpty()) {
                PagePermission::whereIn('id', $staleIds)->delete();
            }

            $grantMap = [];
            foreach ($data['grants'] ?? [] as $grant) {
                $module = strtoupper($grant['module']);
                $page = strtolower($grant['page']);
                if (! in_array($module . '.' . $page, $validPairs, true)) {
                    continue;
                }
                if (! in_array($module, $allowedModules, true)) {
                    continue;
                }
                $grantMap[$module][$page] = strtolower($grant['level']);
            }

            $usableModules = [];
            foreach ($grantMap as $module => $pages) {
                foreach ($pages as $page => $level) {
                    $row = $existing->first(fn ($pp) => strtoupper((string) $pp->module) === $module
                        && strtolower((string) $pp->page) === $page);
                    if ($row) {
                        PagePermission::where('id', $row->id)->update(['permission_level' => $level]);
                    } else {
                        PagePermission::create([
                            'user_id' => $user->id,
                            'module' => $module,
                            'page' => $page,
                            'permission_level' => $level,
                        ]);
                    }
                    $summary[] = "{$module}.{$page}:{$level}";
                    if (in_array($level, ['view', 'edit'], true)) {
                        $usableModules[$module] = true;
                    }
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
            . ($provisionedModules ? ', module shell granted for [' . implode(', ', $provisionedModules) . ']' : '') . ').',
            ['before' => $before, 'after' => $summary, 'provisioned_modules' => $provisionedModules]
        );

        $notice = 'Page permissions updated.'
            . ($provisionedModules ? ' Module access granted for [' . implode(', ', $provisionedModules) . '].' : '');

        return back()->with('success', $notice);
    }

    /** Save staff-workspace grants that belong to this supervisor's department. */
    public function updateSupervisorStaffPages(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'grants' => 'array',
            'grants.*.role' => 'required|string',
            'grants.*.page' => 'required|string',
            'grants.*.level' => 'required|in:view,edit,disabled',
        ]);

        $supervisor = User::findOrFail($data['user_id']);
        if (! $supervisor->is_manufacturing_supervisor || ! $supervisor->supervisor_department) {
            throw ValidationException::withMessages(['error' => 'Department staff page grants can only be configured for a manufacturing supervisor.']);
        }

        $allowedRoles = ManufacturingPages::rolesForDepartment($supervisor->supervisor_department);
        $validPairs = [];
        foreach ($allowedRoles as $role) {
            foreach (array_keys(ManufacturingPages::ROLE_PAGES[$role] ?? []) as $page) {
                $validPairs[] = $role . '.' . strtolower($page);
            }
        }

        $grants = collect($data['grants'] ?? [])->map(function ($grant) {
            return [
                'role' => strtolower(trim($grant['role'])),
                'page' => strtolower(trim($grant['page'])),
                'level' => strtolower($grant['level']),
            ];
        });

        $invalid = $grants->first(fn ($grant) => ! in_array($grant['role'] . '.' . $grant['page'], $validPairs, true));
        if ($invalid) {
            throw ValidationException::withMessages(['error' => 'One or more staff pages are outside this supervisor department.']);
        }

        $before = DB::table('man_supervisor_staff_page_permissions')
            ->where('supervisor_user_id', $supervisor->id)
            ->get(['manufacturing_role', 'page', 'permission_level'])
            ->map(fn ($row) => $row->manufacturing_role . '.' . $row->page . ':' . $row->permission_level)
            ->all();

        DB::transaction(function () use ($supervisor, $grants) {
            DB::table('man_supervisor_staff_page_permissions')
                ->where('supervisor_user_id', $supervisor->id)
                ->delete();

            $now = now();
            $rows = $grants->map(fn ($grant) => [
                'supervisor_user_id' => $supervisor->id,
                'manufacturing_role' => $grant['role'],
                'page' => $grant['page'],
                'permission_level' => $grant['level'],
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            if ($rows) {
                DB::table('man_supervisor_staff_page_permissions')->insert($rows);
            }
        });

        $after = $grants->map(fn ($grant) => $grant['role'] . '.' . $grant['page'] . ':' . $grant['level'])->all();
        $this->logAction(
            $supervisor->id,
            'pages.supervisor_staff_updated',
            'Department staff page permissions updated for ' . $supervisor->name . ' (' . count($after) . ' grants).',
            ['before' => $before, 'after' => $after]
        );

        return back()->with('success', 'Department staff page access updated.');
    }

    /**
     * Ensure every page of each granted module has a row — default disabled.
     * Existing view/edit choices are preserved.
     *
     * A home module ($lockedRoot) instead seeds missing pages as 'edit'; IT
     * can still change those page grants afterwards.
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
            // MAN is role-aware: seed only the pages this employee actually
            // handles instead of the whole module union.
            $modulePageKeys = $upper === 'MAN'
                ? array_keys($user->is_manufacturing_supervisor
                    ? ManufacturingPages::forSupervisorAccountDepartment($user->supervisor_department)
                    : ManufacturingPages::forUser($user))
                : array_keys($map[$upper] ?? []);
            foreach ($modulePageKeys as $page) {
                $key = $upper . '.' . strtolower($page);
                if (! $existing->has($key)) {
                    $pageDefault = $default;
                    if ($upper === 'MAN' && $user->is_manufacturing_supervisor
                        && ! array_key_exists($page, ManufacturingPages::OVERVIEW)) {
                        // Preserve the supervisor's existing staff-workspace
                        // access from the legacy Production grant. IT can now
                        // narrow each page independently in Access Control.
                        $pageDefault = $existing->get('MAN.production', $default);
                    }
                    PagePermission::create([
                        'user_id' => $user->id,
                        'module' => $upper,
                        'page' => strtolower($page),
                        'permission_level' => $pageDefault,
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
