<?php

namespace App\Http\Controllers\Sec;

use App\Models\Sec\SecretaryMeeting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SecretaryMeetingController extends SecretaryController
{
    public function index(Request $request)
    {
        $query = SecretaryMeeting::with('creator')->orderBy('meeting_date')->orderBy('start_time');

        if ($search = $request->search) {
            $query->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('venue', 'like', "%{$search}%")
                ->orWhere('organizer', 'like', "%{$search}%"));
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->from) {
            $query->whereDate('meeting_date', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('meeting_date', '<=', $request->to);
        }

        return Inertia::render('Dashboard/SEC/Meetings', [
            'meetings' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only('search', 'status', 'from', 'to'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'meeting_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'venue' => 'nullable|string|max:255',
            'organizer' => 'nullable|string|max:255',
            'attendees' => 'nullable|string',
            'agenda' => 'nullable|string',
        ]);

        SecretaryMeeting::create([
            ...$validated,
            'status' => 'scheduled',
            'created_by' => auth()->id(),
        ]);

        return back()->with('message', 'Meeting scheduled successfully.');
    }

    public function update(Request $request, SecretaryMeeting $meeting)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'meeting_date' => 'sometimes|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'venue' => 'nullable|string|max:255',
            'organizer' => 'nullable|string|max:255',
            'attendees' => 'nullable|string',
            'agenda' => 'nullable|string',
            'status' => 'sometimes|in:scheduled,ongoing,done,cancelled',
            'minutes' => 'nullable|string',
        ]);

        $meeting->update($validated);

        return back()->with('message', 'Meeting updated successfully.');
    }

    public function destroy(SecretaryMeeting $meeting)
    {
        $meeting->delete();

        return back()->with('message', 'Meeting removed.');
    }
}
