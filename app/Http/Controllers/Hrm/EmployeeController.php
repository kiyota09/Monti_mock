<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\AuditLog;
use App\Models\Core\User;
use App\Support\TerminatedAccess;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Traits\HasPagePermissions;

class EmployeeController extends Controller
{
    use AuthorizesRequests, HasPagePermissions;

    /**
     * Core modules only (HRM, CRM, MAN, LOG)
     */
    private $coreModules = ['HRM', 'CRM', 'MAN', 'LOG'];

    /**
     * Strict Rank Engine
     */
    private function getRank($user)
    {
        if (strtoupper($user->role) === 'CEO') {
            return 60;
        }

        $pos = strtolower($user->position);
        if ($pos === 'secretary') {
            return 50;
        }
        if ($pos === 'special_officer') {
            return 40;
        }
        if ($pos === 'manager') {
            return 30;
        }
        if ($pos === 'supervisor') {
            return 20;
        }
        if ($pos === 'staff') {
            return 10;
        }

        return 0;
    }

    private function getPosRank($position)
    {
        $pos = strtolower($position);
        if ($pos === 'ceo') {
            return 60;
        }
        if ($pos === 'secretary') {
            return 50;
        }
        if ($pos === 'special_officer') {
            return 40;
        }
        if ($pos === 'manager') {
            return 30;
        }
        if ($pos === 'supervisor') {
            return 20;
        }
        if ($pos === 'staff') {
            return 10;
        }

        return 0;
    }

    /**
     * Check if current user can promote a staff to manager.
     * Allowed: CEO, Secretary, Special Officer.
     */
    private function canPromoteToManager($currentUser)
    {
        $rank = $this->getRank($currentUser);
        return $rank >= 40;
    }

    /**
     * Check if current user can demote a manager to staff.
     * Allowed: CEO only.
     */
    private function canDemoteManager($currentUser)
    {
        return strtoupper($currentUser->role) === 'CEO';
    }

    /**
     * Display a listing of employees (only core modules, exclude CEO).
     */
    public function index(Request $request)
    {
        $currentUser = Auth::user();

        $employees = User::with(['auditLogs' => function ($q) {
            $q->orderBy('created_at', 'desc');
        }])
            ->whereIn('role', $this->coreModules)
            ->where('id', '!=', 1)
            ->where('role', '!=', 'CEO')
            ->orderBy('role')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(function ($emp) {
                return $this->formatEmployee($emp);
            });

        $canPromote = $this->canPromoteToManager($currentUser);
        $canDemoteManager = $this->canDemoteManager($currentUser);
        
        // Get page permissions for the current user (HRM module)
        $pagePermissions = $this->getPagePermissionsForModule('HRM');

        return Inertia::render('Dashboard/HRM_NEW/EmployeeLegacy', [
            'employees' => $employees,
            'permissions' => [
                'can_promote' => $canPromote,
                'can_demote_manager' => $canDemoteManager,
                'current_user_rank' => $this->getRank($currentUser),
            ],
            'page_permissions' => $pagePermissions,
        ]);
    }

    /**
     * Show a single employee with full applicant details.
     */
    public function show($id)
    {
        $employee = User::with('auditLogs')->findOrFail($id);

        // Fetch applicant details by email
        $applicant = Applicant::where('email', $employee->email)->first();

        return response()->json([
            'employee' => $this->formatEmployee($employee),
            'applicant' => $applicant ? $this->formatApplicant($applicant) : null,
        ]);
    }

    /**
     * Update employee information (basic details).
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'role' => 'required|in:HRM,SCM,FIN,MAN,INV,ORD,WAR,CRM,ECO,PRO,PROJ,IT,LOG,CEO,COO',
            'position' => 'required|in:staff,supervisor,manager,special_officer,secretary',
            'is_active' => 'required|boolean',
        ]);

        $currentUser = Auth::user();
        $employee = User::findOrFail($id);

        if ($this->getRank($currentUser) <= $this->getRank($employee)) {
            return back()->with('error', 'You do not have authority to modify an employee of equal or higher rank.');
        }
        if ($this->getRank($currentUser) <= $this->getPosRank($request->position)) {
            return back()->with('error', 'Authority Denied: You cannot promote someone to a rank equal to or higher than your own.');
        }
        if (in_array($request->role, ['CEO', 'COO'], true) && strtoupper($currentUser->role) !== 'CEO') {
            return back()->with('error', 'Only the CEO can assign the CEO or COO role.');
        }

        $wasActive = (bool) $employee->is_active;

        $employee->update($request->only('name', 'email', 'role', 'position', 'is_active'));

        $employee->refresh();
        if ($wasActive && ! $employee->is_active) {
            TerminatedAccess::revoke($employee, 'deactivated via employee edit');
        } elseif (! $wasActive && $employee->is_active) {
            TerminatedAccess::restoreApplicantsFor($employee);
        }

        return redirect()->back()->with('message', 'Employee updated successfully.');
    }

    /**
     * Toggle employee account status.
     */
    public function toggleStatus(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string']);

        $currentUser = Auth::user();
        $employee = User::findOrFail($id);

        if ($currentUser->id == $employee->id) {
            return back()->with('error', 'Cannot modify own account status.');
        }

        if ($this->getRank($currentUser) <= $this->getRank($employee)) {
            return back()->with('error', 'You do not have authority to deactivate an employee of equal or higher rank.');
        }

        $newStatus = !$employee->is_active;
        $action = $newStatus ? 'reactivate' : 'deactivate';

        $employee->update(['is_active' => $newStatus]);

        if (! $newStatus) {
            // Termination: kill company-system access (remember token +
            // employment stamp) and block the personal email from the
            // applicant portal. The is_active flag alone already refuses
            // logins; this closes remember-me + applicant-portal holes.
            TerminatedAccess::revoke($employee->fresh(), $request->reason);
        } else {
            TerminatedAccess::restoreApplicantsFor($employee->fresh());
        }

        AuditLog::create([
            'admin_id' => $currentUser->id,
            'target_id' => $employee->id,
            'target_name' => $employee->name,
            'action' => $action,
            'reason' => $request->reason,
        ]);

        return back()->with('message', "Employee {$employee->name} has been {$action}d.");
    }

    /**
     * Promote a staff member to manager.
     */
    public function promoteToManager(Request $request, $id)
    {
        $currentUser = Auth::user();
        $employee = User::findOrFail($id);

        if (!$this->canPromoteToManager($currentUser)) {
            return back()->with('error', 'You are not authorized to promote employees to manager.');
        }

        if ($employee->position !== 'staff') {
            return back()->with('error', 'Only staff members can be promoted to manager.');
        }

        if (!in_array($employee->role, $this->coreModules)) {
            return back()->with('error', 'Only core module employees can be promoted to manager.');
        }

        $oldPosition = $employee->position;
        $employee->position = 'manager';
        $employee->save();

        AuditLog::create([
            'admin_id' => $currentUser->id,
            'target_id' => $employee->id,
            'target_name' => $employee->name,
            'action' => 'promote_to_manager',
            'reason' => "Promoted from {$oldPosition} to manager by " . $currentUser->name,
        ]);

        return back()->with('message', "{$employee->name} has been promoted to Manager.");
    }

    /**
     * Demote a manager to staff.
     */
    public function demoteToStaff(Request $request, $id)
    {
        $currentUser = Auth::user();
        $employee = User::findOrFail($id);

        if (!$this->canDemoteManager($currentUser)) {
            return back()->with('error', 'Only the CEO can demote managers to staff.');
        }

        if ($employee->position !== 'manager') {
            return back()->with('error', 'Only managers can be demoted to staff.');
        }

        $oldPosition = $employee->position;
        $employee->position = 'staff';
        $employee->save();

        AuditLog::create([
            'admin_id' => $currentUser->id,
            'target_id' => $employee->id,
            'target_name' => $employee->name,
            'action' => 'demote_to_staff',
            'reason' => "Demoted from {$oldPosition} to staff by " . $currentUser->name,
        ]);

        return back()->with('message', "{$employee->name} has been demoted to Staff.");
    }

    /**
     * Update employee's role and position (full control) – CEO only.
     */
    public function updateRolePosition(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|in:HRM,SCM,FIN,MAN,INV,ORD,WAR,CRM,ECO,PRO,PROJ,IT,LOG,CEO,COO',
            'position' => 'required|in:staff,supervisor,manager,special_officer,secretary',
        ]);

        $currentUser = Auth::user();
        $employee = User::findOrFail($id);

        if (strtoupper($currentUser->role) !== 'CEO') {
            return back()->with('error', 'Only the CEO can directly change role and position.');
        }

        if ($this->getRank($currentUser) <= $this->getRank($employee)) {
            return back()->with('error', 'You do not have authority to modify an employee of equal or higher rank.');
        }

        $oldRole = $employee->role;
        $oldPosition = $employee->position;

        $employee->update([
            'role' => $request->role,
            'position' => $request->position,
        ]);

        if ($request->role !== 'MAN' && $employee->manufacturing_role) {
            $employee->update(['manufacturing_role' => null]);
        }

        AuditLog::create([
            'admin_id' => $currentUser->id,
            'target_id' => $employee->id,
            'target_name' => $employee->name,
            'action' => 'role_position_change',
            'reason' => "Role changed from {$oldRole} to {$request->role}, Position from {$oldPosition} to {$request->position}",
        ]);

        return back()->with('message', 'Employee role and position updated successfully.');
    }

    private function formatEmployee($employee)
    {
        return [
            'id' => $employee->id,
            'employee_id' => $employee->employee_id,
            'name' => $employee->name,
            'email' => $employee->email,
            'role' => $employee->role,
            'position' => $employee->position,
            'is_active' => (bool)$employee->is_active,
            'join_date' => $employee->join_date,
            'audit_logs' => $employee->auditLogs,
            'profile_photo_url' => $employee->profile_photo_path ? asset('storage/' . $employee->profile_photo_path) : null,
            'is_manufacturing_supervisor' => $employee->is_manufacturing_supervisor,
            'supervisor_department' => $employee->supervisor_department,
        ];
    }

    private function formatApplicant($applicant)
    {
        return [
            'first_name' => $applicant->first_name,
            'middle_name' => $applicant->middle_name,
            'last_name' => $applicant->last_name,
            'image' => $applicant->image ? asset('storage/' . $applicant->image) : null,
            'date_of_birth' => $applicant->date_of_birth,
            'place_of_birth' => $applicant->place_of_birth,
            'citizenship' => $applicant->citizenship,
            'weight' => $applicant->weight,
            'height' => $applicant->height,
            'civil_status' => $applicant->civil_status,
            'sex' => $applicant->sex,
            'religion' => $applicant->religion,
            'contact_number' => $applicant->contact_number,
            'sss_number' => $applicant->sss_number,
            'phone_number' => $applicant->phone_number,
            'street_address' => $applicant->street_address,
            'city' => $applicant->city,
            'state_province' => $applicant->state_province,
            'postal_zip_code' => $applicant->postal_zip_code,
            'position_applied' => $applicant->position_applied,
            'notice_period' => $applicant->notice_period,
            'status' => $applicant->status,
            'sss_file' => $applicant->sss_file ? asset('storage/' . $applicant->sss_file) : null,
            'philhealth_number' => $applicant->philhealth_number,
            'philhealth_file' => $applicant->philhealth_file ? asset('storage/' . $applicant->philhealth_file) : null,
            'pagibig_number' => $applicant->pagibig_number,
            'pagibig_file' => $applicant->pagibig_file ? asset('storage/' . $applicant->pagibig_file) : null,
            'spouse_name' => $applicant->spouse_name,
            'spouse_occupation' => $applicant->spouse_occupation,
            'spouse_address' => $applicant->spouse_address,
            'number_of_children' => $applicant->number_of_children,
            'children' => $applicant->children,
            'mother_name' => $applicant->mother_name,
            'mother_address' => $applicant->mother_address,
            'father_name' => $applicant->father_name,
            'father_address' => $applicant->father_address,
            'languages' => $applicant->languages,
            'emergency_name' => $applicant->emergency_name,
            'emergency_relationship' => $applicant->emergency_relationship,
            'emergency_phone' => $applicant->emergency_phone,
            'emergency_address' => $applicant->emergency_address,
            'special_skills' => $applicant->special_skills,
            'elementary_school' => $applicant->elementary_school,
            'elementary_year' => $applicant->elementary_year,
            'high_school' => $applicant->high_school,
            'high_year' => $applicant->high_year,
            'college' => $applicant->college,
            'college_year' => $applicant->college_year,
            'vocational' => $applicant->vocational,
            'vocational_year' => $applicant->vocational_year,
            'previous_employment_company' => $applicant->previous_employment_company,
            'previous_employment_when' => $applicant->previous_employment_when,
            'previous_employment_position' => $applicant->previous_employment_position,
            'previous_employment_department' => $applicant->previous_employment_department,
            'has_employment_record' => $applicant->has_employment_record,
            'employment_records' => $applicant->employment_records,
            'machine_operation' => $applicant->machine_operation,
            'referred_by' => $applicant->referred_by,
            'referred_by_address' => $applicant->referred_by_address,
            'related_employees' => $applicant->related_employees,
            'assigned_module' => $applicant->assigned_module,
            'rejection_reason' => $applicant->rejection_reason,
            'interview_feedback' => $applicant->interview_feedback,
        ];
    }
}