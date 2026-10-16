<?php

namespace App\Http\Controllers\Trainee;

use App\Http\Controllers\Controller;
use App\Models\Hrm\AttendanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TraineeTimeKeepingController extends Controller
{
    /**
     * Display the time keeping/attendance page.
     */
    public function index()
    {
        $user = Auth::user();

        // Validate user is a trainee
        if (strtolower($user->position) !== 'trainee') {
            abort(403, 'Unauthorized access. This page is for trainees only.');
        }

        // Get today's attendance record
        $today = now()->startOfDay();
        $todayAttendance = AttendanceLog::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        // Get daily time records for the current week
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();
        $weeklyRecords = AttendanceLog::where('user_id', $user->id)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn($record) => [
                'id' => $record->id,
                'date' => is_string($record->date) ? $record->date : $record->date->format('Y-m-d'),
                'dayOfWeek' => is_string($record->date) ? \Carbon\Carbon::parse($record->date)->format('l') : $record->date->format('l'),
                'clockIn' => $record->clock_in,
                'clockOut' => $record->clock_out,
                'duration' => $this->calculateDuration($record->clock_in, $record->clock_out),
                'status' => $record->status,
            ]);

        $currentStatus = $this->determineCurrentStatus($todayAttendance);

        return Inertia::render('Dashboard/TRAINEE/TimeKeeping', [
            'user' => $user,
            'todayAttendance' => $todayAttendance ? [
                'id' => $todayAttendance->id,
                'date' => $todayAttendance->date->format('Y-m-d'),
                'clockIn' => $todayAttendance->clock_in,
                'clockOut' => $todayAttendance->clock_out,
                'status' => $todayAttendance->status,
            ] : null,
            'currentStatus' => $currentStatus,
            'weeklyRecords' => $weeklyRecords,
        ]);
    }

    /**
     * Handle clock in/out action.
     */
    public function clockInOut(Request $request)
    {
        $user = Auth::user();

        // Validate user is a trainee
        if (strtolower($user->position) !== 'trainee') {
            abort(403, 'Unauthorized access.');
        }

        $request->validate([
            'action' => 'required|in:in,out',
        ]);

        $today = now()->startOfDay();
        $attendance = AttendanceLog::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        $now = now()->format('H:i:s');

        if ($request->action === 'in') {
            // Prevent double check-in
            if ($attendance && $attendance->clock_in && !$attendance->clock_out) {
                return redirect()->back()->with('error', 'You are already clocked in. Please clock out first.');
            }

            // Create new attendance record if doesn't exist
            if (!$attendance) {
                AttendanceLog::create([
                    'user_id' => $user->id,
                    'date' => $today,
                    'clock_in' => $now,
                    'status' => 'present',
                ]);
            } else {
                // Update existing record (for second check-in of the day)
                $attendance->update([
                    'clock_in' => $now,
                    'status' => 'present',
                ]);
            }

            return redirect()->back()->with('success', 'Checked in successfully at ' . $now);
        } else {
            // Clock out
            if (!$attendance || !$attendance->clock_in) {
                return redirect()->back()->with('error', 'Please clock in first before clocking out.');
            }

            if ($attendance->clock_out) {
                return redirect()->back()->with('error', 'You have already clocked out today.');
            }

            $attendance->update([
                'clock_out' => $now,
            ]);

            return redirect()->back()->with('success', 'Checked out successfully at ' . $now);
        }
    }

    /**
     * Calculate duration between clock in and out.
     */
    private function calculateDuration(?string $clockIn, ?string $clockOut): ?string
    {
        if (!$clockIn || !$clockOut) {
            return null;
        }

        $start = \DateTime::createFromFormat('H:i:s', $clockIn);
        $end = \DateTime::createFromFormat('H:i:s', $clockOut);

        if (!$start || !$end) {
            return null;
        }

        $interval = $start->diff($end);
        return sprintf('%02d:%02d:%02d', $interval->h, $interval->i, $interval->s);
    }

    /**
     * Determine the current status based on today's attendance.
     */
    private function determineCurrentStatus(?AttendanceLog $attendance): string
    {
        if (!$attendance) {
            return 'not_clocked_in';
        }

        if ($attendance->clock_in && !$attendance->clock_out) {
            return 'clocked_in';
        }

        if ($attendance->clock_in && $attendance->clock_out) {
            return 'clocked_out';
        }

        return 'not_clocked_in';
    }
}
