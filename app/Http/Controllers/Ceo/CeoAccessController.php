<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Models\Ceo\PositionChangeRequest;
use App\Models\Crm\Client;
use App\Models\Crm\CrmClientAssignment;
use App\Models\Core\PagePermission;
use App\Models\Core\User;
use App\Models\Core\UserModuleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;


class CeoAccessController extends Controller
{
    /**
     * Core modules — each has its own manager + staff hierarchy.
     * CRM added as a core module alongside HRM, MAN, LOG.
     */
    protected $coreModules = ['HRM', 'CRM', 'MAN', 'LOG'];

    /**
     * Feature modules — accessible only via elevated assignment by CEO.
     */
    protected $featureModules = ['ECO', 'ORD', 'SCM', 'WAR', 'INV', 'PRO', 'WRF'];

    /**
     * Pages available per module for staff-level granular access control.
     */
    protected $modulePages = [
        'HRM' => [
            'dashboard' => 'Dashboard',
            'application' => 'Applications',
            'interview' => 'Interviews',
            'trainee' => 'Trainees',
            'employee' => 'Employees',
            'payroll' => 'Payroll',
            'analytics' => 'Analytics',
            'onboarding' => 'Onboarding',
        ],
        'MAN' => [
            'dashboard' => 'Dashboard',
            'knitting' => 'Knitting',
            'dyeing' => 'Dyeing',
            'maintenance' => 'Maintenance',
            'checker' => 'Quality Checker',
        ],
        'LOG' => [
            'dashboard'  => 'Dashboard',
            'load'       => 'Load Packages',
            'dispatch'   => 'Dispatch Center',
            'fleet'      => 'Fleet Management',
            'drivers'    => 'Drivers & Conductors',
            'routes'     => 'Routes',
            'proof'      => 'Proof of Delivery',
            'report'     => 'Conductor Reports',
            'access'     => 'Access Control',
            'interview'  => 'Interviews',
            'trainee'    => 'Trainees',
        ],
        'CRM' => [
            'dashboard' => 'Dashboard',
            'leads' => 'Leads',
            'customer_profiles' => 'Customers',
            'inquiry' => 'Inquiries',
            'interviews' => 'Interviews',
            'trainees' => 'Trainees',
            'approvals' => 'Approvals',
            'investigation' => 'Investigation',
            'access' => 'Access Control',
        ],
        'ECO' => [
            'store' => 'Store',
            'push' => 'Push Notifications',
            'credit' => 'Credit',
            'supplier' => 'Suppliers',
            'client' => 'Clients',
        ],
        'ORD' => [
            'dashboard' => 'Dashboard',
            'orders' => 'Orders',
            'productions' => 'Productions',
            'delivery' => 'Delivery',
            'returns' => 'Returns',
        ],
        'SCM' => [
            'procurement' => 'Procurement',
            'sales' => 'Sales Orders',
            'vendor' => 'Vendors',
        ],
        'WAR' => [
            'warehouse' => 'Warehouse',
            'receiving' => 'Receiving',
            'packages' => 'Packages',
            'monitor' => 'Monitor',
            'reject' => 'Rejects',
        ],
        'INV' => [
            'materials' => 'Materials',
            'products' => 'Products',
            'bom' => 'Bill of Materials',
            'checker' => 'Checker',
        ],
        'PRO' => [
            'procurement' => 'Procurement',
            'quotations' => 'Quotations',
            'receipt' => 'Receipt',
        ],
        'WRF' => [
            'schedule' => 'Schedule',
            'leave' => 'Leave Management',
            'absent' => 'Absences',
        ],
        'FIN' => [
            'overview' => 'Overview',
            'payables' => 'Payables',
            'budget' => 'Budget',
        ],
        'PROJ' => [
            'projects' => 'Projects',
            'tasks' => 'Tasks',
        ],
        'IT' => [
            'systems' => 'Systems',
            'users' => 'Users',
        ],
    ];

    /**
     * Human-readable labels for manufacturing roles.
     */
    protected $manufacturingRoleLabels = [
        'knitting_yarn' => 'Knitting Yarn Staff',
        'knitting_mechanic' => 'Knitting Mechanic Staff',
        'dyeing_color' => 'Dyeing Color Staff',
        'dyeing_fabric_softener' => 'Dyeing Fabric Softener Staff',
        'dyeing_squeezer' => 'Dyeing Squeezer Staff',
        'dyeing_ironing' => 'Dyeing Ironing Staff',
        'dyeing_packaging' => 'Dyeing Packaging Staff',
        'dyeing_lab_chemist' => 'Dyeing Lab Chemist',
        'checker_quality' => 'Checker Quality Staff',
        'maintenance_checker' => 'Maintenance Checker Staff',
        'pollution_control_operator' => 'Pollution Control Operator',
        'safety_officer' => 'Safety Officer Staff',
        'boiler_operator' => 'Boiler Operator Staff',
    ];

    // -----------------------------------------------------------------------
    // Internal Helpers
    // -----------------------------------------------------------------------

    protected function getRootModuleForUser(User $user): ?string
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

        return null;
    }

    protected function getAssignableModulesForUser(User $user): array
    {
        $root = $this->getRootModuleForUser($user);
        if ($root && in_array($root, $this->coreModules)) {
            return array_merge([$root], $this->featureModules);
        }

        return $this->featureModules;
    }

    /**
     * Generate the human-readable "smart label" for a user.
     */
    protected function getSmartLabel(User $user): string
    {
        if ($user->is_manufacturing_supervisor) {
            if ($user->supervisor_department) {
                return ucfirst($user->supervisor_department).' Department Supervisor';
            }

            return 'Supervisor — No Department Assigned';
        }

        if ($user->position === 'vice_president') {
            return 'Vice President';
        }
        if ($user->position === 'manager') {
            return $this->getModuleName($user->role).' Manager';
        }
        if ($user->position === 'special_officer') {
            return $this->getModuleName($user->role).' Special Officer';
        }
        if ($user->position === 'secretary') {
            return $this->getModuleName($user->role).' Secretary';
        }

        switch ($user->role) {
            case 'HRM':
                return 'Office Staff';
            case 'CRM':
                return 'Representative';
            case 'LOG':
                $isDriver = DB::table('drivers')->where('user_id', $user->id)->exists();
                $isConductor = DB::table('conductors')->where('user_id', $user->id)->exists();
                if ($isDriver) {
                    return 'Driver';
                }
                if ($isConductor) {
                    return 'Conductor';
                }
                if (array_key_exists('log_role', $user->getAttributes())) {
                    if ($user->log_role === 'driver') {
                        return 'Driver';
                    }
                    if ($user->log_role === 'conductor') {
                        return 'Conductor';
                    }
                }

                return 'Unassigned Logistics Staff';
            case 'MAN':
                if ($user->manufacturing_role) {
                    return $this->manufacturingRoleLabels[$user->manufacturing_role]
                        ?? ucwords(str_replace('_', ' ', $user->manufacturing_role));
                }

                return 'No Assigned Department Yet';
            default:
                return $this->getModuleName($user->role).' Staff';
        }
    }

    /**
     * Detect whether a LOG staff is a driver or conductor.
     */
    protected function resolveLogRole(User $user): ?string
    {
        if ($user->role !== 'LOG') {
            return null;
        }
        if (DB::table('drivers')->where('user_id', $user->id)->exists()) {
            return 'driver';
        }
        if (DB::table('conductors')->where('user_id', $user->id)->exists()) {
            return 'conductor';
        }
        if (array_key_exists('log_role', $user->getAttributes())) {
            return $user->log_role;
        }

        return null;
    }

    /**
     * Serialize a single user into the shape expected by the Vue component.
     */
    protected function formatUser(User $user): array
    {
        $moduleAccessRecords = $user->moduleAccess ?? collect();

        $grantedModules = $moduleAccessRecords->map(fn ($ma) => [
            'module' => $ma->module,
            'permission_level' => $ma->permission_level ?? 'edit',
        ])->toArray();
        $grantedModuleKeys = array_column($grantedModules, 'module');

        $isElevated = $user->is_manufacturing_supervisor
            || in_array($user->position, ['secretary', 'special_officer']);
        $rootModule = $this->getRootModuleForUser($user);

        if ($isElevated && $rootModule && ! in_array($rootModule, $grantedModuleKeys)) {
            $grantedModules[] = ['module' => $rootModule, 'permission_level' => 'edit'];
            $grantedModuleKeys[] = $rootModule;
        }

        $pagePermRecords = $user->pagePermissions ?? collect();
        $pagePermissions = $pagePermRecords->map(fn ($pp) => [
            'module' => $pp->module,
            'page' => $pp->page,
            'permission_level' => $pp->permission_level ?? 'edit',
        ])->toArray();

        $displayPosition = $user->position;
        if ($user->is_manufacturing_supervisor && $user->position === 'staff') {
            $displayPosition = 'manufacturing_supervisor';
        }

        $photoUrl = $user->profile_photo_path
            ? asset('storage/'.$user->profile_photo_path)
            : null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'employee_id' => $user->employee_id,
            'role' => $user->role,
            'position' => $user->position,
            'display_position' => $displayPosition,
            'is_manufacturing_supervisor' => (bool) $user->is_manufacturing_supervisor,
            'supervisor_department' => $user->supervisor_department,
            'manufacturing_role' => $user->manufacturing_role,
            'log_role' => $this->resolveLogRole($user),
            'granted_modules' => $grantedModules,
            'root_module' => $rootModule,
            'assignable_modules' => $isElevated ? $this->getAssignableModulesForUser($user) : [],
            'page_permissions' => $pagePermissions,
            'smart_label' => $this->getSmartLabel($user),
            'profile_photo' => $photoUrl,
            'is_active' => (bool) $user->is_active,
            'join_date' => $user->join_date,
            'department' => $user->department,
        ];
    }

    private function getModuleName(string $key): string
    {
        $names = [
            'HRM' => 'Human Resource',
            'MAN' => 'Manufacturing',
            'LOG' => 'Logistics',
            'CRM' => 'Customer Relationship',
            'ECO' => 'E-Commerce',
            'ORD' => 'Order Management',
            'SCM' => 'Supply Chain',
            'WAR' => 'Warehouse',
            'INV' => 'Inventory',
            'PRO' => 'Procurement',
            'WRF' => 'Workforce Management',
            'FIN' => 'Finance',
            'PROJ' => 'Project',
            'IT' => 'IT & Systems',
            'CEO' => 'CEO',
        ];

        return $names[$key] ?? $key;
    }

    // -----------------------------------------------------------------------
    // Profile Photo Synchronisation
    // -----------------------------------------------------------------------

    /**
     * Synchronise profile photo between applicant and user (bidirectional).
     * - If applicant has an image and user has no profile_photo_path, copy to user.
     * - If user has a profile_photo_path and applicant has no image, copy to applicant.
     *
     * @param  object|null  $applicant  Row from applicants table
     */
    protected function syncProfilePhoto($applicant, User $user): void
    {
        if (! $applicant) {
            return;
        }

        $applicantImage = $applicant->image ?? null;
        $userPhoto = $user->profile_photo_path;

        // If applicant has image but user does not, copy to user
        if ($applicantImage && ! $userPhoto) {
            $user->profile_photo_path = $applicantImage;
            $user->save();
        }
        // If user has photo but applicant does not, copy to applicant
        elseif ($userPhoto && ! $applicantImage) {
            DB::table('applicants')
                ->where('id', $applicant->id)
                ->update(['image' => $userPhoto]);
        }
    }

    // -----------------------------------------------------------------------
    // Default Staff Page Permissions
    // -----------------------------------------------------------------------

    /**
     * Assign default page permissions for a staff member based on their module.
     * Currently, gives 'dashboard' with 'view' permission for core modules.
     * You can extend this for other modules as needed.
     */
    protected function assignDefaultStaffPagePermissions(User $user): void
    {
        // Only assign if user is a staff member (position === 'staff')
        if ($user->position !== 'staff') {
            return;
        }

        $module = $user->role;
        // Only assign for core modules that have defined pages
        if (! in_array($module, $this->coreModules)) {
            return;
        }

        // Check if the user already has any page permissions for this module
        $existing = PagePermission::where('user_id', $user->id)
            ->where('module', $module)
            ->exists();

        if (! $existing) {
            // Grant default: dashboard with view permission
            $pagePerm = new PagePermission;
            $pagePerm->user_id = $user->id;
            $pagePerm->module = $module;
            $pagePerm->page = 'dashboard';
            $pagePerm->permission_level = 'view';
            $pagePerm->save();
        }
    }

    // -----------------------------------------------------------------------
    // Public Actions
    // -----------------------------------------------------------------------

    /**
     * Main index — returns the full org structure to the Access.vue component.
     */
    public function index()
    {
        $ceo = User::where('role', 'CEO')->where('position', '!=', 'vice_president')->first();

        $vicePresident = User::where('position', 'vice_president')->first();

        $allUsers = User::with(['moduleAccess', 'pagePermissions'])
            ->where('role', '!=', 'CEO')
            ->whereIn('position', ['manager', 'staff', 'secretary', 'special_officer'])
            ->orderBy('name')
            ->get();

        $managers = $allUsers
            ->filter(fn ($u) => $u->position === 'manager' && ! $u->is_manufacturing_supervisor)
            ->map(fn ($u) => $this->formatUser($u))
            ->values();

        $secretary = $allUsers
            ->filter(fn ($u) => $u->position === 'secretary' && ! $u->is_manufacturing_supervisor)
            ->map(fn ($u) => $this->formatUser($u))
            ->first();

        $specialOfficers = $allUsers
            ->filter(fn ($u) => $u->position === 'special_officer' && ! $u->is_manufacturing_supervisor)
            ->map(fn ($u) => $this->formatUser($u))
            ->values();

        $supervisors = $allUsers
            ->filter(fn ($u) => (bool) $u->is_manufacturing_supervisor)
            ->map(fn ($u) => $this->formatUser($u))
            ->values();

        $staff = $allUsers
            ->filter(fn ($u) => $u->position === 'staff' && ! $u->is_manufacturing_supervisor)
            ->map(fn ($u) => $this->formatUser($u))
            ->values();

        $allModules = collect(array_merge($this->coreModules, $this->featureModules))
            ->map(fn ($key) => ['key' => $key, 'name' => $this->getModuleName($key)])
            ->values()
            ->toArray();

        $modulePages = [];
        foreach ($this->modulePages as $module => $pages) {
            $modulePages[$module] = collect($pages)
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
                ->values()
                ->toArray();
        }

        $manufacturingRoles = collect($this->manufacturingRoleLabels)
            ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
            ->values()
            ->toArray();

        // Check if a secretary already exists (for the frontend secretary-limit guard)
        $secretaryExists = User::where('position', 'secretary')
            ->where('role', '!=', 'CEO')
            ->where('is_manufacturing_supervisor', 0)
            ->exists();

        // Pending + recent position commands issued from this executive office.
        // Visible so President/VP can track what IT has / hasn't fulfilled.
        $myRequests = PositionChangeRequest::with('target:id,name,email,employee_id,role,position')
            ->whereIn('status', ['pending', 'fulfilled', 'rejected'])
            ->latest()
            ->take(50)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'target_user_id' => $r->target_user_id,
                'target_name' => $r->target?->name,
                'requested_position' => $r->requested_position,
                'requested_role' => $r->requested_role,
                'supervisor_department' => $r->supervisor_department,
                'action' => $r->action,
                'status' => $r->status,
                'reason' => $r->reason,
                'created_at' => $r->created_at,
            ]);

        $pendingByTarget = PositionChangeRequest::where('status', 'pending')
            ->pluck('id', 'target_user_id')
            ->toArray();

        return Inertia::render('Dashboard/CEO/Access', [
            'ceo' => $ceo ? [
                'name' => $ceo->name,
                'email' => $ceo->email,
                'profile_photo' => $ceo->profile_photo_path ? asset('storage/'.$ceo->profile_photo_path) : null,
            ] : null,
            'secretary' => $secretary,
            'vicePresident' => $vicePresident ? $this->formatUser($vicePresident) : null,
            'specialOfficers' => $specialOfficers,
            'managers' => $managers,
            'supervisors' => $supervisors,
            'staff' => $staff,
            'allModules' => $allModules,
            'modulePages' => $modulePages,
            'manufacturingRoles' => $manufacturingRoles,
            'secretaryExists' => $secretaryExists,
            'myRequests' => $myRequests,
            'pendingByTarget' => $pendingByTarget,
        ]);
    }

    /**
     * Executive position command (President / Vice President → IT).
     * The CEO office is VIEW + REQUEST only — IT fulfils in IT Access Control.
     *
     * Manufacturing-supervisor assignment also flows through here (the MAN
     * access page was removed): requested_position = 'supervisor' with a
     * supervisor_department assigns the seat; 'staff' against a current
     * supervisor removes it (target stays MAN staff).
     */
    public function requestPosition(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'position' => 'required|in:manager,staff,secretary,special_officer,vice_president,supervisor',
            'role' => 'nullable|in:HRM,CRM,MAN,LOG,ECO,ORD,SCM,WAR,INV,PRO,FIN,PROJ,IT',
            'supervisor_department' => 'nullable|in:knitting,dyeing,finishing,maintenance,boiler',
            'reason' => 'nullable|string|max:1000',
        ]);

        $user = User::findOrFail($data['user_id']);
        if ($user->role === 'CEO' && $user->position !== 'vice_president') {
            return back()->withErrors(['error' => 'Cannot request changes for the President account.']);
        }

        // ── Manufacturing-supervisor track ────────────────────────────
        if ($data['position'] === 'supervisor') {
            return $this->requestSupervisorAssign($user, $data);
        }
        if ($user->is_manufacturing_supervisor) {
            // A supervisor going back to plain staff = seat removal command.
            if ($data['position'] !== 'staff') {
                return back()->withErrors(['error' => 'Remove the supervisor seat first (command to staff) before changing position.']);
            }

            return $this->requestSupervisorRemove($user, $data);
        }

        if ($user->position === $data['position'] && empty($data['role'])) {
            return back()->withErrors(['error' => 'Employee already holds this position.']);
        }

        $existing = PositionChangeRequest::where('target_user_id', $user->id)
            ->where('status', 'pending')
            ->first();
        if ($existing) {
            return back()->withErrors(['error' => "A pending command already exists for {$user->name} ({$existing->requested_position}). Wait for IT to fulfil it."]);
        }

        $rank = ['staff' => 0, 'manager' => 1, 'special_officer' => 2, 'secretary' => 2, 'vice_president' => 3];
        $action = ($rank[$data['position']] ?? 0) > ($rank[$user->position] ?? 0) ? 'promote' : 'demote';
        if ($user->position === $data['position']) {
            $action = 'demote';
        }

        PositionChangeRequest::create([
            'target_user_id' => $user->id,
            'requested_by' => auth()->id(),
            'requester_role' => auth()->user()->position === 'vice_president' ? 'Vice President' : 'President',
            'current_position' => $user->position,
            'requested_position' => $data['position'],
            'current_role' => $user->role,
            'requested_role' => $data['role'] ?? $user->role,
            'action' => $action,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('success', "Command sent to IT: {$action} {$user->name} to {$data['position']}.");
    }

    /**
     * Command IT to assign a MAN staffer to a supervisor department seat.
     */
    protected function requestSupervisorAssign(User $user, array $data)
    {
        if ($user->role !== 'MAN' || $user->position !== 'staff') {
            return back()->withErrors(['error' => 'Only MAN staff can be assigned a supervisor seat.']);
        }
        if (empty($data['supervisor_department'])) {
            return back()->withErrors(['error' => 'Choose a supervisor department (knitting, dyeing, finishing, maintenance, boiler).']);
        }
        if ($user->is_manufacturing_supervisor && $user->supervisor_department === $data['supervisor_department']) {
            return back()->withErrors(['error' => "{$user->name} already supervises {$data['supervisor_department']}."]);
        }

        // One supervisor per department — fail fast with the occupant's name.
        $occupant = User::where('is_manufacturing_supervisor', true)
            ->where('supervisor_department', $data['supervisor_department'])
            ->where('id', '!=', $user->id)
            ->first(['id', 'name']);
        if ($occupant) {
            return back()->withErrors(['error' => "The {$data['supervisor_department']} seat is held by {$occupant->name}. Command their removal first."]);
        }

        if ($this->hasPendingCommand($user->id)) {
            return back()->withErrors(['error' => "A pending command already exists for {$user->name}. Wait for IT to fulfil it."]);
        }

        PositionChangeRequest::create([
            'target_user_id' => $user->id,
            'requested_by' => auth()->id(),
            'requester_role' => auth()->user()->position === 'vice_president' ? 'Vice President' : 'President',
            'current_position' => $user->is_manufacturing_supervisor ? 'supervisor' : $user->position,
            'requested_position' => 'supervisor',
            'current_role' => $user->role,
            'requested_role' => 'MAN',
            'supervisor_department' => $data['supervisor_department'],
            'action' => 'assign_supervisor',
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('success', "Command sent to IT: assign {$user->name} as {$data['supervisor_department']} supervisor.");
    }

    /**
     * Command IT to strip a supervisor seat (target stays MAN staff).
     */
    protected function requestSupervisorRemove(User $user, array $data)
    {
        if ($this->hasPendingCommand($user->id)) {
            return back()->withErrors(['error' => "A pending command already exists for {$user->name}. Wait for IT to fulfil it."]);
        }

        PositionChangeRequest::create([
            'target_user_id' => $user->id,
            'requested_by' => auth()->id(),
            'requester_role' => auth()->user()->position === 'vice_president' ? 'Vice President' : 'President',
            'current_position' => 'supervisor',
            'requested_position' => 'staff',
            'current_role' => $user->role,
            'requested_role' => 'MAN',
            'supervisor_department' => null,
            'action' => 'remove_supervisor',
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('success', "Command sent to IT: remove {$user->name} from the {$user->supervisor_department} supervisor seat.");
    }

    protected function hasPendingCommand(int $userId): bool
    {
        return PositionChangeRequest::where('target_user_id', $userId)
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * Cancel own pending command.
     */
    public function cancelRequest(Request $request, int $id)
    {
        $cmd = PositionChangeRequest::findOrFail($id);
        if ($cmd->status !== 'pending') {
            return back()->withErrors(['error' => 'Only pending commands can be cancelled.']);
        }
        // President/VP may cancel any pending command from the executive office.
        $cmd->status = 'cancelled';
        $cmd->reviewed_by = auth()->id();
        $cmd->reviewed_at = now();
        $cmd->save();

        return back()->with('success', 'Command cancelled.');
    }

    /**
     * REMOVED POWERS — the executive office is view + request only.
     * Direct promotion / module / page / role / photo / client writes now
     * belong to IT Access Control. These stubs keep old calls from
     * silently succeeding.
     */
    public function updatePosition(Request $request)
    {
        return back()->withErrors(['error' => 'Direct position changes are disabled. Send a command to IT instead — IT will promote/demote after your approval.']);
    }


    
    /**
     * Disabled — executive office is view + request only.
     */
    public function updateModules(Request $request)
    {
        return back()->withErrors(['error' => 'Module transfers are disabled. IT Access Control now owns module grants.']);
    }

    
    /**
     * Disabled — executive office is view + request only.
     */
    public function updateStaffPages(Request $request)
    {
        return back()->withErrors(['error' => 'Page permission writes are disabled. IT Access Control now owns page grants.']);
    }

    
    /**
     * Disabled — executive office is view + request only.
     */
    public function assignStaffRole(Request $request)
    {
        return back()->withErrors(['error' => 'Role assignment is disabled. IT Access Control now owns staff roles.']);
    }

    
    /**
     * Disabled — executive office is view + request only.
     */
    public function updateProfilePhoto(Request $request)
    {
        return back()->withErrors(['error' => 'Profile photo writes are disabled. IT Access Control now owns account changes.']);
    }

    /**
     * Return full personal/application-form information for a specific employee.
     * Links users → applicants via matching email address.
     */
    public function getEmployeePersonalInfo(int $id)
    {
        $user = User::findOrFail($id);

        // Attempt to find the matching applicant record by email
        $applicant = DB::table('applicants')
            ->where('email', $user->email)
            ->first();

        // Synchronise profile photo between both tables (bidirectional)
        if ($applicant) {
            $this->syncProfilePhoto($applicant, $user);
            // Refresh user to get the latest profile_photo_path after sync
            $user->refresh();
        }

        // Unified profile photo: applicant image first, then user's profile photo
        $profilePhotoUrl = null;
        if ($applicant && $applicant->image) {
            $profilePhotoUrl = asset('storage/'.$applicant->image);
        } elseif ($user->profile_photo_path) {
            $profilePhotoUrl = asset('storage/'.$user->profile_photo_path);
        }

        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'employee_id' => $user->employee_id,
            'role' => $user->role,
            'position' => $user->position,
            'join_date' => $user->join_date,
            'department' => $user->department,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at,
            'profile_photo' => $profilePhotoUrl,
        ];

        $applicantData = null;
        if ($applicant) {
            // Decode JSON columns
            $children = $applicant->children ? json_decode($applicant->children, true) : null;
            $employmentRecords = $applicant->employment_records ? json_decode($applicant->employment_records, true) : null;
            $relatedEmployees = $applicant->related_employees ? json_decode($applicant->related_employees, true) : null;

            $applicantData = [
                // ── Personal ────────────────────────────────────────────────
                'first_name' => $applicant->first_name,
                'middle_name' => $applicant->middle_name,
                'last_name' => $applicant->last_name,
                'date_of_birth' => $applicant->date_of_birth,
                'place_of_birth' => $applicant->place_of_birth,
                'citizenship' => $applicant->citizenship,
                'weight' => $applicant->weight,
                'height' => $applicant->height,
                'civil_status' => $applicant->civil_status,
                'sex' => $applicant->sex,
                'age' => $applicant->age,
                'religion' => $applicant->religion,
                'contact_number' => $applicant->contact_number,
                'phone_number' => $applicant->phone_number,
                'image' => $applicant->image ? asset('storage/'.$applicant->image) : null,

                // ── Address ─────────────────────────────────────────────────
                'street_address' => $applicant->street_address,
                'street_address_line2' => $applicant->street_address_line2,
                'city' => $applicant->city,
                'state_province' => $applicant->state_province,
                'postal_zip_code' => $applicant->postal_zip_code,

                // ── Government IDs (file URLs removed for security — served via authorized routes only) ──
                'sss_number' => $applicant->sss_number,
                'philhealth_number' => $applicant->philhealth_number,
                'pagibig_number' => $applicant->pagibig_number,

                // ── Family ──────────────────────────────────────────────────
                'spouse_name' => $applicant->spouse_name,
                'spouse_occupation' => $applicant->spouse_occupation,
                'spouse_address' => $applicant->spouse_address,
                'number_of_children' => $applicant->number_of_children,
                'children' => $children,
                'mother_name' => $applicant->mother_name,
                'mother_address' => $applicant->mother_address,
                'father_name' => $applicant->father_name,
                'father_address' => $applicant->father_address,

                // ── Emergency Contact ────────────────────────────────────────
                'emergency_name' => $applicant->emergency_name,
                'emergency_relationship' => $applicant->emergency_relationship,
                'emergency_phone' => $applicant->emergency_phone,
                'emergency_address' => $applicant->emergency_address,

                // ── Education ───────────────────────────────────────────────
                'elementary_school' => $applicant->elementary_school,
                'elementary_year' => $applicant->elementary_year,
                'high_school' => $applicant->high_school,
                'high_year' => $applicant->high_year,
                'college' => $applicant->college,
                'college_year' => $applicant->college_year,
                'vocational' => $applicant->vocational,
                'vocational_year' => $applicant->vocational_year,

                // ── Employment History ───────────────────────────────────────
                'has_employment_record' => (bool) $applicant->has_employment_record,
                'employment_records' => $employmentRecords,
                'previous_employment_company' => $applicant->previous_employment_company,
                'previous_employment_when' => $applicant->previous_employment_when,
                'previous_employment_position' => $applicant->previous_employment_position,
                'previous_employment_department' => $applicant->previous_employment_department,

                // ── Skills & Other ───────────────────────────────────────────
                'languages' => $applicant->languages,
                'special_skills' => $applicant->special_skills,
                'machine_operation' => $applicant->machine_operation,
                'position_applied' => $applicant->position_applied,
                'notice_period' => $applicant->notice_period,

                // ── References / Relatives ───────────────────────────────────
                'referred_by' => $applicant->referred_by,
                'referred_by_address' => $applicant->referred_by_address,
                'related_employees' => $relatedEmployees,

                // ── Application meta ─────────────────────────────────────────
                'status' => $applicant->status,
                'assigned_module' => $applicant->assigned_module,
                'application_date' => $applicant->created_at,
            ];
        }

        return response()->json([
            'user' => $userData,
            'applicant' => $applicantData,
        ]);
    }

    // -----------------------------------------------------------------------
    // Client assignment for CRM staff
    // -----------------------------------------------------------------------

    /**
     * Get all active clients and the ones already assigned to a specific CRM staff member.
     */
    public function getClientAssignments(int $staffId)
    {
        $staff = User::findOrFail($staffId);

        if ($staff->role !== 'CRM' || $staff->position !== 'staff') {
            return response()->json(['error' => 'User is not a CRM staff member.'], 403);
        }

        // Get all active clients (status 'active')
        $clients = Client::where('status', 'active')
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'contact_person', 'email']);

        // Get already assigned client IDs
        $assignedIds = CrmClientAssignment::where('staff_id', $staffId)
            ->pluck('client_id')
            ->toArray();

        return response()->json([
            'clients' => $clients,
            'assigned_client_ids' => $assignedIds,
        ]);
    }

    
    /**
     * Disabled — executive office is view + request only.
     */
    public function updateClientAssignments(Request $request)
    {
        return back()->withErrors(['error' => 'Client assignment writes are disabled. IT / CRM module owns assignments now.']);
    }
}