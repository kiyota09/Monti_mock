<?php

namespace App\Http\Controllers\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Hrm\AttendanceLog;
use App\Models\Core\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AbsentController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (! $user->canAccessWorkforce(null, null, 'manage')) {
            abort(403);
        }

        $absentEmployees = User::where('is_active', true)
            ->whereDoesntHave('latestAttendance', function ($q) {
                $q->where('date', today());
            })
            ->orWhereHas('latestAttendance', function ($q) {
                $q->where('date', today())->where('status', 'Absent');
            })
            ->with(['latestAttendance', 'currentShift'])
            ->get()
            ->map(function ($emp) {
                $consecutiveAbsent = AttendanceLog::where('user_id', $emp->id)
                    ->where('status', 'Absent')
                    ->where('date', '>=', now()->subDays(30))
                    ->count();

                return [
                    'id' => $emp->id,
                    'name' => $emp->name,
                    'role' => $emp->role,
                    'department' => $emp->manufacturing_role ?? $emp->role,
                    'consecutive_absent_days' => $consecutiveAbsent,
                    'last_clock_in' => $emp->latestAttendance?->clock_in,
                ];
            });

        return Inertia::render('Dashboard/Workforce/Absent', ['absentEmployees' => $absentEmployees]);
    }

    public function suspend(Request $request, $id)
    {
        $user = Auth::user();
        if (! $user->canAccessWorkforce(null, null, 'manage')) {
            abort(403);
        }
        $request->validate(['reason' => 'required|string']);

        $employee = User::findOrFail($id);
        $employee->update(['is_active' => false]);

        // Log in audit_logs or create a suspension record if needed
        \App\Models\AuditLog::create([
            'admin_id' => Auth::id(),
            'target_id' => $employee->id,
            'target_name' => $employee->name,
            'action' => 'suspend',
            'reason' => $request->reason,
        ]);

        return back()->with('message', "{$employee->name} has been suspended.");
    }
}
