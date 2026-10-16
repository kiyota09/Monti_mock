<?php

namespace App\Http\Controllers\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Hrm\LeaveRequest;
use App\Models\Core\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LeaveController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (!$user->canAccessWorkforce(null, null, 'manage')) {
            abort(403);
        }
        
        $leaveRequests = LeaveRequest::with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($lr) => [
                'id' => $lr->id,
                'user_id' => $lr->user_id,
                'user_name' => $lr->user->name,
                'user_role' => $lr->user->role,
                'leave_type' => $lr->leave_type,
                'start_date' => $lr->start_date,
                'end_date' => $lr->end_date,
                'reason' => $lr->reason,
                'status' => $lr->status,
                'created_at' => $lr->created_at,
            ]);
        
        return Inertia::render('Dashboard/Workforce/Leave', ['leaveRequests' => $leaveRequests]);
    }
    
    public function approve($id)
    {
        if (! Auth::user()->canAccessWorkforce(null, null, 'manage')) {
            abort(403);
        }
        $leave = LeaveRequest::findOrFail($id);
        $leave->update(['status' => 'approved']);
        return back()->with('message', 'Leave approved.');
    }
    
    public function reject(Request $request, $id)
    {
        if (! Auth::user()->canAccessWorkforce(null, null, 'manage')) {
            abort(403);
        }
        $request->validate(['reason' => 'required|string']);
        $leave = LeaveRequest::findOrFail($id);
        $leave->update(['status' => 'rejected', 'reason' => $request->reason]);
        return back()->with('message', 'Leave rejected.');
    }
}