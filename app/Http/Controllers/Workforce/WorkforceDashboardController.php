<?php

namespace App\Http\Controllers\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Hrm\LeaveRequest;
use App\Models\Core\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WorkforceDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Determine which users to show based on permissions
        $usersQuery = User::where('is_active', true);

        if (! $user->canAccessWorkforce(null, null, 'view')) {
            abort(403);
        }

        // If user has specific module/department restrictions, filter
        $perms = $user->workforcePermissions;
        if ($perms->isNotEmpty() && $user->role !== 'CEO') {
            $modules = $perms->pluck('module')->filter()->unique();
            $departments = $perms->pluck('department')->filter()->unique();
            $usersQuery->where(function ($q) use ($modules, $departments) {
                if ($modules->isNotEmpty()) {
                    $q->whereIn('role', $modules);
                }
                if ($departments->isNotEmpty()) {
                    $q->whereIn('manufacturing_role', $departments);
                }
            });
        }

        $employees = $usersQuery->with(['latestAttendance', 'currentShift'])->get()->map(function ($emp) {
            return [
                'id' => $emp->id,
                'name' => $emp->name,
                'role' => $emp->role,
                'department' => $emp->manufacturing_role ?? $emp->role,
                'shift' => $emp->currentShift?->shift_type ?? 'Not Assigned',
                'status' => $emp->latestAttendance?->status ?? 'Absent',
                'is_on_leave' => LeaveRequest::where('user_id', $emp->id)
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', now())
                    ->whereDate('end_date', '>=', now())
                    ->exists(),
            ];
        });

        $stats = [
            'total' => $employees->count(),
            'present' => $employees->where('status', 'On-Time')->count() + $employees->where('status', 'Late')->count(),
            'absent' => $employees->where('status', 'Absent')->count(),
            'on_leave' => $employees->where('is_on_leave', true)->count(),
        ];

        return Inertia::render('Dashboard/Workforce/WorkDashboard', [
            'employees' => $employees,
            'stats' => $stats,
        ]);
    }
}
