<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Hrm\AttendanceLog;
use App\Models\Hrm\EmployeeShift;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AppController extends Controller
{
    /**
     * Display the main employee dashboard (app.vue).
     */
    public function index()
    {
        $user = Auth::user();
        $now = Carbon::now('Asia/Manila');
        $today = $now->toDateString();

        return Inertia::render('Dashboard/USERS/app', [
            'user' => $user,
            // Fetch today's log to determine if currently clocked in
            'today_log' => AttendanceLog::where('user_id', $user->id)
                ->where('date', $today)
                ->first(),

            // Fetch today's assigned shift from the scheduler
            'assigned_shift' => EmployeeShift::where('user_id', $user->id)
                ->where('effective_date', $today)
                ->first(),

            // Fetch recent attendance history for the dashboard table
            'attendance_history' => AttendanceLog::where('user_id', $user->id)
                ->orderBy('date', 'desc')
                ->take(5)
                ->get()
                ->map(fn ($log) => [
                    'date' => Carbon::parse($log->date)->format('M d'),
                    'clockIn' => $log->clock_in ?? '--:--',
                    'status' => $log->status,
                ]),
        ]);
    }
}
