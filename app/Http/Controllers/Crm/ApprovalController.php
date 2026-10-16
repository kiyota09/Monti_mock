<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Client;
use App\Models\Crm\CrmMeeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Traits\HasPagePermissions;

class ApprovalController extends Controller
{
    use HasPagePermissions;

    public function index()
    {
        $pendingClients = Client::where('status', 'pending')->with(['meetings', 'logo'])->get();

        $permissions = $this->getPagePermissionsForModule('CRM');

        return Inertia::render('Dashboard/CRM/Approval', [
            'pendingClients' => $pendingClients,
            'permissions' => $permissions,
        ]);
    }

    public function addNote(Request $request, $id)
    {
        $request->validate(['note' => 'required|string']);

        $client = Client::findOrFail($id);
        $existing = $client->notes ? $client->notes . "\n" : '';
        $client->update(['notes' => $existing . $request->note]);

        return back()->with('message', 'Note added.');
    }

    public function scheduleMeeting(Request $request, $id)
    {
        $validated = $request->validate([
            'scheduled_at' => 'required|date',
            'meeting_type' => 'required|string',
            'location'     => 'nullable|string',
            'notes'        => 'nullable|string',
        ]);

        CrmMeeting::create([
            'client_id'    => $id,
            'scheduled_at' => $validated['scheduled_at'],
            'meeting_type' => $validated['meeting_type'],
            'location'     => $validated['location'],
            'notes'        => $validated['notes'],
            'created_by'   => Auth::id(),
            'status'       => 'scheduled',
        ]);

        return back()->with('message', 'Meeting scheduled.');
    }

    public function updateMeetingStatus(Request $request, $meetingId)
    {
        $meeting = CrmMeeting::findOrFail($meetingId);
        $meeting->update(['status' => $request->status]);
        return back()->with('message', 'Meeting status updated.');
    }

    public function accept($id)
    {
        $client = Client::findOrFail($id);
        $client->update(['status' => 'active']);
        return back()->with('message', 'Client approved and now active.');
    }

    public function reject(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string']);
        $client = Client::findOrFail($id);
        $client->update(['status' => 'rejected', 'rejection_reason' => $request->reason]);
        return back()->with('message', 'Client rejected.');
    }
}