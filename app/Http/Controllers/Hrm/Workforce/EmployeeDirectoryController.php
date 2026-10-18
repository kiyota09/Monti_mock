<?php

namespace App\Http\Controllers\Hrm\Workforce;

use App\Http\Controllers\Controller;
use App\Mail\EmployeeWelcomeMail;
use App\Models\Core\User;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\AttendanceLog;
use App\Models\Hrm\HrmCertification;
use App\Models\Hrm\HrmDepartment;
use App\Models\Hrm\HrmEmploymentType;
use App\Models\Hrm\HrmLeaveBalance;
use App\Models\Hrm\HrmLeaveType;
use App\Models\Hrm\HrmPosition;
use App\Models\Hrm\HrmTrainingEnrollment;
use App\Models\Hrm\Payroll;
use App\Models\Hrm\SalaryHistory;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Employee Master Records — whole-company directory (all roles).
 * Every user row becomes a full master record; org/contact chapters are
 * editable here, role/position elevation stays on Hrm\EmployeeController.
 */
class EmployeeDirectoryController extends Controller
{
    use HasPagePermissions;

    public function index(Request $request)
    {
        $q = User::with(['hrmDepartment', 'hrmOrgPosition.reportsTo', 'hrmEmploymentType', 'hrmRole', 'manager'])
            ->whereNull('worker_id');

        if ($s = $request->get('search')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
                ->orWhere('employee_id', 'like', "%{$s}%"));
        }
        if ($d = $request->get('department')) {
            $q->where(fn ($w) => $w->where('hrm_department_id', $d)->orWhere('role', $d)->orWhere('department', $d));
        }
        if ($r = $request->get('role')) {
            $q->where('role', $r);
        }
        if ($request->get('status') === 'inactive') {
            $q->where('is_active', false);
        } elseif ($request->get('status') === 'active') {
            $q->where('is_active', true);
        }

        $paginated = $q->orderBy('name')->paginate(20)->withQueryString();
        $employees = collect($paginated->items())->filter(fn ($u) => $u !== null)
            ->map(fn ($u) => $this->record($u))->values()->all();

        $deptCounts = User::whereNull('worker_id')->selectRaw('role, count(*) as c')->groupBy('role')->pluck('c', 'role');

        return Inertia::render('Dashboard/HRM_NEW/Employee', [
            'employees' => $employees,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'stats' => [
                'total' => User::whereNull('worker_id')->count(),
                'active' => User::whereNull('worker_id')->where('is_active', true)->count(),
                'inactive' => User::whereNull('worker_id')->where('is_active', false)->count(),
                'byDepartment' => $deptCounts,
            ],
            'filters' => $request->only(['search', 'department', 'role', 'status']),
            'departments' => HrmDepartment::whereNull('archived_at')->select('id', 'code', 'name')->get(),
            'positions' => HrmPosition::whereNull('archived_at')->select('id', 'name', 'department_id')->get(),
            'employmentTypes' => HrmEmploymentType::where('is_active', true)->select('id', 'name')->get(),
            'roles' => HrmDepartment::whereNull('archived_at')->pluck('category')->filter()->unique()->values()->all(),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    /**
     * Single-employee profile page. Renders the same master-record
     * component in profile-only mode (no directory table).
     */
    public function show(User $employee)
    {
        $employee->load(['hrmDepartment', 'hrmOrgPosition.reportsTo', 'hrmEmploymentType', 'hrmRole', 'manager']);

        return Inertia::render('Dashboard/HRM_NEW/Employee', [
            'employees' => [$this->record($employee)],
            'profileOnly' => true,
            'focusId' => $employee->id,
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 1,
                'total' => 1,
            ],
            'stats' => [
                'total' => User::whereNull('worker_id')->count(),
                'active' => User::whereNull('worker_id')->where('is_active', true)->count(),
                'inactive' => User::whereNull('worker_id')->where('is_active', false)->count(),
                'byDepartment' => [],
            ],
            'filters' => [],
            'departments' => HrmDepartment::whereNull('archived_at')->select('id', 'code', 'name')->get(),
            'positions' => HrmPosition::whereNull('archived_at')->select('id', 'name', 'department_id')->get(),
            'employmentTypes' => HrmEmploymentType::where('is_active', true)->select('id', 'name')->get(),
            'roles' => HrmDepartment::whereNull('archived_at')->pluck('category')->filter()->unique()->values()->all(),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function update(Request $request, User $employee)
    {
        $data = $request->validate([
            'first_name' => 'nullable|string|max:255', 'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255', 'suffix' => 'nullable|string|max:30',
            'nickname' => 'nullable|string|max:60', 'preferred_name' => 'nullable|string|max:255',
            'gender' => 'nullable|string|max:20', 'date_of_birth' => 'nullable|date',
            'civil_status' => 'nullable|string|max:20', 'nationality' => 'nullable|string|max:60',
            'personal_email' => 'nullable|email|max:255', 'mobile_number' => 'nullable|string|max:40',
            'telephone' => 'nullable|string|max:40',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_number' => 'nullable|string|max:40',
            'current_address' => 'nullable|string', 'permanent_address' => 'nullable|string',
            'country' => 'nullable|string|max:60', 'province' => 'nullable|string|max:60',
            'city' => 'nullable|string|max:60', 'postal_code' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255', 'branch' => 'nullable|string|max:255',
            'business_unit' => 'nullable|string|max:255', 'department' => 'nullable|string|max:255',
            'cost_center' => 'nullable|string|max:60', 'work_location' => 'nullable|string|max:255',
            'department_head' => 'nullable|string|max:255', 'immediate_supervisor' => 'nullable|string|max:255',
            'payroll_group' => 'nullable|string|max:60', 'salary_grade' => 'nullable|string|max:30',
            'basic_salary' => 'nullable|numeric|min:0', 'bank_account' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:60', 'sss_id' => 'nullable|string|max:60',
            'work_schedule' => 'nullable|string|max:255', 'shift' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:20', 'biometrics_id' => 'nullable|string|max:60',
            'join_date' => 'nullable|date', 'probation_end_date' => 'nullable|date',
            'regularization_date' => 'nullable|date', 'resignation_date' => 'nullable|date',
            'last_working_day' => 'nullable|date',
            'hrm_department_id' => 'nullable|exists:hrm_departments,id',
            'hrm_position_id' => 'nullable|exists:hrm_positions,id',
            'hrm_employment_type_id' => 'nullable|exists:hrm_employment_types,id',
            'manager_id' => 'nullable|exists:users,id',
            'education' => 'nullable|string', 'certifications' => 'nullable|string',
            'skills' => 'nullable|string', 'languages' => 'nullable|string|max:255',
            'trainings' => 'nullable|string',
        ]);

        // Guard against self-reporting.
        if (! empty($data['manager_id']) && (int) $data['manager_id'] === (int) $employee->id) {
            unset($data['manager_id']);
        }
        $previousSalary = $employee->basic_salary;
        $employee->update($data);
        // Direct salary edits outside the adjustment workflow still leave an
        // audit row so the ledger stays complete.
        if (array_key_exists('basic_salary', $data)
            && (string) ($employee->basic_salary ?? '') !== (string) ($previousSalary ?? '')) {
            SalaryHistory::create([
                'user_id' => $employee->id,
                'previous_salary' => $previousSalary,
                'new_salary' => $employee->basic_salary,
                'effective_date' => now()->toDateString(),
                'reason' => 'Updated via employee record',
                'source' => 'directory',
                'recorded_by' => $request->user()?->id,
            ]);
        }
        // Keep the legacy name strings in sync when the FK changes.
        if (array_key_exists('manager_id', $data) && $data['manager_id']) {
            $managerName = User::where('id', $data['manager_id'])->value('name');
            if ($managerName) {
                $employee->update(['immediate_supervisor' => $managerName]);
            }
        }
        // Keep the display name in sync with name parts.
        if (array_intersect_key($data, array_flip(['first_name', 'middle_name', 'last_name']))) {
            $employee->update(['name' => trim(($employee->first_name ?? '') . ' ' . ($employee->middle_name ? $employee->middle_name . ' ' : '') . ($employee->last_name ?? '')) ?: $employee->name]);
        }

        return back()->with('success', 'Master record updated.');
    }

    /**
     * Regenerate a temporary password and re-send the welcome credentials.
     * Recovery path when the original welcome mail was lost — the new
     * password is flashed once (same show-once banner as creation).
     */
    public function resendCredentials(Request $request, User $employee)
    {
        // Never issue fresh credentials to a deactivated/terminated account —
        // the company email must stay locked out until HR/IT reactivates it.
        if (! $employee->is_active) {
            return back()->withErrors(['email' => 'This account is deactivated. Reactivate it before issuing credentials.']);
        }
        $tempPassword = 'Monti-' . Str::upper(Str::random(6));
        $employee->update(['password' => Hash::make($tempPassword), 'must_change_password' => true]);

        $employee->loadMissing(['hrmDepartment:id,name', 'hrmOrgPosition:id,name']);
        $personalEmail = $employee->personal_email;
        $mailSent = false;
        $mailError = null;
        if ($personalEmail && $personalEmail !== $employee->email) {
            try {
                Mail::to($personalEmail)->send(new EmployeeWelcomeMail(
                    $employee->name,
                    $employee->employee_id,
                    $employee->hrmOrgPosition?->name ?? $employee->position,
                    $employee->hrmDepartment?->name ?? $employee->department,
                    $employee->email,
                    $tempPassword,
                    $employee->join_date ? \Carbon\Carbon::parse($employee->join_date)->format('F j, Y') : 'To be announced',
                    route('login')
                ));
                $mailSent = true;
            } catch (\Throwable $e) {
                $mailError = $e->getMessage();
                Log::warning('Credential resend failed for user #' . $employee->id . ': ' . $e->getMessage());
            }
        } else {
            $mailError = 'No personal email on record — hand the credentials over manually.';
        }

        return back()->with('hire_credentials', [
            'email' => $employee->email,
            'employee_id' => $employee->employee_id,
            'password' => $tempPassword,
            'is_new_account' => false,
            'mailed' => $mailSent,
            'mailed_to' => $mailSent ? $personalEmail : null,
            'mail_error' => $mailError,
        ]);
    }

    public function export()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['employee_id', 'name', 'email', 'role', 'position', 'department', 'status', 'join_date']);
            User::whereNull('worker_id')->orderBy('name')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $u) {
                    fputcsv($out, [
                        $u->employee_id ?: ('EMP-' . $u->id), $u->name, $u->email,
                        $u->role, $u->position,
                        $u->hrmDepartment?->name ?? $u->department ?? $u->role,
                        $u->is_active ? 'Active' : 'Inactive', $u->join_date,
                    ]);
                }
            });
            fclose($out);
        }, 'employee-master.csv');
    }

    private function record(User $u): array
    {
        $deptName = $u->hrmDepartment?->name ?? $u->department ?? $u->role;
        $posName = $u->hrmOrgPosition?->name ?? ucfirst((string) $u->position);
        $typeName = $u->hrmEmploymentType?->name ?? 'Regular';
        $year = (int) now()->year;

        $balances = HrmLeaveBalance::with('leaveType')->where('user_id', $u->id)->where('year', $year)->get();
        if ($balances->isEmpty()) {
            $balances = HrmLeaveType::where('is_active', true)->get()->map(fn ($t) => (object) [
                'leaveType' => $t, 'entitled' => $t->default_days, 'used' => 0,
            ]);
        }
        $bal = fn ($code) => collect($balances)->first(
            fn ($b) => strtolower($b->leaveType?->code ?? '') === $code
        );
        $leaveBalancesDetailed = collect($balances)->map(fn ($b) => [
            'code' => $b->leaveType?->code ?? '',
            'name' => $b->leaveType?->name ?? ucfirst((string) ($b->leaveType?->code ?? 'Leave')),
            'entitled' => (int) ($b->entitled ?? 0),
            'used' => (int) ($b->used ?? 0),
            'remaining' => max(0, (int) ($b->entitled ?? 0) - (int) ($b->used ?? 0)),
        ])->values()->all();

        $monthStart = now()->startOfMonth()->toDateString();
        $att = AttendanceLog::where('user_id', $u->id)->where('date', '>=', $monthStart)->get();
        $attendanceRecent = AttendanceLog::where('user_id', $u->id)->orderByDesc('date')->take(15)->get()
            ->map(fn ($a) => [
                'date' => $a->date, 'clock_in' => $a->clock_in, 'clock_out' => $a->clock_out,
                'status' => $a->status,
            ])->values()->all();
        $payroll = Payroll::where('employee_id', $u->id)->latest()->first();
        $payrollHistory = Payroll::where('employee_id', $u->id)->orderByDesc('id')->take(10)->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'period' => optional($p->created_at)->format('M Y') ?? ('#' . $p->id),
                'date' => optional($p->created_at)->format('Y-m-d'),
                'base_salary' => $p->base_salary, 'days_worked' => $p->days_worked,
                'gross_pay' => $p->gross_pay, 'net_pay' => $p->net_pay, 'status' => $p->status,
            ])->values()->all();
        $leaveRequests = \App\Models\Hrm\LeaveRequest::where('user_id', $u->id)->orderByDesc('id')->take(20)->get()
            ->map(fn ($l) => [
                'id' => $l->id, 'type' => $l->leave_type,
                'start' => optional($l->start_date)->format('Y-m-d'), 'end' => optional($l->end_date)->format('Y-m-d'),
                'reason' => $l->reason, 'status' => $l->status,
            ])->values()->all();
        $shifts = \App\Models\Hrm\EmployeeShift::where('user_id', $u->id)->orderByDesc('effective_date')->take(10)->get()
            ->map(fn ($s) => [
                'id' => $s->id, 'dept_code' => $s->dept_code, 'shift_type' => $s->shift_type,
                'effective_date' => $s->effective_date, 'schedule' => $s->schedule_range, 'status' => $s->status,
            ])->values()->all();
        $applicant = Applicant::where('email', $u->email)->with('documents')->first();
        $certModels = HrmCertification::where('user_id', $u->id)->orderByDesc('id')->get();
        $certs = $certModels->pluck('name')->all();
        $enrollModels = HrmTrainingEnrollment::with('training')->where('user_id', $u->id)->orderByDesc('id')->get();
        $enrolled = $enrollModels->map(fn ($e) => $e->training?->name)->filter()->values()->all();
        $certificationsDetailed = $certModels->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'training_id' => $c->training_id,
            'issued' => optional($c->issued_at)->format('Y-m-d'), 'expiry' => optional($c->expiry_date)->format('Y-m-d'),
        ])->values()->all();
        $trainingHistory = $enrollModels->map(fn ($e) => [
            'id' => $e->id, 'training' => $e->training?->name ?? ('Training #' . $e->training_id),
            'status' => $e->status, 'progress' => $e->progress,
            'completed' => optional($e->completed_at)->format('Y-m-d'),
        ])->values()->all();
        $onboardingRecord = \App\Models\Hrm\HrmOnboarding::where('email', $u->email)
            ->orWhere('applicant_id', $applicant?->id)->orderByDesc('id')->first();
        $grade = \App\Models\Hrm\TraineeGrade::where('user_id', $u->id)->first();
        $trainDone = $enrollModels->where('status', 'completed')->count();
        $trainTotal = max(1, $enrollModels->count());
        $attTotal = max(1, $att->count());
        $attPresent = $att->whereIn('status', ['On-Time', 'Present'])->count();
        $performanceSummary = [
            'has_grade' => (bool) $grade,
            'skills_performance' => $grade?->skills_performance,
            'behaviour' => $grade?->behaviour,
            'technicals' => $grade?->technicals,
            'safety_awareness' => $grade?->safety_awareness,
            'productivity' => $grade?->productivity,
            'total_percentage' => $grade?->total_percentage,
            'passed_to_hr' => (bool) ($grade?->passed_to_hr ?? false),
            'trainings_completed' => $trainDone,
            'trainings_total' => $enrollModels->count(),
            'training_completion_rate' => (int) round($trainDone / $trainTotal * 100),
            'attendance_rate' => (int) round($attPresent / $attTotal * 100),
            'attendance_present' => $attPresent,
            'attendance_total' => $att->count(),
            'certifications_count' => $certModels->count(),
        ];

        return [
            'id' => $u->id,
            'employeeId' => $u->employee_id ?: ('EMP-' . str_pad((string) $u->id, 4, '0', STR_PAD_LEFT)),
            'firstName' => $u->first_name ?? strtok((string) $u->name, ' '),
            'middleName' => $u->middle_name ?? '',
            'lastName' => $u->last_name ?? '',
            'suffix' => $u->suffix ?? '',
            'nickname' => $u->nickname ?? '',
            'preferredName' => $u->preferred_name ?? $u->name,
            'gender' => $u->gender ?? '',
            'dateOfBirth' => $u->date_of_birth,
            'civilStatus' => $u->civil_status ?? '',
            'nationality' => $u->nationality ?? 'Filipino',
            'profilePicture' => $u->profile_photo_path ? Storage::url($u->profile_photo_path) : '',
            'personalEmail' => $u->personal_email ?? '',
            'companyEmail' => $u->email,
            'mobileNumber' => $u->mobile_number ?? '',
            'telephone' => $u->telephone ?? '',
            'emergencyContact' => $u->emergency_contact_name ?? '',
            'emergencyContactNumber' => $u->emergency_contact_number ?? '',
            'currentAddress' => $u->current_address ?? '',
            'permanentAddress' => $u->permanent_address ?? '',
            'country' => $u->country ?? 'Philippines',
            'provinceState' => $u->province ?? '',
            'city' => $u->city ?? '',
            'postalCode' => $u->postal_code ?? '',
            'company' => $u->company ?? 'MontiTextile',
            'branch' => $u->branch ?? '',
            'businessUnit' => $u->business_unit ?? '',
            'department' => $deptName,
            'departmentId' => $u->hrm_department_id,
            'position' => $posName,
            'orgPositionId' => $u->hrm_position_id,
            'employmentType' => $typeName,
            'employmentTypeId' => $u->hrm_employment_type_id,
            'employeeStatus' => $u->is_active ? 'Active' : 'Inactive',
            'role' => $u->role,
            'positionRank' => $u->position,
            'dateHired' => $u->join_date,
            'probationEndDate' => $u->probation_end_date,
            'regularizationDate' => $u->regularization_date,
            'resignationDate' => $u->resignation_date,
            'lastWorkingDay' => $u->last_working_day,
            'reportsTo' => $u->manager?->name ?? $u->hrmOrgPosition?->reportsTo?->name ?? $u->immediate_supervisor ?? $u->department_head ?? '',
            'managerId' => $u->manager_id,
            'managerName' => $u->manager?->name ?? '',
            'immediateSupervisor' => $u->immediate_supervisor ?? '',
            'departmentHead' => $u->department_head ?? '',
            'costCenter' => $u->cost_center ?? '',
            'workLocation' => $u->work_location ?? '',
            'payrollGroup' => $u->payroll_group ?? '',
            'salaryGrade' => $u->salary_grade ?? '',
            'basicSalary' => $u->basic_salary !== null ? '₱' . number_format((float) $u->basic_salary, 2) : ($payroll?->base_salary ? '₱' . number_format((float) $payroll->base_salary, 2) : ''),
            'basicSalaryRaw' => $u->basic_salary,
            'bankAccount' => $u->bank_account ?? '',
            'taxID' => $u->tax_id ?? '',
            'ssn' => $u->sss_id ?? '',
            'workSchedule' => $u->work_schedule ?? '',
            'shift' => $u->shift ?? '',
            'timeZone' => $u->timezone ?? 'GMT+8',
            'biometricsID' => $u->biometrics_id ?? '',
            'vacationLeaveBalance' => (string) max(0, (int) ($bal('vacation')?->entitled ?? 12) - (int) ($bal('vacation')?->used ?? 0)),
            'sickLeaveBalance' => (string) max(0, (int) ($bal('sick')?->entitled ?? 15) - (int) ($bal('sick')?->used ?? 0)),
            'otherLeaveBalance' => '5',
            'attendanceMonth' => [
                'present' => $att->whereIn('status', ['On-Time', 'Present'])->count(),
                'late' => $att->where('status', 'Late')->count(),
                'absent' => $att->where('status', 'Absent')->count(),
            ],
            'education' => $u->education ?? '',
            'certifications' => $u->certifications ?? implode(', ', $certs),
            'skills' => $u->skills ?? '',
            'languages' => $u->languages ?? '',
            'trainings' => $u->trainings ?? implode(', ', $enrolled),
            'documents' => ($applicant?->documents ?? collect())->map(fn ($d) => [
                'name' => $d->original_name ?: basename($d->file_path),
                'type' => $d->type, 'size' => '', 'url' => Storage::url($d->file_path),
                'verified' => (bool) ($d->verified ?? false),
                'uploaded_by' => $d->uploaded_by,
                'uploaded_at' => optional($d->created_at)->format('Y-m-d'),
            ])->values()->all(),
            'auditTrail' => $u->auditLogs()->with('admin')->latest()->take(20)->get()->map(fn ($a) => [
                'date' => optional($a->created_at)->format('F d, Y'),
                'raw_date' => optional($a->created_at)->format('Y-m-d H:i'),
                'title' => ucfirst((string) $a->action) . ' — ' . ($a->target_name ?? $u->name),
                'desc' => $a->reason ?? '',
                'action' => $a->action,
                'admin' => $a->admin?->name ?? ('#' . $a->admin_id),
                'target' => $a->target_name ?? $u->name,
            ])->values()->all(),
            'leaveBalancesDetailed' => $leaveBalancesDetailed,
            'leaveRequests' => $leaveRequests,
            'attendanceRecent' => $attendanceRecent,
            'payrollHistory' => $payrollHistory,
            'shifts' => $shifts,
            'certificationsDetailed' => $certificationsDetailed,
            'trainingHistory' => $trainingHistory,
            'performanceSummary' => $performanceSummary,
            'applicantProfile' => $applicant ? [
                'id' => $applicant->id,
                'position_applied' => $applicant->position_applied,
                'status' => $applicant->status,
                'expected_salary' => $applicant->expected_salary,
                'city' => $applicant->city,
                'contact' => $applicant->phone_number ?? $applicant->contact_number,
            ] : null,
            'onboardingRecord' => $onboardingRecord ? [
                'id' => $onboardingRecord->id,
                'status' => $onboardingRecord->status,
                'position' => $onboardingRecord->position,
                'department' => $onboardingRecord->department,
                'progress' => $onboardingRecord->progress,
                'start_date' => optional($onboardingRecord->start_date)->format('Y-m-d'),
            ] : null,
        ];
    }
}
