<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Crm\Client;
use App\Models\Crm\CrmMeeting;
use App\Models\Crm\CrmMeetingRequest;
use App\Models\Crm\CrmOpportunityDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Traits\HasPagePermissions;

class ApprovalController extends Controller
{
    use HasPagePermissions;

    public function index()
    {
        $pendingClients = Client::where('status', 'pending')
            ->with([
                'meetings',
                'logo',
                'documents.uploader:id,name',
                'activities' => fn ($q) => $q
                    ->where('status', 'planned')
                    ->with(['owner:id,name', 'assignee:id,name', 'meetingRequests:id,activity_id,status'])
                    ->orderByRaw('due_date IS NULL, due_at IS NULL, COALESCE(due_date, due_at) ASC'),
            ])
            ->get();

        // Full registration payload for each card, plus the files/images and
        // scheduled calls/meetings that were added to that client.
        $clients = $pendingClients->map(function (Client $client) {
            return array_merge($client->toArray(), [
                'documents' => $client->documents->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->original_name,
                    'url' => $d->file_url,
                    'mime' => $d->mime,
                    'size' => (int) $d->size,
                    'is_image' => $d->is_image,
                    'uploaded_by' => $d->uploader?->name,
                    'created_at' => $d->created_at?->toIso8601String(),
                ])->values(),
                'scheduled' => $client->activities->map(function ($a) {
                    $latest = $a->meetingRequests->first();
                    $due = $a->due_date ?? $a->due_at;

                    return [
                        'id' => $a->id,
                        'type' => $a->type,
                        'summary' => $a->summary ?? $a->subject,
                        'due' => $due?->toDateTimeString(),
                        'status' => $a->status,
                        'meeting_link' => $a->meeting_link,
                        'notes' => $a->notes,
                        'assigned_to' => $a->assigned_to,
                        'assigned_to_name' => $a->assignee?->name,
                        'owner_name' => $a->owner?->name,
                        'request_status' => $latest?->status,
                    ];
                })->values(),
            ]);
        })->values();

        // Client answers to the call/meeting requests sent from this board.
        $feedback = CrmMeetingRequest::with(['client:id,company_name', 'requestedBy:id,name'])
            ->whereIn('client_id', $pendingClients->pluck('id'))
            ->latest('id')
            ->take(100)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'client_id' => $r->client_id,
                'client_name' => $r->client?->company_name,
                'opportunity_id' => $r->opportunity_id,
                'opportunity_title' => $r->client?->company_name,
                'type' => $r->type,
                'summary' => $r->summary,
                'meeting_link' => $r->meeting_link,
                'proposed_at' => $r->proposed_at?->toIso8601String(),
                'status' => $r->status,
                'recipient_name' => $r->recipient_name,
                'response_place' => $r->response_place,
                'response_message' => $r->response_message,
                'preferred_at' => $r->preferred_at?->toIso8601String(),
                'responded_at' => $r->responded_at?->toIso8601String(),
                'requested_by' => $r->requestedBy?->name,
                'created_at' => $r->created_at?->toIso8601String(),
            ])
            ->values();

        $salespeople = User::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values();

        // Rejected registrations — shown on the page's "Rejected" tab with the
        // reason captured at rejection time.
        $rejectedClients = Client::where('status', 'rejected')
            ->with('logo')
            ->latest('updated_at')
            ->get();

        $permissions = $this->getPagePermissionsForModule('CRM');

        return Inertia::render('Dashboard/CRM/Approval', [
            'pendingClients' => $clients,
            'rejectedClients' => $rejectedClients,
            'feedback' => $feedback,
            'salespeople' => $salespeople,
            'currentUserId' => Auth::id(),
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

    /**
     * Request a call/meeting for a pending client. Delegates to the shared
     * ActivityController so the client gets the same meeting-request email and
     * the answer shows up in the feedback panel.
     */
    public function storeActivity(Request $request, $id)
    {
        $request->merge([
            'client_id' => $id,
            'opportunity_id' => null,
            'lead_id' => null,
        ]);

        return app(ActivityController::class)->store($request);
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

    /**
     * Upload documents/images directly against a pending client. Stored with
     * the shared opportunity-document model (deal reference is optional).
     */
    public function storeDocument(Request $request, $id)
    {
        $data = $request->validate([
            'files' => 'required|array|max:10',
            'files.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,png,jpg,jpeg,webp,gif,svg|max:10240',
        ]);

        $client = Client::findOrFail($id);
        $folder = 'crm-client-documents/' . $client->id;
        $saved = 0;

        foreach ($data['files'] as $file) {
            $path = $file->store($folder, 'public');

            CrmOpportunityDocument::create([
                'opportunity_id' => null,
                'client_id' => $client->id,
                'stage_id' => null,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
                'uploaded_by' => Auth::id(),
            ]);

            $saved++;
        }

        return back()->with('message', $saved . ' file' . ($saved === 1 ? '' : 's') . ' uploaded.');
    }

    public function destroyDocument(CrmOpportunityDocument $document)
    {
        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return back()->with('message', 'File removed.');
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
