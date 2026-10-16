<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Hrm\LeaveRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    /**
     * Display the leave management page with history.
     */
    public function leave(): Response
    {
        return Inertia::render('Dashboard/USERS/leave', [
            'leaveHistory' => LeaveRequest::where('user_id', auth()->id())
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($leave) {
                    return [
                        'id' => $leave->id,
                        'leave_type' => $leave->leave_type,
                        // Ensure dates are strings for the Vue component
                        'start_date' => $leave->start_date->format('Y-m-d'),
                        'end_date' => $leave->end_date->format('Y-m-d'),
                        'reason' => $leave->reason,
                        'status' => $leave->status,
                        'created_at' => $leave->created_at->diffForHumans(),
                    ];
                }),
        ]);
    }

    /**
     * Store a newly created leave request in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'leave_type' => 'required|string|in:sick,vacation,personal',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:10|max:500',
        ]);

        // This creates the record via the User -> LeaveRequest relationship
        // Ensure you have defined 'public function leaveRequests()' in your User model
        $request->user()->leaveRequests()->create($validated);

        return redirect()->back()->with('message', 'Leave request submitted successfully!');
    }
}
