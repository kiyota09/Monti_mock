<?php

namespace App\Http\Controllers\Vp;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\AttendanceLog;
use App\Models\Hrm\LeaveRequest;
use App\Models\Hrm\Payroll;
use Inertia\Inertia;

class VpWorkforceController extends Controller
{
    /**
     * Workforce overview: manpower, attendance, leave, hiring, cost.
     * Read-only — HR owns the transactions.
     */
    public function index()
    {
        $today = today()->toDateString();

        $headcount = User::where('is_active', true)
            ->selectRaw('role, COUNT(*) c')
            ->groupBy('role')
            ->pluck('c', 'role');

        $attendance = AttendanceLog::whereDate('date', $today)
            ->selectRaw('status, COUNT(*) c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $leave = LeaveRequest::selectRaw('status, COUNT(*) c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $applicants = Applicant::selectRaw('status, COUNT(*) c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return Inertia::render('Dashboard/VP/Workforce', [
            'headcount' => $headcount,
            'attendanceToday' => $attendance,
            'leave' => $leave,
            'applicants' => $applicants,
            'payrollMonth' => (float) Payroll::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)->sum('net_pay'),
            'supervisors' => User::where('is_manufacturing_supervisor', true)
                ->get(['id', 'name', 'supervisor_department']),
        ]);
    }
}
