<?php

namespace App\Models\Core;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Hrm\AuditLog;
use App\Models\Hrm\TraineeGrade;
use App\Models\Hrm\HrmTrainingEnrollment;
use App\Models\Hrm\TraineeTransfer;
use App\Models\Hrm\TraineeWeeklyEvaluation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Hrm\AttendanceLog;
use App\Models\Hrm\EmployeeShift;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmInteraction;
use App\Models\Hrm\LeaveRequest;
use App\Models\Man\Fabric;
use App\Models\Man\DyeJob;
use App\Models\Man\SoftenerJob;
use App\Models\Man\SqueezerJob;
use App\Models\Man\IronJob;
use App\Models\Man\Package;
use App\Models\Man\MachineReport;   
use App\Models\Man\ManufacturingSupervisorRole;
use App\Models\Pro\ProAccess;
use App\Models\Scm\ScmAccessPermission;
use App\Models\Core\UserModuleAccess;
use App\Models\War\Warehouse;
use App\Models\Eco\CreditAccount;
use App\Models\Ord\PurchaseOrder;
use App\Models\Ord\PurchaseOrderItem;
use App\Models\Ord\PurchaseOrderPayment;
use App\Models\Ord\PurchaseOrderShipment;
use App\Models\Ord\PurchaseOrderInvoice;
use App\Models\Ord\PurchaseOrderInvoicePayment;
use App\Models\Ord\PurchaseOrderInvoiceItem;
use App\Models\Ord\PurchaseOrderInvoiceShipment;
use App\Models\Ord\PurchaseOrderInvoiceShipmentItem;
use App\Models\Ord\PurchaseOrderInvoiceShipmentTracking;
use App\Models\Ord\PurchaseOrderInvoiceShipmentTrackingUpdate;
use App\Models\Ord\PurchaseOrderInvoiceShipmentTrackingUpdateAttachment;

use App\Models\Core\PagePermission;
use App\Models\Work\WorkforcePermission;
use App\Models\Crm\CrmPagePermission;   
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_photo_path',
        'employee_id',
        'worker_id',
        'department',
        'join_date',
        // Fields for promotion suggestion logic
        'promotion_suggested',
        'suggested_at',
        'manufacturing_role',
        'is_manufacturing_supervisor',
        'supervisor_department',
        // HRM_NEW dynamic hooks (DB-rooted roles/positions)
        'hrm_role_id',
        'hrm_department_id',
        'hrm_position_id',
        'hrm_employment_type_id',
        // Employee Master Record fields
        'first_name', 'middle_name', 'last_name', 'suffix', 'nickname', 'preferred_name',
        'gender', 'date_of_birth', 'civil_status', 'nationality', 'personal_email',
        'mobile_number', 'telephone', 'emergency_contact_name', 'emergency_contact_number',
        'current_address', 'permanent_address', 'country', 'province', 'city', 'postal_code',
        'company', 'branch', 'business_unit', 'cost_center', 'work_location',
        'department_head', 'immediate_supervisor', 'payroll_group', 'salary_grade',
        'basic_salary', 'bank_account', 'tax_id', 'sss_id', 'work_schedule', 'shift',
        'timezone', 'biometrics_id', 'probation_end_date', 'regularization_date',
        'resignation_date', 'last_working_day', 'education', 'certifications',
        'skills', 'languages', 'trainings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // Casting new fields for proper data handling
            'promotion_suggested' => 'boolean',
            'suggested_at' => 'datetime',
            'is_manufacturing_supervisor' => 'boolean',
        ];
    }

    /**
     * Scope for HRM department users
     */
    public function scopeHrm($query)
    {
        return $query->where('role', 'HRM');
    }

    /**
     * Scope for SCM department users
     */
    public function scopeScm($query)
    {
        return $query->where('role', 'SCM');
    }

    public function scopeFin($query)
    {
        return $query->where('role', 'FIN');
    }

    public function scopeMan($query)
    {
        return $query->where('role', 'MAN');
    }

    public function scopeInv($query)
    {
        return $query->where('role', 'INV');
    }

    public function scopeOrd($query)
    {
        return $query->where('role', 'ORD');
    }

    public function scopeWar($query)
    {
        return $query->where('role', 'WAR');
    }

    public function scopeCrm($query)
    {
        return $query->where('role', 'CRM');
    }

    public function scopeEco($query)
    {
        return $query->where('role', 'ECO');
    }

    public function traineeGrade()
    {
        return $this->hasOne(TraineeGrade::class);
    }

    public function trainingEnrollments(): HasMany
    {
        return $this->hasMany(HrmTrainingEnrollment::class);
    }

    public function traineeTransfers(): HasMany
    {
        return $this->hasMany(TraineeTransfer::class);
    }

    public function weeklyEvaluations(): HasMany
    {
        return $this->hasMany(TraineeWeeklyEvaluation::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function leaveRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Get all attendance logs for the user.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    /**
     * Get the most recent attendance log (used in the Controller).
     */
    public function latestAttendance(): HasOne
    {
        return $this->hasOne(AttendanceLog::class)->latestOfMany('date');
    }

    /**
     * Get the shifts assigned to the user.
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(EmployeeShift::class);
    }

    /**
     * Get the current active shift (used in the Controller).
     */
    public function currentShift(): HasOne
    {
        // This helps the 'with' query in your controller find today's shift
        return $this->hasOne(EmployeeShift::class)->latestOfMany('effective_date');
    }

    public function leads()
    {
        return $this->hasMany(CrmLead::class, 'assigned_staff_id');
    }

    public function interactions()
    {
        return $this->hasMany(CrmInteraction::class);
    }

    public function performedLogs()
    {
        return $this->hasMany(AuditLog::class, 'admin_id');
    }

    public function receivedLogs()
    {
        return $this->hasMany(AuditLog::class, 'target_id');
    }

    public function auditLogs()
    {
        // The foreign key is 'target_id' based on your SQL file
        return $this->hasMany(AuditLog::class, 'target_id');
    }

    // Relationships for manufacturing
    public function fabrics()
    {
        return $this->hasMany(Fabric::class, 'operator_id');
    }

    public function dyeJobs()
    {
        return $this->hasMany(DyeJob::class, 'operator_id');
    }

    public function softenerJobs()
    {
        return $this->hasMany(SoftenerJob::class, 'operator_id');
    }

    public function squeezerJobs()
    {
        return $this->hasMany(SqueezerJob::class, 'operator_id');
    }

    public function ironJobs()
    {
        return $this->hasMany(IronJob::class, 'operator_id');
    }

    public function packages()
    {
        return $this->hasMany(Package::class, 'operator_id');
    }

    public function reportedMachineReports()
    {
        return $this->hasMany(MachineReport::class, 'reported_by');
    }

    public function resolvedMachineReports()
    {
        return $this->hasMany(MachineReport::class, 'resolved_by');
    }

    public function hasPagePermission($module, $page, $requiredLevel = 'view')
    {
        $requiredLevel = strtolower($requiredLevel ?? 'view');
        if (! in_array($requiredLevel, ['view', 'edit'], true)) {
            $requiredLevel = 'view';
        }

        // Superusers (secretary, special officers) have full access.
        // NOTE (overseer model): the President is intentionally excluded —
        // executive oversight runs through the CEO module, not module pages.
        if (in_array($this->position, ['secretary', 'special_officer'])) {
            return true;
        }
        // Module-native managers and staff have full access to their own module —
        // UNLESS explicit page_permissions rows exist for them in that module,
        // in which case the explicit grants are the exact access set.
        if (strtoupper((string) $this->role) === strtoupper((string) $module)
            && in_array($this->position, ['manager', 'staff'])) {
            $moduleVariants = [$module, strtoupper((string) $module), strtolower((string) $module)];
            if (! PagePermission::where('user_id', $this->id)->whereIn('module', $moduleVariants)->exists()) {
                return true;
            }
            // Fall through to the strict row check below.
        }

        // Otherwise check the explicit page_permissions record ('edit' implies 'view').
        // Legacy NULL levels are grandfathered as 'edit'. Matching is
        // case-tolerant on both module and page.
        $record = PagePermission::where('user_id', $this->id)
            ->whereIn('module', [$module, strtoupper((string) $module), strtolower((string) $module)])
            ->get(['page', 'permission_level'])
            ->first(fn ($row) => strtolower((string) $row->page) === strtolower((string) $page));

        // An explicit 'disabled' row always wins — even for executives.
        if ($record !== null && strtolower($record->permission_level ?? 'edit') === 'disabled') {
            return false;
        }

        // Vice President: no HRM pages — the COO role is standalone.
        if (strtoupper((string) $module) === 'HRM'
            && (in_array($this->role, ['COO'], true)
                || ($this->position ?? '') === 'vice_president')) {
            return false;
        }

        // President & Vice President have full access to every CRM page.
        if (strtoupper((string) $module) === 'CRM'
            && (in_array($this->role, ['CEO', 'COO'], true)
                || ($this->position ?? '') === 'vice_president')) {
            return true;
        }

        if ($record === null) {
            return false;
        }

        $grantedLevel = strtolower($record->permission_level ?? 'edit');

        return $requiredLevel === 'view'
            ? in_array($grantedLevel, ['view', 'edit'], true)
            : $grantedLevel === 'edit';
    }

    /**
     * Get the page permissions associated with the user.
     */
    public function pagePermissions()
    {
        return $this->hasMany(PagePermission::class);
    }

    /**
     * Whether this user can reach the CRM Messaging page (and therefore be
     * called by another CRM user). Mirrors CheckPagePermission's shortcuts:
     * executives have full CRM, module-native CRM staff with no explicit
     * grants do too, and an explicit 'disabled' row always wins.
     */
    public function canAccessCrmMessaging(): bool
    {
        $rows = $this->pagePermissions()
            ->whereIn('module', ['CRM', 'crm', 'Crm'])
            ->get(['page', 'permission_level']);

        $messaging = $rows->first(fn ($row) => strtolower((string) $row->page) === 'messaging');
        if ($messaging !== null) {
            return strtolower((string) ($messaging->permission_level ?? 'edit')) !== 'disabled';
        }

        $role = strtoupper((string) $this->role);
        if (in_array($role, ['CEO', 'COO'], true) || ($this->position ?? '') === 'vice_president') {
            return true;
        }

        // Native CRM staff have full access unless explicit grants exist.
        if ($role === 'CRM') {
            return $rows->isEmpty();
        }

        if ($this->position === 'secretary' && str_contains($role, 'CRM')) {
            return true;
        }

        return false;
    }

    // HRM_NEW dynamic org links (nullable for BC rollout)
    public function hrmRole()
    {
        return $this->belongsTo(\App\Models\Hrm\HrmRole::class, 'hrm_role_id');
    }

    public function hrmDepartment()
    {
        return $this->belongsTo(\App\Models\Hrm\HrmDepartment::class, 'hrm_department_id');
    }

    public function hrmOrgPosition()
    {
        return $this->belongsTo(\App\Models\Hrm\HrmPosition::class, 'hrm_position_id');
    }

    public function hrmEmploymentType()
    {
        return $this->belongsTo(\App\Models\Hrm\HrmEmploymentType::class, 'hrm_employment_type_id');
    }

    public function workforcePermissions()
    {
        return $this->hasMany(WorkforcePermission::class);
    }

    /**
     * Check if user can access workforce module for a specific module/department.
     *
     * @param  string|null  $module  e.g., 'HRM', null means any module
     * @param  string|null  $department  e.g., 'dyeing'
     * @param  string  $requiredLevel  'view', 'schedule', 'manage'
     * @return bool
     */
    public function canAccessWorkforce($module = null, $department = null, $requiredLevel = 'view')
    {
        // NOTE (overseer model): no President bypass — workforce access
        // follows the same grant/permission rules as everyone else.

        // 1. Secretary or Special Officer: check if they have 'WRF' in granted_modules
        if (in_array($this->position, ['secretary', 'special_officer'])) {
            $grantedModules = $this->moduleAccess->pluck('module')->toArray();
            if (in_array('WRF', $grantedModules)) {
                // They have full access (manage level) to workforce module
                return true;
            }

            // If they don't have WRF granted, they cannot access workforce pages
            return false;
        }

        // 2. For all other users (managers, staff, supervisors): check workforce_permissions table
        $permissions = $this->workforcePermissions;
        if ($permissions->isEmpty()) {
            return false;
        }

        // Define access level hierarchy
        $levelRank = [
            'view' => 1,
            'schedule' => 2,
            'manage' => 3,
        ];
        $requiredRank = $levelRank[$requiredLevel] ?? 1;

        foreach ($permissions as $perm) {
            // Check module match: if $module is provided, permission must have same module OR null (wildcard)
            $moduleMatch = is_null($module) || is_null($perm->module) || $perm->module === $module;
            // Check department match: if $department is provided, permission must have same department OR null (wildcard)
            $deptMatch = is_null($department) || is_null($perm->department) || $perm->department === $department;

            if ($moduleMatch && $deptMatch) {
                $permRank = $levelRank[$perm->access_level] ?? 0;
                if ($permRank >= $requiredRank) {
                    return true;
                }
            }
        }

        return false;
    }

    public function crmPagePermissions()
    {
        return $this->hasMany(CrmPagePermission::class);
    }

    public function canAccessCrmPage($page)
    {
        // Managers can access all pages? Not necessarily; we'll check permissions
        // But for simplicity, we'll rely on the permissions table.
        // NOTE (overseer model): no President bypass.
        return CrmPagePermission::where('user_id', $this->id)->where('page', $page)->exists();
    }

    // Warehouse and Inventory Access Relationships
    public function warehouseAccess()
    {
        return $this->belongsToMany(Warehouse::class, 'user_warehouse_access', 'user_id', 'warehouse_id');
    }

    public function inventoryAccess()
    {
        return $this->belongsToMany(Warehouse::class, 'user_inventory_access', 'user_id', 'warehouse_id');
    }

    // app/Models/User.php
    public function proAccess()
    {
        return $this->hasOne(ProAccess::class);
    }

    /**
     * Check if user has any warehouse assigned (raw DB query to avoid ambiguous column).
     */
    public function hasWarehouseAccess(): bool
    {
        return \DB::table('user_warehouse_access')
            ->where('user_id', $this->id)
            ->exists();
    }

    /**
     * Check if user has any inventory access (raw DB query).
     */
    public function hasInventoryAccess(): bool
    {
        return \DB::table('user_inventory_access')
            ->where('user_id', $this->id)
            ->exists();
    }

    public function assignedWarehouses()
    {
        return $this->warehouseAccess();
    }

    public function scmAccess()
    {
        return $this->hasOne(ScmAccessPermission::class);
    }

    public function supervisorRoles()
    {
        return $this->hasMany(ManufacturingSupervisorRole::class);
    }

    public function isManufacturingSupervisor()
    {
        return $this->is_manufacturing_supervisor;
    }

    public function getAssignedManufacturingRoles()
    {
        return $this->supervisorRoles->pluck('manufacturing_role')->toArray();
    }

    /**
     * Get the module access entries for this user (secretary/general manager).
     */
    public function moduleAccess()
    {
        return $this->hasMany(UserModuleAccess::class);
    }

    public function manufacturingSupervisorRoles()
    {
        return $this->hasMany(ManufacturingSupervisorRole::class);
    }

    /**
     * Get the list of manufacturing roles this supervisor can oversee (based on department).
     */
    public function getSupervisedRolesAttribute()
    {
        if (! $this->is_manufacturing_supervisor || ! $this->supervisor_department) {
            return [];
        }

        return match ($this->supervisor_department) {
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
    }

    /**
     * Check if this supervisor can manage a given manufacturing role.
     */
    public function canSuperviseRole(string $role): bool
    {
        return in_array($role, $this->supervised_roles);
    }

    /**
     * Get all staff members under this supervisor's department.
     */
    public function getDepartmentStaff()
    {
        if (! $this->is_manufacturing_supervisor) {
            return collect();
        }

        $roles = $this->supervised_roles;

        return User::where('role', 'MAN')
            ->where('position', 'staff')
            ->whereIn('manufacturing_role', $roles)
            ->get();
    }

    /**
     * Helper method to check if user can access a specific module.
     * For secretaries/GMs: if granted_modules is empty → fallback to original role.
     * Once any module is explicitly granted, only those modules are allowed.
     */
    public function canAccessModule(string $module): bool
    {
        // NOTE (overseer model): no President bypass — the CEO role alone
        // grants nothing outside the CEO module (see CheckModuleAccess).

        // For secretaries and general managers
        if (in_array($this->position, ['secretary', 'special_officer'])) {
            $granted = $this->moduleAccess->pluck('module')->toArray();

            // If no modules have been explicitly granted yet,
            // fall back to the user's original role (e.g., HRM, MAN, etc.)
            if (empty($granted)) {
                return $this->role === $module;
            }

            // Otherwise, check only the explicitly granted modules
            return in_array($module, $granted);
        }

        // Normal managers/staff have default access based on their role
        return $this->role === $module;
    }

    /**
     * Get list of modules this user is explicitly granted (for frontend).
     */
    public function getGrantedModulesAttribute()
    {
        return $this->moduleAccess->pluck('module')->toArray();
    }

    /**
     * Get appropriate dashboard path based on department and position.
     * For secretaries and general managers, redirect to the manager dashboard
     * of their original role (since they retain access via fallback).
     */
    public function getDashboardPathAttribute(): string
    {
        // If the position is trainee, redirect to a single unified trainee dashboard
        if ($this->position === 'trainee') {
            return route('trainee.dashboard');
        }

        // Secretaries land on their own executive workspace.
        if ($this->position === 'secretary') {
            return route('secretary.dashboard');
        }

        // Secretaries and general managers: use the manager dashboard of their role
        if (in_array($this->position, ['secretary', 'special_officer'])) {
            return match ($this->role) {
                'HRM' => route('hrm.manager.dashboard'),
                'SCM' => route('scm.manager.dashboard'),
                'FIN' => route('fin.manager.dashboard'),
                'MAN' => route('man.manager.dashboard'),
                'INV' => route('inv.manager.dashboard'),
                'ORD' => route('ord.manager.dashboard'),
                'WAR' => route('war.manager.dashboard'),
                'CRM' => route('crm.manager.dashboard'),
                'ECO' => route('eco.manager.dashboard'),
                'PRO' => route('pro.manager.dashboard'),
                'PROJ' => route('proj.manager.dashboard'),
                'IT' => route('it.dashboard'),
                default => route('dashboard'),
            };
        }

        // For normal managers and staff
        return match ([$this->role, $this->position]) {
            ['HRM', 'manager'] => route('hrm.manager.dashboard'),
            ['HRM', 'staff'] => route('hrm.employee.dashboard'),
            ['SCM', 'manager'] => route('scm.manager.dashboard'),
            ['SCM', 'staff'] => route('scm.employee.dashboard'),
            ['FIN', 'manager'] => route('fin.manager.dashboard'),
            ['FIN', 'staff'] => route('fin.employee.dashboard'),
            ['MAN', 'manager'] => route('man.manager.dashboard'),
            ['MAN', 'staff'] => route('man.employee.dashboard'),
            ['INV', 'manager'] => route('inv.manager.dashboard'),
            ['INV', 'staff'] => route('inv.employee.dashboard'),
            ['ORD', 'manager'] => route('ord.manager.dashboard'),
            ['ORD', 'staff'] => route('ord.employee.dashboard'),
            ['WAR', 'manager'] => route('war.manager.dashboard'),
            ['WAR', 'staff'] => route('war.employee.dashboard'),
            ['CRM', 'manager'] => route('crm.manager.dashboard'),
            ['CRM', 'staff'] => route('crm.employee.dashboard'),
            ['ECO', 'manager'] => route('eco.manager.dashboard'),
            ['ECO', 'staff'] => route('eco.employee.dashboard'),
            ['PRO', 'manager'] => route('pro.manager.dashboard'),
            ['PRO', 'staff'] => route('pro.employee.dashboard'),
            ['PROJ', 'manager'] => route('proj.manager.dashboard'),
            ['PROJ', 'staff'] => route('proj.employee.dashboard'),
            ['IT', 'manager'] => route('it.dashboard'),
            ['IT', 'staff'] => route('it.dashboard'),
            default => route('dashboard'),
        };
    }
}
