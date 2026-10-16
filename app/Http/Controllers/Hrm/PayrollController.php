<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\AttendanceLog;
use App\Models\Hrm\EmployeeShift;
use App\Models\Hrm\Holiday;
use App\Models\Hrm\LeaveRequest;
use App\Models\Hrm\Payroll;
use App\Models\Hrm\PayrollSet;
use App\Models\Hrm\PayrollRate;
use App\Models\Core\User;
use App\Support\NotifiesExecutive;
use App\Support\PayrollCalc;
use App\Models\Ceo\ExecutiveActionLog;
use App\Models\Hrm\GovernmentContributionRate;
use App\Traits\HasPagePermissions;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PayrollController extends Controller
{
    use HasPagePermissions;

    /**
     * Display the payroll console.
     * New workflow: Draft -> Submitted -> Approved -> Paid (Paid is final).
     * Totals are for ALL employees in the period, ignoring filters.
     */
    public function index(Request $request)
    {
        $hasPeriod = \Illuminate\Support\Facades\Schema::hasColumn('payrolls', 'period');

        // Real-ERP calendar: weekly / semi-monthly / monthly.
        $frequency = \App\Support\PayPeriods::normalizeFrequency($request->query('frequency', 'semi_monthly'));
        $calYear = (int) ($request->query('year', now()->year));
        $calendar = \App\Support\PayPeriods::year($calYear, $frequency);
        $currentLabel = \App\Support\PayPeriods::currentLabel($frequency);

        // Row counts per period for lock/current badges.
        $countsByPeriod = [];
        $allPaidByPeriod = [];
        if ($hasPeriod) {
            $countsByPeriod = Payroll::selectRaw('period, COUNT(*) as c')
                ->whereNotNull('period')->groupBy('period')->pluck('c', 'period')->all();
            foreach ($countsByPeriod as $pl => $c) {
                $paid = Payroll::where('period', $pl)->where('status', 'Paid')->count();
                $allPaidByPeriod[$pl] = $c > 0 && $paid === (int) $c;
            }
        }

        $dbLabels = array_keys($countsByPeriod);
        $requested = $request->query('period');
        if ($requested && ($hasPeriod ? true : true)) {
            $period = $requested;
        } elseif (in_array($currentLabel, array_column($calendar, 'label'))) {
            $period = $currentLabel;
        } elseif (!empty($dbLabels)) {
            $period = end($dbLabels);
        } else {
            $period = $calendar[0]['label'] ?? null;
        }

        $periods = array_map(function ($p) use ($currentLabel, $countsByPeriod, $allPaidByPeriod) {
            return array_merge($p, [
                'is_current' => $p['label'] === $currentLabel,
                'row_count' => $countsByPeriod[$p['label']] ?? 0,
                'locked' => $allPaidByPeriod[$p['label']] ?? false,
            ]);
        }, $calendar);
        // Include legacy DB labels not in this calendar/frequency.
        foreach ($dbLabels as $pl) {
            if (!collect($periods)->contains(fn ($p) => $p['label'] === $pl)) {
                $meta = \App\Support\PayPeriods::find($pl, $frequency);
                $periods[] = array_merge($meta ?? ['label' => $pl, 'start' => null, 'end' => null, 'pay_date' => null], [
                    'is_current' => $pl === $currentLabel,
                    'row_count' => $countsByPeriod[$pl] ?? 0,
                    'locked' => $allPaidByPeriod[$pl] ?? false,
                ]);
            }
        }

        $activeMeta = collect($periods)->firstWhere('label', $period);

        $query = Payroll::latest();
        if ($period && $hasPeriod) {
            $query->where('period', $period);
        }
        $rows = $query->get();

        // Fallback for rows without department: users.department string,
        // else hrm_departments via users.hrm_department_id (real HRM departments).
        $userByEmpId = User::with('hrmDepartment:id,name')
            ->whereIn('employee_id', $rows->pluck('employee_id')->filter()->unique()->values()->all())
            ->get(['id', 'employee_id', 'department', 'position', 'hrm_department_id'])->keyBy('employee_id');

        $payrollData = $rows->map(function ($payroll) use ($userByEmpId) {
            $deductions = (float) $payroll->late_total_deduction + (float) $payroll->sss_deduction
                + (float) $payroll->philhealth_deduction + (float) $payroll->pagibig_deduction
                + (float) $payroll->tax_withheld + (float) $payroll->sss_loan + (float) $payroll->pf_loan;
            $u = $userByEmpId->get($payroll->employee_id);
            $fallbackDept = $payroll->department
                ?: ($u?->department ?: ($u?->hrmDepartment?->name ?: 'Unassigned'));
            return [
                'id' => $payroll->id,
                'employee_id' => $payroll->employee_id,
                'employee_name' => $payroll->employee_name,
                'job_title' => $payroll->job_title ?? $payroll->role,
                'department' => $fallbackDept,
                'role' => $payroll->role,
                'period' => $payroll->period,
                'base_salary' => $payroll->base_salary,
                'monthly_salary' => $payroll->monthly_salary ?? $payroll->base_salary,
                'allowance' => $payroll->allowance ?? 0,
                'absent_days' => $payroll->absent_days ?? 0,
                'holiday_days' => $payroll->holiday_days ?? 0,
                'days_worked' => $payroll->days_worked,
                'daily_rate' => $payroll->daily_rate,
                'total_days_amt' => $payroll->total_days_amt,
                'overtime_hours' => $payroll->overtime_hours,
                'ot_rate' => $payroll->ot_rate,
                'ot_amt' => $payroll->ot_amt,
                'night_hours' => $payroll->night_hours,
                'night_rate' => $payroll->night_rate,
                'night_amt' => $payroll->night_amt,
                'sunday_restday_hours' => $payroll->sunday_restday_hours,
                'sun_sp_rate' => $payroll->sun_sp_rate,
                'sun_sp_amt' => $payroll->sun_sp_amt,
                'holiday_amt' => $payroll->holiday_amt,
                'late_minutes' => $payroll->late_minutes,
                'late_rate_min' => $payroll->late_rate_min,
                'late_total_deduction' => $payroll->late_total_deduction,
                'sss_deduction' => $payroll->sss_deduction,
                'philhealth_deduction' => $payroll->philhealth_deduction,
                'pagibig_deduction' => $payroll->pagibig_deduction,
                'tax_withheld' => $payroll->tax_withheld,
                'sss_loan' => $payroll->sss_loan,
                'pf_loan' => $payroll->pf_loan,
                'deductions' => $deductions,
                'gross_pay' => $payroll->gross_pay,
                'net_pay' => $payroll->net_pay,
                'employer_sss' => $payroll->employer_sss ?? 0,
                'employer_philhealth' => $payroll->employer_philhealth ?? 0,
                'employer_pagibig' => $payroll->employer_pagibig ?? 0,
                'accrual_13th' => $payroll->accrual_13th ?? 0,
                'status' => $this->normalizeStatus($payroll->status),
                'paid_at' => $payroll->paid_at,
                'payment_reference' => $payroll->payment_reference,
                'payment_method' => $payroll->payment_method,
                'created_at' => $payroll->created_at,
            ];
        });

        $norm = fn ($s) => $this->normalizeStatus($s);
        $counts = ['Draft' => 0, 'Submitted' => 0, 'Approved' => 0, 'Paid' => 0];
        $gross = $dedTotal = $net = 0;
        $paidCount = 0;
        foreach ($rows as $r) {
            $s = $norm($r->status);
            if (isset($counts[$s])) $counts[$s]++;
            $gross += (float) $r->gross_pay;
            $dedTotal += (float) $r->late_total_deduction + (float) $r->sss_deduction + (float) $r->philhealth_deduction + (float) $r->pagibig_deduction + (float) $r->tax_withheld + (float) $r->sss_loan + (float) $r->pf_loan;
            $net += (float) $r->net_pay;
            if ($s === 'Paid') $paidCount++;
        }

        $eligible = $rows->filter(fn ($r) => in_array($norm($r->status), ['Approved', 'Paid']));
        $rem = PayrollCalc::remittances($eligible);
        // Year-to-date 13th-month accrual (same calendar year, Approved/Paid).
        $activeYear = $activeMeta['year'] ?? (int) now()->year;
        $rem['ytd_accrual_13th'] = (float) Payroll::where('period', 'like', '%'.$activeYear.'%')
            ->whereIn('status', ['Approved', 'Paid'])->sum('accrual_13th');
        $rem['year'] = $activeYear;

        $audit = ExecutiveActionLog::with('actor:id,name')
            ->where('action_type', 'payroll')
            ->latest()->take(100)->get()->map(fn ($a) => [
                'time' => $a->created_at?->toDateTimeString(),
                'role' => $a->actor?->name ?? 'System',
                'message' => $a->subject_label,
                'decision' => $a->decision,
            ]);

        $permissions = $this->getPagePermissionsForModule('HRM');

        $employees = User::where('is_active', true)
            ->whereIn('position', ['staff', 'manager', 'trainee'])
            ->select('id', 'name', 'position as role', 'department')
            ->get();

        // Department options for the filter: payrolls + users so old rows still filter.
        $deptOptions = $payrollData->pluck('department')->filter()
            ->merge($employees->pluck('department')->filter())
            ->unique()->values()->all();

        return Inertia::render('Dashboard/HRM_NEW/Payroll', [
            'payroll_data' => $payrollData,
            'periods' => array_column($periods, 'label'),
            'period_meta' => $periods,
            'active_period' => $period,
            'active_period_meta' => $activeMeta,
            'calendar_year' => $calYear,
            'frequency' => $frequency,
            'departments' => $deptOptions,
            'stats' => [
                'total_payout' => $net,
                'gross_pay' => $gross,
                'deductions' => $dedTotal,
                'net_payroll' => $net,
                'paid_count' => $paidCount,
                'total_count' => $rows->count(),
                // Back-compat keys:
                'total_payout_approved' => $net,
                'pending_approvals' => ($counts['Submitted'] ?? 0),
            ],
            'stepper' => $counts,
            'remittances' => $rem,
            'audit_log' => $audit,
            'permissions' => $permissions,
            'employees' => $employees,
        ]);
    }

    private function normalizeStatus(?string $s): string
    {
        return match (strtolower((string) $s)) {
            'draft' => 'Draft',
            'submitted', 'pending' => 'Submitted',
            'approved' => 'Approved',
            'paid' => 'Paid',
            'rejected' => 'Draft',
            default => 'Draft',
        };
    }

    private function auditPayroll(string $message, $subjectId, string $decision, ?string $reason = null): void
    {
        // executive_action_logs.subject_label is VARCHAR(255) — keep it short.
        ExecutiveActionLog::create([
            'actor_id' => auth()->id(),
            'action_type' => 'payroll',
            'subject_label' => mb_substr($message, 0, 250),
            'subject_id' => is_numeric($subjectId) ? (int) $subjectId : null,
            'decision' => mb_substr($decision, 0, 50),
            'reason' => $reason ? mb_substr($reason, 0, 1000) : null,
        ]);
    }

    private function auditNames($rows, int $max = 3): string
    {
        $names = $rows instanceof \Illuminate\Support\Collection ? $rows->pluck('employee_name')->all() : (array) $rows;
        $names = array_values(array_filter($names));
        $head = implode(', ', array_slice($names, 0, $max));
        if (count($names) > $max) $head .= ' (+'.(count($names) - $max).' more)';
        return count($names).' record(s): '.$head;
    }

    private function isHR(): bool
    {
        $u = auth()->user();
        return $u && strtoupper((string) $u->role) === 'HRM';
    }

    private function isManager(): bool
    {
        $u = auth()->user();
        if (!$u) return false;
        if (in_array($u->position, ['manager', 'secretary', 'special_officer'])) return true;
        return in_array(strtoupper((string) $u->role), ['CEO', 'COO']);
    }

    private function isFinance(): bool
    {
        $u = auth()->user();
        return $u && strtoupper((string) $u->role) === 'FIN';
    }

    /**
     * Show the payroll rates page.
     */
    public function rates()
    {
        $rates = PayrollRate::first();
        if (!$rates) {
            $rates = PayrollRate::create([]);
        }

        return Inertia::render('Dashboard/HRM_NEW/PayrollRates', [
            'rates' => $rates
        ]);
    }

    /**
     * Update payroll rates.
     */
    public function updateRates(Request $request)
    {
        $validated = $request->validate([
            'daily_rate' => 'required|numeric|min:0',
            'daily_rate_usd' => 'nullable|numeric|min:0',
            'overtime_rate' => 'required|numeric|min:0|max:300',
            'night_diff_rate' => 'required|numeric|min:0|max:200',
            'holiday_rate' => 'required|numeric|min:0|max:300',
            'special_holiday_rate' => 'required|numeric|min:0|max:300',
            'rest_day_rate' => 'required|numeric|min:0|max:300',
            'late_deduction_per_minute' => 'required|numeric|min:0',
            'sss_rate' => 'nullable|numeric|min:0|max:100',
            'philhealth_rate' => 'nullable|numeric|min:0|max:100',
            'pagibig_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $rates = PayrollRate::first();
        if (!$rates) {
            $rates = new PayrollRate();
        }

        $rates->fill($validated);
        $rates->save();

        return back()->with('success', 'Payroll rates updated successfully.');
    }

    /**
     * JSON list of active employees (used by the generate-payroll form
     * when the prop list is empty).
     */
    public function employeesJson()
    {
        return response()->json(
            User::where('is_active', true)
                ->whereIn('position', ['staff', 'manager', 'trainee'])
                ->select('id', 'name', 'position', 'role', 'department', 'basic_salary')
                ->orderBy('name')
                ->get()
        );
    }

    /**
     * Show the form for generating payroll (real-ERP calendar).
     */
    public function create(Request $request)
    {
        $employees = User::where('is_active', true)
            ->whereIn('position', ['staff', 'manager', 'trainee'])
            ->select('id', 'name', 'position', 'role', 'department', 'basic_salary')
            ->get();

        $frequency = \App\Support\PayPeriods::normalizeFrequency($request->query('frequency', 'semi_monthly'));
        $calYear = (int) ($request->query('year', now()->year));
        $calendar = \App\Support\PayPeriods::year($calYear, $frequency);
        $currentLabel = \App\Support\PayPeriods::currentLabel($frequency);

        return Inertia::render('Dashboard/HRM_NEW/GeneratePayroll', [
            'employees' => $employees,
            'period_meta' => $calendar,
            'current_period' => $currentLabel,
            'calendar_year' => $calYear,
            'frequency' => $frequency,
        ]);
    }

    /**
     * Generate payroll for selected employees within a cutoff period.
     * Real-ERP rules: one row per employee per period, cutoff must fall
     * inside the period, locked (all Paid) periods refuse new drafts.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'cutoff_start' => 'required|date',
            'cutoff_end'   => 'required|date|after_or_equal:cutoff_start',
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:users,id',
            'period' => 'required|string|max:60',
        ]);

        $periodLabel = $request->input('period');
        $frequency = \App\Support\PayPeriods::normalizeFrequency($request->input('frequency', 'semi_monthly'));
        $meta = \App\Support\PayPeriods::find($periodLabel, $frequency);
        if (!$meta) {
            // Retry across all frequencies (label may belong to another calendar).
            foreach (\App\Support\PayPeriods::FREQUENCIES as $f) {
                $meta = \App\Support\PayPeriods::find($periodLabel, $f);
                if ($meta) { $frequency = $f; break; }
            }
        }
        if (!$meta) {
            return back()->withErrors(['error' => 'Unknown pay period. Pick a period from the calendar.']);
        }

        $start = Carbon::parse($request->cutoff_start);
        $end   = Carbon::parse($request->cutoff_end);
        // Cutoff must live inside the selected pay period.
        if ($start->toDateString() < $meta['start'] || $end->toDateString() > $meta['end']) {
            return back()->withErrors(['error' => "Cutoff must be inside {$meta['label']} ({$meta['start']} to {$meta['end']})."]);
        }
        // Locked period: all rows Paid => no new drafts.
        $existing = Payroll::where('period', $periodLabel)->get();
        if ($existing->isNotEmpty() && $existing->every(fn ($p) => strtolower($p->status) === 'paid')) {
            return back()->withErrors(['error' => "Period {$periodLabel} is locked (all Paid). Paid is final."]);
        }
        $existingEmpIds = Payroll::where('period', $periodLabel)->pluck('employee_id')->all();

        $carbonPeriod = CarbonPeriod::create($start, $end);

        $employees = User::with('hrmDepartment:id,name')->whereIn('id', $request->employee_ids)->get();
        $payrollRecords = [];
        $skipped = [];

        DB::transaction(function () use ($employees, $carbonPeriod, $periodLabel, $frequency, $existingEmpIds, &$payrollRecords, &$skipped) {
            foreach ($employees as $employee) {
                if (empty($employee->employee_id)) {
                    $skipped[] = $employee->name.' (missing employee ID)';
                    continue;
                }
                // One row per employee per period (real-ERP idempotency).
                if (in_array($employee->employee_id, $existingEmpIds)) {
                    $skipped[] = $employee->name.' (already in '.$periodLabel.')';
                    continue;
                }

                $payrollSet = PayrollSet::forType($employee->position)->first();
                // Salary source: employee account first, position pay set as fallback.
                $empSalary = (float) ($employee->basic_salary ?? 0);
                if ($empSalary <= 0 && !$payrollSet) {
                    $skipped[] = $employee->name.' (no salary: empty employee basic_salary and no pay rate)';
                    continue;
                }
                if (!$payrollSet && $empSalary <= 0) {
                    $skipped[] = $employee->name." (no active pay rate for '{$employee->position}')";
                    continue;
                }
                $monthly = $empSalary > 0 ? $empSalary : (float) $payrollSet->base_salary;
                // Attendance daily rate derives from monthly via Payroll Settings divisor;
                // multipliers live in Payroll Settings (computeAttendanceSummary reads them).
                $effectiveSet = $payrollSet ? clone $payrollSet : new PayrollSet(['base_salary' => $monthly]);
                $effectiveSet->base_salary = $monthly;

                $summary = $this->computeAttendanceSummary($employee, $carbonPeriod, $effectiveSet);

                // Daily-rate formula: Gross = (days × rate) + night + OT + Sun/SP + holiday − late.
                // Statutory + loans start blank (0) for HR to fill in.
                $calc = PayrollCalc::calc([
                    'days' => $summary['days_worked'],
                    'daily' => $summary['daily_rate'],
                    'monthly' => $monthly,
                    'night_hours' => $summary['night_hours'],
                    'ot_hours' => $summary['overtime_hours'],
                    'sun_hours' => $summary['sunday_restday_hours'],
                    'holiday_amt' => $summary['holiday_amt'],
                    'late_minutes' => $summary['late_minutes'],
                    'sss' => 0, 'philhealth' => 0, 'pagibig' => 0,
                    'sss_loan' => 0, 'pf_loan' => 0,
                ], $frequency);

                $payroll = Payroll::create([
                    'employee_id'            => $employee->employee_id,
                    'employee_name'          => $employee->name,
                    'role'                   => $employee->position === 'manager' ? 'Manager' : ($employee->position === 'trainee' ? 'Trainee' : 'Staff'),
                    'period'                 => $periodLabel,
                    'department'             => $employee->department ?: ($employee->hrmDepartment?->name ?? null),
                    'job_title'              => null,
                    'base_salary'            => $monthly,
                    'monthly_salary'         => $monthly,
                    'allowance'              => 0,
                    'absent_days'            => $summary['absent_days'],
                    'holiday_days'           => $summary['holiday_days'],
                    'days_worked'            => $summary['days_worked'],
                    'daily_rate'             => $summary['daily_rate'],
                    'total_days_amt'         => $summary['total_days_amt'],
                    'night_hours'            => $summary['night_hours'],
                    'night_rate'             => $summary['night_rate'],
                    'night_amt'              => $summary['night_amt'],
                    'overtime_hours'         => $summary['overtime_hours'],
                    'ot_rate'                => $summary['ot_rate'],
                    'ot_amt'                 => $summary['ot_amt'],
                    'sunday_restday_hours'   => $summary['sunday_restday_hours'],
                    'sun_sp_rate'            => $summary['sun_sp_rate'],
                    'sun_sp_amt'             => $summary['sun_sp_amt'],
                    'holiday_amt'            => $summary['holiday_amt'],
                    'late_minutes'           => $summary['late_minutes'],
                    'late_rate_min'          => $calc['late_rate_min'],
                    'late_total_deduction'   => $calc['late_total'],
                    'sss_deduction'          => 0,
                    'philhealth_deduction'   => 0,
                    'pagibig_deduction'      => 0,
                    'tax_withheld'           => 0,
                    'sss_loan'               => 0,
                    'pf_loan'                => 0,
                    'gross_pay'              => $calc['gross'],
                    'net_pay'                => $calc['net'],
                    'employer_sss'           => 0,
                    'employer_philhealth'    => 0,
                    'employer_pagibig'       => 0,
                    'accrual_13th'           => $calc['accrual_13th'],
                    'status'                 => 'Draft',
                ]);

                  $payrollRecords[] = $payroll;
              }
          });

        if (count($payrollRecords)) {
            $this->auditPayroll('Payroll drafted for '.$periodLabel.' — '.$this->auditNames(collect($payrollRecords)), null, 'drafted');
        }
        NotifiesExecutive::push('payroll', count($payrollRecords) . ' payroll records drafted ('.$periodLabel.')', 'A new payroll batch was prepared and awaits HR submit.', 'ceo.approvals');

        if (count($payrollRecords) === 0) {
            $detail = $skipped ? ' Skipped: '.implode(', ', array_slice($skipped, 0, 5)).(count($skipped) > 5 ? ' (+'.(count($skipped) - 5).' more)' : '') : '';
            return back()->withErrors(['error' => 'No payroll generated. Check employee IDs and pay rates.'.$detail]);
        }

        $msg = count($payrollRecords).' payroll records generated as Draft.';
        if ($skipped) $msg .= ' Skipped '.count($skipped).': '.implode(', ', array_slice($skipped, 0, 3)).(count($skipped) > 3 ? '...' : '');
        return redirect()->route('hrm.payroll', ['period' => $periodLabel, 'frequency' => $frequency])->with('message', $msg);
    }

    /**
     * Display the specified payroll record with payslip breakdown.
     */
    public function show(Payroll $payroll)
    {
        $freq = \App\Support\PayPeriods::frequencyForLabel((string) ($payroll->period ?? ''));
        $calc = PayrollCalc::calc([
            'days' => (float) ($payroll->days_worked ?? 0),
            'daily' => (float) ($payroll->daily_rate ?? 0),
            'monthly' => (float) ($payroll->monthly_salary ?? $payroll->base_salary),
            'night_hours' => (float) $payroll->night_hours,
            'ot_hours' => (float) $payroll->overtime_hours,
            'sun_hours' => (float) ($payroll->sunday_restday_hours ?? 0),
            'holiday_amt' => (float) ($payroll->holiday_amt ?? 0),
            'late_minutes' => (float) $payroll->late_minutes,
            'sss' => (float) ($payroll->sss_deduction ?? 0),
            'philhealth' => (float) ($payroll->philhealth_deduction ?? 0),
            'pagibig' => (float) ($payroll->pagibig_deduction ?? 0),
            'sss_loan' => (float) ($payroll->sss_loan ?? 0),
            'pf_loan' => (float) ($payroll->pf_loan ?? 0),
        ], $freq);
        return Inertia::render('Dashboard/HRM_NEW/PayrollDetail', [
            'payroll' => array_merge($payroll->toArray(), ['status' => $this->normalizeStatus($payroll->status)]),
            'calc' => $calc,
            'frequency' => $freq,
            'attendance' => $this->attendanceSource($payroll),
            'settings' => \App\Models\Hrm\PayrollSetting::cached(),
        ]);
    }

    /**
     * Attendance-management source for a payroll row: shifts + logs +
     * leaves + holidays inside the row's pay period. Shown in Detail and
     * reused by the Sync button so OT / night / holiday / late / absences
     * always come from attendance, never typed guesses.
     */
    private function attendanceSource(Payroll $payroll): ?array
    {
        try {
            $meta = \App\Support\PayPeriods::find((string) ($payroll->period ?? ''), \App\Support\PayPeriods::frequencyForLabel((string) ($payroll->period ?? '')));
            if (!$meta || empty($meta['start']) || empty($meta['end'])) return null;
            $user = User::where('employee_id', $payroll->employee_id)->first();
            if (!$user) return null;
            $monthly = (float) ($payroll->monthly_salary ?? $payroll->base_salary ?? $user->basic_salary ?? 0);
            $set = PayrollSet::forType($user->position)->first()
                ?? new PayrollSet(['base_salary' => $monthly]);
            $set->base_salary = $monthly;
            $summary = $this->computeAttendanceSummary($user, CarbonPeriod::create(Carbon::parse($meta['start']), Carbon::parse($meta['end'])), $set);
            return [
                'scheduled' => $summary['scheduled_shifts'],
                'present' => $summary['days_worked'],
                'absent' => $summary['absent_days'],
                'leave' => $summary['leave_days'],
                'ot_hours' => round($summary['overtime_hours'], 2),
                'night_hours' => round($summary['night_hours'], 2),
                'sun_hours' => round($summary['sunday_restday_hours'], 2),
                'holiday_amt' => round($summary['holiday_amt'], 2),
                'late_minutes' => $summary['late_minutes'],
                'holiday_days' => $summary['holiday_days'],
                'cutoff' => $meta['start'].' to '.$meta['end'],
                'cutoff_start' => $meta['start'],
                'cutoff_end' => $meta['end'],
                // Days they were present (Y-m-d list) + absent/leave for the payslip.
                'present_dates' => $summary['present_dates'] ?? [],
                'absent_dates' => $summary['absent_dates'] ?? [],
                'leave_dates' => $summary['leave_dates'] ?? [],
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Re-pull OT / night / holiday / late / absences from attendance
     * management into a Draft row (HR only). Keeps HR-typed allowance /
     * salary / other deductions untouched.
     */
    public function syncAttendance(Payroll $payroll)
    {
        if ($this->normalizeStatus($payroll->status) !== 'Draft') {
            return back()->withErrors(['error' => 'Locked. Only Draft rows can re-sync from attendance.']);
        }
        if (!$this->isHR()) {
            return back()->withErrors(['error' => 'Only HR officer can do this.']);
        }
        $src = $this->attendanceSource($payroll);
        if (!$src) {
            return back()->withErrors(['error' => 'No attendance source found for this period/employee.']);
        }
        $freq = \App\Support\PayPeriods::frequencyForLabel((string) ($payroll->period ?? ''));
        $dailyForSync = (float) ($payroll->daily_rate ?? 0);
        if ($dailyForSync <= 0) {
            $psSync = \App\Models\Hrm\PayrollSetting::cached();
            $dailyForSync = (float) ($payroll->monthly_salary ?? $payroll->base_salary ?? 0) / ((float) ($psSync['daily_divisor'] ?? 26) ?: 26);
        }
        $calc = PayrollCalc::calc([
            'days' => $src['present'],
            'daily' => $dailyForSync,
            'monthly' => (float) ($payroll->monthly_salary ?? $payroll->base_salary),
            'night_hours' => $src['night_hours'], 'ot_hours' => $src['ot_hours'],
            'sun_hours' => $src['sun_hours'] ?? 0,
            'holiday_amt' => $src['holiday_amt'] ?? 0,
            'late_minutes' => $src['late_minutes'],
            'sss' => (float) ($payroll->sss_deduction ?? 0),
            'philhealth' => (float) ($payroll->philhealth_deduction ?? 0),
            'pagibig' => (float) ($payroll->pagibig_deduction ?? 0),
            'sss_loan' => (float) ($payroll->sss_loan ?? 0),
            'pf_loan' => (float) ($payroll->pf_loan ?? 0),
        ], $freq);
        $payroll->update([
            'days_worked' => $src['present'],
            'daily_rate' => $dailyForSync,
            'total_days_amt' => $calc['basic'],
            'overtime_hours' => $src['ot_hours'],
            'ot_rate' => $calc['ot_rate'],
            'ot_amt' => $calc['ot_amt'],
            'night_hours' => $src['night_hours'],
            'night_rate' => $calc['night_rate'],
            'night_amt' => $calc['night_amt'],
            'sunday_restday_hours' => $src['sun_hours'] ?? 0,
            'sun_sp_rate' => $calc['sun_sp_rate'],
            'sun_sp_amt' => $calc['sun_sp_amt'],
            'holiday_days' => $src['holiday_days'],
            'holiday_amt' => $calc['holiday_amt'],
            'late_minutes' => $src['late_minutes'],
            'late_rate_min' => $calc['late_rate_min'],
            'late_total_deduction' => $calc['late_total'],
            'absent_days' => $src['absent'],
            'gross_pay' => $calc['gross'],
            'net_pay' => $calc['net'],
            'accrual_13th' => $calc['accrual_13th'],
        ]);
        $this->auditPayroll('Synced from attendance: '.$payroll->employee_name.' ('.$payroll->period.', present '.$src['present'].'/absent '.$src['absent'].'/leave '.$src['leave'].')', $payroll->id, 'synced');
        return back()->with('message', 'Synced from attendance management.');
    }

    /**
     * HR edits a Draft row only. Locked otherwise. Paid is final.
     */
    public function update(Request $request, Payroll $payroll)
    {
        if ($this->normalizeStatus($payroll->status) !== 'Draft') {
            return back()->withErrors(['error' => 'Locked. Only HR can edit, and only while the row is Draft.']);
        }
        if (!$this->isHR()) {
            return back()->withErrors(['error' => 'Only HR officer can do this.']);
        }
        $v = $request->validate([
            'employee_name' => 'required|string|max:120',
            'job_title' => 'nullable|string|max:120',
            'department' => 'nullable|string|max:80',
            'days_worked' => 'nullable|numeric|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'monthly_salary' => 'nullable|numeric|min:0',
            'overtime_hours' => 'nullable|numeric|min:0',
            'night_hours' => 'nullable|numeric|min:0',
            'sunday_restday_hours' => 'nullable|numeric|min:0',
            'holiday_amt' => 'nullable|numeric|min:0',
            'holiday_days' => 'nullable|numeric|min:0',
            'late_minutes' => 'nullable|numeric|min:0',
            'sss_deduction' => 'nullable|numeric|min:0',
            'philhealth_deduction' => 'nullable|numeric|min:0',
            'pagibig_deduction' => 'nullable|numeric|min:0',
            'sss_loan' => 'nullable|numeric|min:0',
            'pf_loan' => 'nullable|numeric|min:0',
            // Legacy combined field still accepted.
            'allowance' => 'nullable|numeric|min:0',
            'other_deductions' => 'nullable|numeric|min:0',
        ]);
        $freq = \App\Support\PayPeriods::frequencyForLabel((string) ($payroll->period ?? ''));
        $calc = PayrollCalc::calc([
            'days' => (float) ($v['days_worked'] ?? $payroll->days_worked ?? 0),
            'daily' => (float) ($v['daily_rate'] ?? 0),
            'monthly' => (float) ($v['monthly_salary'] ?? $payroll->monthly_salary ?? $payroll->base_salary ?? 0),
            'night_hours' => (float) ($v['night_hours'] ?? 0),
            'ot_hours' => (float) ($v['overtime_hours'] ?? 0),
            'sun_hours' => (float) ($v['sunday_restday_hours'] ?? 0),
            'holiday_amt' => (float) ($v['holiday_amt'] ?? 0),
            'holiday_days' => (float) ($v['holiday_days'] ?? 0),
            'late_minutes' => (float) ($v['late_minutes'] ?? 0),
            'sss' => (float) ($v['sss_deduction'] ?? 0),
            'philhealth' => (float) ($v['philhealth_deduction'] ?? 0),
            'pagibig' => (float) ($v['pagibig_deduction'] ?? 0),
            'sss_loan' => (float) ($v['sss_loan'] ?? $v['other_deductions'] ?? 0),
            'pf_loan' => (float) ($v['pf_loan'] ?? 0),
        ], $freq);
        $before = $payroll->only(['days_worked', 'daily_rate', 'overtime_hours', 'night_hours', 'sunday_restday_hours', 'holiday_amt', 'late_minutes', 'sss_deduction', 'sss_loan', 'pf_loan']);
        $payroll->update([
            'employee_name' => $v['employee_name'],
            'job_title' => $v['job_title'] ?? $payroll->job_title,
            'department' => $v['department'] ?? $payroll->department,
            'monthly_salary' => (float) ($v['monthly_salary'] ?? $payroll->monthly_salary ?? $payroll->base_salary ?? 0),
            'base_salary' => (float) ($v['monthly_salary'] ?? $payroll->monthly_salary ?? $payroll->base_salary ?? 0),
            'days_worked' => $calc['days'],
            'daily_rate' => $calc['daily'],
            'total_days_amt' => $calc['basic'],
            'overtime_hours' => $calc['overtime_hours'],
            'ot_rate' => $calc['ot_rate'],
            'ot_amt' => $calc['ot_amt'],
            'night_hours' => $calc['night_hours'],
            'night_rate' => $calc['night_rate'],
            'night_amt' => $calc['night_amt'],
            'sunday_restday_hours' => $calc['sunday_hours'],
            'sun_sp_rate' => $calc['sun_sp_rate'],
            'sun_sp_amt' => $calc['sun_sp_amt'],
            'holiday_amt' => $calc['holiday_amt'],
            'late_minutes' => $calc['late_minutes'],
            'late_rate_min' => $calc['late_rate_min'],
            'late_total_deduction' => $calc['late_total'],
            'sss_deduction' => $calc['sss'],
            'philhealth_deduction' => $calc['philhealth'],
            'pagibig_deduction' => $calc['pagibig'],
            'tax_withheld' => 0,
            'sss_loan' => $calc['sss_loan'],
            'pf_loan' => $calc['pf_loan'],
            'allowance' => 0,
            'gross_pay' => $calc['gross'],
            'net_pay' => $calc['net'],
            'employer_sss' => 0,
            'employer_philhealth' => 0,
            'employer_pagibig' => 0,
            'accrual_13th' => $calc['accrual_13th'],
        ]);
        $changes = [];
        $labels = ['days_worked' => 'Days', 'daily_rate' => 'Rate', 'overtime_hours' => 'OT', 'night_hours' => 'Night', 'sunday_restday_hours' => 'Sun/SP', 'holiday_amt' => 'Holiday', 'late_minutes' => 'Late'];
        $after = ['days_worked' => $calc['days'], 'daily_rate' => $calc['daily'], 'overtime_hours' => $calc['overtime_hours'], 'night_hours' => $calc['night_hours'], 'sunday_restday_hours' => $calc['sunday_hours'], 'holiday_amt' => $calc['holiday_amt'], 'late_minutes' => $calc['late_minutes']];
        foreach ($after as $k => $nv) {
            $ov = (float) ($before[$k] ?? 0);
            if (abs($ov - $nv) > 0.0001) $changes[] = ($labels[$k] ?? $k).": {$ov}→{$nv}";
        }
        $detail = $changes ? ' ['.implode('; ', array_slice($changes, 0, 4)).']' : '';
        $this->auditPayroll('Edited '.$payroll->employee_name.' ('.$payroll->period.')'.$detail, $payroll->id, 'edited');
        return back()->with('message', 'Changes saved.');
    }

    public function destroy(Payroll $payroll)
    {
        if ($this->normalizeStatus($payroll->status) !== 'Draft' || !$this->isHR()) {
            return back()->withErrors(['error' => 'Locked. Only HR can remove a Draft row.']);
        }
        $name = $payroll->employee_name;
        $payroll->delete();
        $this->auditPayroll('Employee removed: '.$name, null, 'removed');
        return redirect()->route('hrm.payroll')->with('message', 'Employee removed.');
    }

    /**
     * Submit for approval: HR only. Draft -> Submitted.
     */
    public function submit(Request $request)
    {
        if (!$this->isHR()) {
            return back()->withErrors(['error' => 'Only HR officer can do this. Switch the role at the top.']);
        }
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        $rows = Payroll::whereIn('id', $ids)->get()->filter(fn ($p) => $this->normalizeStatus($p->status) === 'Draft');
        if ($rows->isEmpty()) {
            return back()->withErrors(['error' => 'None of the selected rows are draft.']);
        }
        foreach ($rows as $p) $p->update(['status' => 'Submitted']);
        $this->auditPayroll('Submitted for approval — '.$this->auditNames($rows), null, 'submitted');
        NotifiesExecutive::push('payroll', $rows->count().' payroll records submitted', 'HR submitted payroll for manager approval.', 'ceo.approvals');
        return back()->with('message', 'Submitted for approval ('.$rows->count().').');
    }

    /**
     * Approve: Manager only. Submitted -> Approved.
     */
    public function approve(Payroll $payroll)
    {
        // Single-row (legacy) path now enforces Manager + Submitted.
        if (!$this->isManager()) {
            return back()->withErrors(['error' => 'Only Manager can do this. Switch the role at the top.']);
        }
        if ($this->normalizeStatus($payroll->status) !== 'Submitted') {
            return back()->withErrors(['error' => 'None of the selected rows are submitted.']);
        }
        $payroll->update(['status' => 'Approved']);
        $this->auditPayroll('Approved: '.$payroll->employee_name, $payroll->id, 'approved');
        return back()->with('message', 'Approved.');
    }

    public function approveBatch(Request $request)
    {
        if (!$this->isManager()) {
            return back()->withErrors(['error' => 'Only Manager can do this. Switch the role at the top.']);
        }
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        $rows = Payroll::whereIn('id', $ids)->get()->filter(fn ($p) => $this->normalizeStatus($p->status) === 'Submitted');
        if ($rows->isEmpty()) {
            return back()->withErrors(['error' => 'None of the selected rows are submitted.']);
        }
        foreach ($rows as $p) $p->update(['status' => 'Approved']);
        $this->auditPayroll('Approved — '.$this->auditNames($rows), null, 'approved');
        return back()->with('message', 'Approved ('.$rows->count().').');
    }

    /**
     * Return to draft: Manager only. Submitted or Approved -> Draft. Paid is final.
     */
    public function returnToDraft(Request $request)
    {
        if (!$this->isManager()) {
            return back()->withErrors(['error' => 'Only Manager can do this. Switch the role at the top.']);
        }
        $v = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer', 'reason' => 'nullable|string|max:1000']);
        $rows = Payroll::whereIn('id', $v['ids'])->get()->filter(fn ($p) => in_array($this->normalizeStatus($p->status), ['Submitted', 'Approved']));
        if ($rows->isEmpty()) {
            return back()->withErrors(['error' => 'None of the selected rows are submitted.']);
        }
        foreach ($rows as $p) $p->update(['status' => 'Draft']);
        $this->auditPayroll('Returned to draft — '.$this->auditNames($rows), null, 'returned', $v['reason'] ?? null);
        return back()->with('message', 'Returned to draft ('.$rows->count().').');
    }

    /**
     * Reject a single row (Manager). Submitted or Approved -> Draft.
     */
    public function reject(Request $request, Payroll $payroll)
    {
        if (!$this->isManager()) {
            return back()->withErrors(['error' => 'Only Manager can do this. Switch the role at the top.']);
        }
        $request->validate(['reason' => 'nullable|string']);
        if (!in_array($this->normalizeStatus($payroll->status), ['Submitted', 'Approved'])) {
            return back()->withErrors(['error' => 'Only Submitted or Approved rows can be returned to draft. Paid is final.']);
        }
        $payroll->update(['status' => 'Draft']);
        $this->auditPayroll('Returned to draft: '.$payroll->employee_name, $payroll->id, 'returned', $request->input('reason'));
        return back()->with('message', 'Returned to draft.');
    }

    /**
     * Mark paid: Finance only. Approved -> Paid. Paid is final.
     * Records paid_at / paid_by / payment_reference for proof.
     */
    public function markPaid(Request $request)
    {
        if (!$this->isFinance()) {
            return back()->withErrors(['error' => 'Only Finance can do this. Switch the role at the top.']);
        }
        $v = $request->validate([
            'ids' => 'required|array', 'ids.*' => 'integer',
            'payment_reference' => 'nullable|string|max:80',
            'payment_method' => 'nullable|string|max:40',
        ]);
        $rows = Payroll::whereIn('id', $v['ids'])->get()->filter(fn ($p) => $this->normalizeStatus($p->status) === 'Approved');
        if ($rows->isEmpty()) {
            return back()->withErrors(['error' => 'None of the selected rows are approved.']);
        }
        $ps = \App\Models\Hrm\PayrollSetting::cached();
        $ref = $v['payment_reference'] ?? ((string) ($ps['payment_ref_prefix'] ?? 'PAY-').now()->format('Ymd-His'));
        foreach ($rows as $p) {
            $p->update([
                'status' => 'Paid',
                'paid_at' => now(),
                'paid_by' => auth()->id(),
                'payment_reference' => $ref,
                'payment_method' => $v['payment_method'] ?? (string) ($ps['default_payment_method'] ?? 'bank'),
            ]);
        }
        $this->auditPayroll('Marked as paid '.$ref.' — '.$this->auditNames($rows), null, 'paid');
        return back()->with('message', 'Marked as paid ('.$rows->count().', ref '.$ref.').');
    }

    /**
     * Core computation of attendance summary for a given employee and period.
     *
     * Single authoritative implementation: App\Services\Payroll\PayrollSummaryService.
     * Both batch "Generate Payroll" and single "Add Employee" call through here,
     * so holiday-pay logic cannot drift between flows. Approval, payslip, and
     * audit code is untouched and reads the same summary keys.
     */
    private function computeAttendanceSummary($employee, $period, $payrollSet)
    {
        return \App\Services\Payroll\PayrollSummaryService::summarize($employee, $period, $payrollSet);
    }

    /**
     * Government contributions based on monthly gross pay.
     */
    private function computeSSS($monthlyGross)
    {
        $msc = $this->getMSC($monthlyGross);
        $rate = GovernmentContributionRate::ofType('sss')
            ->where('min_compensation', '<=', $msc)
            ->where('max_compensation', '>=', $msc)
            ->first();
        return $rate ? $msc * ($rate->percentage / 100) : 0;
    }

    private function computePhilHealth($monthlyGross)
    {
        $base = max(10000, min(100000, $monthlyGross));
        return $base * 0.025; // 2.5% employee share
    }

    private function computePagIBIG($monthlyGross)
    {
        return min($monthlyGross * 0.02, 200);
    }

    private function computeWithholdingTax($monthlyGross, $baseSalary)
    {
        // TRAIN Law annualized computation
        $annual = $monthlyGross * 12;
        if ($annual <= 250000) return 0;
        if ($annual <= 400000) return ($annual - 250000) * 0.15 / 12;
        if ($annual <= 800000) return (22500 + ($annual - 400000) * 0.20) / 12;
        if ($annual <= 2000000) return (102500 + ($annual - 800000) * 0.25) / 12;
        if ($annual <= 8000000) return (402500 + ($annual - 2000000) * 0.30) / 12;
        return (2202500 + ($annual - 8000000) * 0.35) / 12;
    }

    private function getMSC($monthly)
    {
        $msc = round($monthly / 500) * 500;
        return max(4000, min(30000, $msc));
    }
}