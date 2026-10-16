<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Hrm\AttendanceLog;
use App\Models\Hrm\Holiday;
use App\Models\Hrm\LeaveRequest;
use App\Models\Hrm\Payroll;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TraineeDashboardController extends Controller
{
    /**
     * Display the trainee dashboard with key metrics.
     */
    public function index()
    {
        $user = Auth::user();

        // Validate user is a trainee
        if (strtolower($user->position) !== 'trainee') {
            abort(403, 'Unauthorized access. This page is for trainees only.');
        }

        // Calculate attendance percentage (last 30 days)
        $attendancePercentage = $this->calculateAttendancePercentage($user->id);

        // Get current payroll status
        $currentPayroll = Payroll::where('employee_id', $user->employee_id)
            ->latest('created_at')
            ->first();

        // Get upcoming holidays (next 30 days)
        $upcomingHolidays = Holiday::where('holiday_date', '>=', now())
            ->where('holiday_date', '<=', now()->addDays(30))
            ->orderBy('holiday_date')
            ->take(5)
            ->get();

        // Get recent attendance records (last 7 days)
        $recentAttendance = AttendanceLog::where('user_id', $user->id)
            ->where('date', '>=', now()->subDays(7))
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn ($record) => [
                'date' => is_string($record->date) ? $record->date : $record->date->format('Y-m-d'),
                'clockIn' => $record->clock_in,
                'clockOut' => $record->clock_out,
                'status' => $record->status,
            ]);

        // Calculate leave balance
        $leaveBalance = $this->calculateLeaveBalance($user->id);

        // Get attendance statistics
        $attendanceStats = $this->getAttendanceStatistics($user->id);

        return Inertia::render('Dashboard/TRAINEE/Dashboard', [
            'user' => $user,
            'attendancePercentage' => $attendancePercentage,
            'currentPayroll' => $currentPayroll ? [
                'id' => $currentPayroll->id,
                'gross_pay' => (float) $currentPayroll->gross_pay,
                'net_pay' => (float) $currentPayroll->net_pay,
                'status' => $currentPayroll->status,
            ] : null,
            'upcomingHolidays' => $upcomingHolidays->map(fn ($h) => [
                'id' => $h->id,
                'date' => $h->holiday_date->format('Y-m-d'),
                'name' => $h->holiday_name,
                'type' => $h->holiday_type,
            ]),
            'recentAttendance' => $recentAttendance,
            'leaveBalance' => $leaveBalance,
            'attendanceStats' => $attendanceStats,
        ]);
    }

    /**
     * Calculate attendance percentage for a user.
     */
    private function calculateAttendancePercentage(int $userId): float
    {
        $thirtyDaysAgo = now()->subDays(30);
        $totalWorkDays = AttendanceLog::where('user_id', $userId)
            ->where('date', '>=', $thirtyDaysAgo)
            ->count();

        if ($totalWorkDays === 0) {
            return 0;
        }

        $presentDays = AttendanceLog::where('user_id', $userId)
            ->where('date', '>=', $thirtyDaysAgo)
            ->whereIn('status', ['present', 'late'])
            ->count();

        return round(($presentDays / $totalWorkDays) * 100, 2);
    }

    /**
     * Calculate remaining leave balance.
     */
    private function calculateLeaveBalance(int $userId): array
    {
        $currentYear = now()->year;

        // Assuming 15 days annual leave
        $totalAnnualLeave = 15;

        $usedLeave = LeaveRequest::where('user_id', $userId)
            ->where('status', 'approved')
            ->whereYear('start_date', $currentYear)
            ->sum(\DB::raw('DATEDIFF(end_date, start_date)'));

        $remaining = max(0, $totalAnnualLeave - $usedLeave);

        return [
            'total' => $totalAnnualLeave,
            'used' => (int) $usedLeave,
            'remaining' => $remaining,
        ];
    }

    /**
     * Get attendance statistics.
     */
    private function getAttendanceStatistics(int $userId): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        $presentCount = AttendanceLog::where('user_id', $userId)
            ->where('date', '>=', $thirtyDaysAgo)
            ->where('status', 'present')
            ->count();

        $lateCount = AttendanceLog::where('user_id', $userId)
            ->where('date', '>=', $thirtyDaysAgo)
            ->where('status', 'late')
            ->count();

        $absentCount = AttendanceLog::where('user_id', $userId)
            ->where('date', '>=', $thirtyDaysAgo)
            ->where('status', 'absent')
            ->count();

        $onLeaveCount = AttendanceLog::where('user_id', $userId)
            ->where('date', '>=', $thirtyDaysAgo)
            ->where('status', 'on_leave')
            ->count();

        return [
            'present' => $presentCount,
            'late' => $lateCount,
            'absent' => $absentCount,
            'onLeave' => $onLeaveCount,
        ];
    }
}
