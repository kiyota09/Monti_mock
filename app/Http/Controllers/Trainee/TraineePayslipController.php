<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Payroll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TraineePayslipController extends Controller
{
    /**
     * Display the payslip page with list of payslips.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Validate user is a trainee
        if (strtolower($user->position) !== 'trainee') {
            abort(403, 'Unauthorized access. This page is for trainees only.');
        }

        // Get month and year filters from query parameters
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        // Validate month and year
        $month = max(1, min(12, (int) $month));
        $year = (int) $year;

        // Fetch payslips for the user
        $payslips = Payroll::where('employee_id', $user->employee_id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($payroll) => [
                'id' => $payroll->id,
                'period' => $this->formatPayslipPeriod($payroll->created_at),
                'grossPay' => (float) $payroll->gross_pay,
                'netPay' => (float) $payroll->net_pay,
                'date' => $payroll->created_at->format('Y-m-d'),
                'status' => $payroll->status,
                'statusBadge' => $this->getStatusBadge($payroll->status),
            ]);

        // Get latest payslip details
        $latestPayslip = Payroll::where('employee_id', $user->employee_id)
            ->latest('created_at')
            ->first();

        $payslipDetails = $latestPayslip ? $this->formatPayslipDetails($latestPayslip) : null;

        return Inertia::render('Dashboard/TRAINEE/Payslip', [
            'user' => $user,
            'payslips' => $payslips,
            'payslipDetails' => $payslipDetails,
            'currentMonth' => $month,
            'currentYear' => $year,
        ]);
    }

    /**
     * Get details of a specific payslip.
     */
    public function show(Request $request, Payroll $payroll)
    {
        $user = Auth::user();

        // Validate user is a trainee
        if (strtolower($user->position) !== 'trainee') {
            abort(403, 'Unauthorized access.');
        }

        // Ensure user can only view their own payslips
        if ($payroll->employee_id !== $user->employee_id) {
            abort(403, 'You cannot view other users\' payslips.');
        }

        return response()->json($this->formatPayslipDetails($payroll));
    }

    /**
     * Format payslip details for frontend.
     */
    private function formatPayslipDetails(Payroll $payroll): array
    {
        return [
            'id' => $payroll->id,
            'period' => $this->formatPayslipPeriod($payroll->created_at),
            'date' => $payroll->created_at->format('Y-m-d'),
            'employeeName' => $payroll->employee_name,
            'employeeId' => $payroll->employee_id,
            'role' => $payroll->role,
            'baseSalary' => (float) $payroll->base_salary,
            'daysWorked' => (float) $payroll->days_worked,
            'dailyRate' => (float) $payroll->daily_rate,
            'totalDaysAmt' => (float) $payroll->total_days_amt,
            'nightHours' => (float) $payroll->night_hours,
            'nightRate' => (float) $payroll->night_rate,
            'nightAmt' => (float) $payroll->night_amt,
            'overtimeHours' => (float) $payroll->overtime_hours,
            'otRate' => (float) $payroll->ot_rate,
            'otAmt' => (float) $payroll->ot_amt,
            'sundayRestdayHours' => (float) $payroll->sunday_restday_hours,
            'sunSpRate' => (float) $payroll->sun_sp_rate,
            'sunSpAmt' => (float) $payroll->sun_sp_amt,
            'holidayAmt' => (float) $payroll->holiday_amt,
            'lateMinutes' => (float) $payroll->late_minutes,
            'lateRateMin' => (float) $payroll->late_rate_min,
            'lateTotalDeduction' => (float) $payroll->late_total_deduction,
            'grossPay' => (float) $payroll->gross_pay,
            'deductions' => [
                'sss' => (float) $payroll->sss_deduction,
                'philhealth' => (float) $payroll->philhealth_deduction,
                'pagibig' => (float) $payroll->pagibig_deduction,
                'taxWithheld' => (float) $payroll->tax_withheld,
                'sssLoan' => (float) $payroll->sss_loan,
                'pfLoan' => (float) $payroll->pf_loan,
                'totalDeductions' => $this->calculateTotalDeductions($payroll),
            ],
            'netPay' => (float) $payroll->net_pay,
            'status' => $payroll->status,
        ];
    }

    /**
     * Calculate total deductions.
     */
    private function calculateTotalDeductions(Payroll $payroll): float
    {
        return (float) (
            $payroll->sss_deduction +
            $payroll->philhealth_deduction +
            $payroll->pagibig_deduction +
            $payroll->tax_withheld +
            $payroll->sss_loan +
            $payroll->pf_loan +
            $payroll->late_total_deduction
        );
    }

    /**
     * Format payslip period from date.
     */
    private function formatPayslipPeriod($date): string
    {
        $month = $date->format('F');
        $year = $date->format('Y');

        return "Payslip - {$month} {$year}";
    }

    /**
     * Get status badge.
     */
    private function getStatusBadge(string $status): array
    {
        return match ($status) {
            'pending' => ['label' => 'Pending', 'color' => 'warning'],
            'approved' => ['label' => 'Approved', 'color' => 'success'],
            'rejected' => ['label' => 'Rejected', 'color' => 'danger'],
            'paid' => ['label' => 'Paid', 'color' => 'success'],
            default => ['label' => 'Unknown', 'color' => 'secondary'],
        };
    }
}
