<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Hrm\AttendanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TraineeAttendanceController extends Controller
{
    /**
     * Display the attendance page with monthly calendar and statistics.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Validate user is a trainee
        if (strtolower($user->position) !== 'trainee') {
            abort(403, 'Unauthorized access. This page is for trainees only.');
        }

        // Get month and year from query parameters or use current
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        // Validate month and year
        $month = max(1, min(12, (int) $month));
        $year = (int) $year;

        // Get all attendance records for the selected month
        $startOfMonth = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $monthlyAttendance = AttendanceLog::where('user_id', $user->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn ($record) => (is_string($record->date) ? $record->date : $record->date->format('Y-m-d')));

        // Build calendar data
        $calendarDays = $this->buildCalendar($year, $month, $monthlyAttendance);

        // Calculate monthly statistics
        $statistics = $this->calculateStatistics($monthlyAttendance);

        // Get detailed records for the month
        $detailedRecords = $monthlyAttendance->sortByDesc('date')->values()->map(fn ($record) => [
            'id' => $record->id,
            'date' => is_string($record->date) ? $record->date : $record->date->format('Y-m-d'),
            'clockIn' => $record->clock_in,
            'clockOut' => $record->clock_out,
            'duration' => $this->calculateDuration($record->clock_in, $record->clock_out),
            'status' => $record->status,
            'statusBadge' => $this->getStatusBadge($record->status),
        ])->toArray();

        return Inertia::render('Dashboard/TRAINEE/Attendance', [
            'user' => $user,
            'currentMonth' => $month,
            'currentYear' => $year,
            'calendarDays' => $calendarDays,
            'statistics' => $statistics,
            'detailedRecords' => $detailedRecords,
        ]);
    }

    /**
     * Build calendar data for the month.
     */
    private function buildCalendar(int $year, int $month, $monthlyAttendance): array
    {
        $calendar = [];
        $date = \Carbon\Carbon::createFromDate($year, $month, 1);
        $daysInMonth = $date->daysInMonth;

        // Fill in the days
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDate = \Carbon\Carbon::createFromDate($year, $month, $day);
            $dateString = $currentDate->format('Y-m-d');

            $record = $monthlyAttendance->get($dateString);
            $status = $record ? $record->status : null;

            $calendar[] = [
                'day' => $day,
                'date' => $dateString,
                'dayOfWeek' => $currentDate->format('l'),
                'status' => $status,
                'statusClass' => $this->getStatusClass($status),
                'hasRecord' => (bool) $record,
            ];
        }

        return $calendar;
    }

    /**
     * Calculate attendance statistics for the month.
     */
    private function calculateStatistics($monthlyAttendance): array
    {
        $total = $monthlyAttendance->count();
        $present = $monthlyAttendance->where('status', 'present')->count();
        $late = $monthlyAttendance->where('status', 'late')->count();
        $absent = $monthlyAttendance->where('status', 'absent')->count();
        $onLeave = $monthlyAttendance->where('status', 'on_leave')->count();

        return [
            'totalDays' => $total,
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'onLeave' => $onLeave,
            'attendanceRate' => $total > 0 ? round((($present + $late) / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get CSS class for status.
     */
    private function getStatusClass(?string $status): string
    {
        return match ($status) {
            'present' => 'bg-green-100 text-green-800 border-green-300',
            'late' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
            'absent' => 'bg-red-100 text-red-800 border-red-300',
            'on_leave' => 'bg-blue-100 text-blue-800 border-blue-300',
            default => 'bg-gray-100 text-gray-600 border-gray-300',
        };
    }

    /**
     * Get status badge information.
     */
    private function getStatusBadge(string $status): array
    {
        return match ($status) {
            'present' => ['label' => 'Present', 'color' => 'success'],
            'late' => ['label' => 'Late', 'color' => 'warning'],
            'absent' => ['label' => 'Absent', 'color' => 'danger'],
            'on_leave' => ['label' => 'On Leave', 'color' => 'info'],
            default => ['label' => 'Unknown', 'color' => 'secondary'],
        };
    }

    /**
     * Calculate duration between clock in and out.
     */
    private function calculateDuration(?string $clockIn, ?string $clockOut): ?string
    {
        if (! $clockIn || ! $clockOut) {
            return null;
        }

        $start = \DateTime::createFromFormat('H:i:s', $clockIn);
        $end = \DateTime::createFromFormat('H:i:s', $clockOut);

        if (! $start || ! $end) {
            return null;
        }

        $interval = $start->diff($end);

        return sprintf('%02d:%02d', $interval->h, $interval->i);
    }
}
