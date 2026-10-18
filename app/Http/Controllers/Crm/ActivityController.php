<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Mail\ActivityCancelledMail;
use App\Mail\ActivityScheduledMail;
use App\Mail\MeetingRequestMail;
use App\Models\Crm\Client;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmClientAssignment;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmMeetingRequest;
use App\Models\Crm\CrmOpportunity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class ActivityController extends Controller
{
    protected function scopeForUser($query)
    {
        $user = Auth::user();
        if ($user->role === 'CRM' && $user->position === 'staff') {
            $ids = CrmClientAssignment::where('staff_id', $user->id)->pluck('client_id');
            $query->where(function ($q) use ($ids, $user) {
                $q->whereIn('client_id', $ids)->orWhere('owner_id', $user->id);
            });
        }

        return $query;
    }

    public function index(Request $request)
    {
        $filter = $request->get('filter', 'open');

        $query = CrmActivity::with(['client:id,company_name', 'opportunity:id,title', 'lead:id,company_name', 'owner:id,name', 'assignee:id,name']);
        $this->scopeForUser($query);

        if ($filter === 'open') {
            $query->whereNull('done_at');
        } elseif ($filter === 'overdue') {
            $query->whereNull('done_at')->where('due_at', '<', now());
        } elseif ($filter === 'done') {
            $query->whereNotNull('done_at');
        }

        $activities = $query->latest()->take(150)->get()
            ->map(fn ($a) => array_merge($a->toArray(), ['is_overdue' => $a->is_overdue]));

        $clients = Client::where('status', 'active')->orderBy('company_name')->get(['id', 'company_name']);

        return Inertia::render('Dashboard/CRM/Activities', [
            'filter' => $filter,
            'activities' => $activities,
            'openCount' => CrmActivity::whereNull('done_at')->count(),
            'overdueCount' => CrmActivity::whereNull('done_at')->where('due_at', '<', now())->count(),
            'clients' => $clients,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'opportunity_id' => 'nullable|exists:crm_opportunities,id',
            'lead_id' => 'nullable|exists:crm_leads,id',
            'type' => 'required|in:call,meeting,reminder,todo,note,task,email',
            'subject' => 'nullable|string|max:255',
            'summary' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:4000',
            'notes' => 'nullable|string|max:4000',
            'meeting_link' => 'nullable|string|max:500',
            'due_at' => 'nullable|date',
            'due_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
            'mark_done' => 'nullable|boolean',
        ]);
        if (empty($data['client_id']) && empty($data['opportunity_id']) && empty($data['lead_id'])) {
            return back()->withErrors(['error' => 'Attach the activity to an account, deal or lead.'])->withInput();
        }
        // Won/Lost deals are closed: they only wait on "create account" / "archive".
        if (! empty($data['opportunity_id'])) {
            $stage = CrmOpportunity::with('stageRef')->find($data['opportunity_id'])?->stageRef;
            if ($stage && $stage->isTerminal()) {
                return back()->withErrors(['error' => 'This deal is closed — activities can no longer be scheduled.'])->withInput();
            }
        }
        if (empty($data['subject']) && empty($data['summary'])) {
            return back()->withErrors(['summary' => 'Give the activity a summary.'])->withInput();
        }

        $summary = $data['summary'] ?? $data['subject'];
        $due = $data['due_date'] ?? $data['due_at'] ?? null;
        $done = ! empty($data['mark_done']);

        $activity = CrmActivity::create([
            'client_id' => $data['client_id'] ?? null,
            'opportunity_id' => $data['opportunity_id'] ?? null,
            'lead_id' => $data['lead_id'] ?? null,
            'type' => $data['type'],
            'subject' => $summary,
            'summary' => $summary,
            'body' => $data['notes'] ?? $data['body'] ?? null,
            'notes' => $data['notes'] ?? $data['body'] ?? null,
            'meeting_link' => $data['meeting_link'] ?? null,
            'due_at' => $due,
            'due_date' => $due,
            'done_at' => $done ? now() : null,
            'status' => $done ? 'done' : 'planned',
            'owner_id' => Auth::id(),
            'assigned_to' => $data['assigned_to'] ?? Auth::id(),
        ]);

        // Call/Meeting → email a meeting REQUEST the client must confirm.
        // Other types → email the scheduled details directly. Best-effort: a
        // mail hiccup must never block the activity from being saved.
        $isRequest = in_array($activity->type, ['call', 'meeting'], true);
        if (! $done && $isRequest) {
            $emailedTo = $this->sendMeetingRequest($activity);
        } else {
            $emailedTo = $this->emailScheduledDetails($activity);
        }

        $base = $done ? 'Activity logged as done.' : ($isRequest ? 'Meeting request sent.' : 'Activity scheduled.');

        return back()->with('message', $emailedTo ? "{$base} Emailed to {$emailedTo}." : $base);
    }

    /** Resolve the client-facing recipient for an activity. */
    protected function resolveRecipient(CrmActivity $activity): ?array
    {
        $opp = $activity->opportunity_id
            ? CrmOpportunity::with(['contact', 'client', 'lead'])->find($activity->opportunity_id)
            : null;

        $recipient = null;
        $name = null;
        $company = null;

        if ($opp) {
            $company = $opp->client?->company_name
                ?? $opp->lead?->company_name
                ?? $opp->contact?->organization
                ?? $opp->title;
            $recipient = $opp->contact?->email;
            $name = $opp->contact?->name;
            if (! $recipient) {
                $recipient = $opp->client?->email;
                $name ??= $opp->client?->contact_person;
            }
            $recipient ??= $opp->email;
            $recipient ??= $opp->lead?->email;
        } elseif ($activity->client_id) {
            $client = Client::find($activity->client_id);
            $company = $client?->company_name;
            $recipient = $client?->email;
            $name = $client?->contact_person;
        } elseif ($activity->lead_id) {
            $lead = CrmLead::find($activity->lead_id);
            $company = $lead?->company_name;
            $recipient = $lead?->email;
            $name = $lead?->contact_person;
        }

        if (! $recipient || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return [
            'email' => $recipient,
            'name' => $name ?: ($company ?: 'Valued Client'),
            'company' => $company,
            'title' => $opp?->title,
        ];
    }

    /** Email the confirmed call/meeting details (non-request types). */
    protected function emailScheduledDetails(CrmActivity $activity): ?string
    {
        $ctx = $this->resolveRecipient($activity);
        if (! $ctx) {
            return null;
        }

        $due = $activity->due_date ?? $activity->due_at;

        try {
            Mail::to($ctx['email'])->send(new ActivityScheduledMail(
                recipientName: $ctx['name'],
                companyName: $ctx['company'],
                opportunityTitle: $ctx['title'],
                type: $activity->type,
                summary: $activity->summary ?? $activity->subject,
                dueAt: $due ? $due->format('l, F j, Y \a\t g:i A') : null,
                meetingLink: $activity->meeting_link,
                notes: $activity->notes,
                ownerName: Auth::user()?->name,
            ));

            return $ctx['email'];
        } catch (\Throwable $e) {
            Log::warning('Activity scheduled email failed for activity #' . $activity->id . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Create a meeting request for a call/meeting and email the client the
     * confirm / reschedule / decline links. Returns the recipient address or
     * null when there is no usable email (or the send failed).
     */
    protected function sendMeetingRequest(CrmActivity $activity): ?string
    {
        $ctx = $this->resolveRecipient($activity);
        if (! $ctx) {
            return null;
        }

        $request = CrmMeetingRequest::create([
            'opportunity_id' => $activity->opportunity_id,
            'activity_id' => $activity->id,
            'client_id' => $activity->client_id,
            'lead_id' => $activity->lead_id,
            'requested_by' => Auth::id(),
            'type' => $activity->type,
            'summary' => $activity->summary ?? $activity->subject,
            'meeting_link' => $activity->meeting_link,
            'notes' => $activity->notes,
            'proposed_at' => $activity->due_date ?? $activity->due_at,
            'status' => 'pending',
            'recipient_name' => $ctx['name'],
            'recipient_email' => $ctx['email'],
        ]);

        $due = $request->proposed_at;

        try {
            Mail::to($ctx['email'])->send(new MeetingRequestMail(
                recipientName: $ctx['name'],
                companyName: $ctx['company'],
                opportunityTitle: $ctx['title'],
                type: $request->type,
                summary: $request->summary,
                proposedAt: $due ? $due->format('l, F j, Y \a\t g:i A') : null,
                meetingLink: $request->meeting_link,
                notes: $request->notes,
                ownerName: Auth::user()?->name,
                respondUrl: route('meeting-request.show', $request->token),
            ));

            return $ctx['email'];
        } catch (\Throwable $e) {
            Log::warning('Meeting request email failed for request #' . $request->id . ': ' . $e->getMessage());

            return null;
        }
    }

    /** Email the client that a scheduled meeting/call has been cancelled. */
    protected function emailCancellation(CrmActivity $activity, string $reason): ?string
    {
        $ctx = $this->resolveRecipient($activity);
        if (! $ctx) {
            return null;
        }

        $due = $activity->due_date ?? $activity->due_at;

        try {
            Mail::to($ctx['email'])->send(new ActivityCancelledMail(
                recipientName: $ctx['name'],
                companyName: $ctx['company'],
                opportunityTitle: $ctx['title'],
                type: $activity->type,
                summary: $activity->summary ?? $activity->subject,
                wasAt: $due ? $due->format('l, F j, Y \a\t g:i A') : null,
                meetingLink: $activity->meeting_link,
                reason: $reason,
                ownerName: Auth::user()?->name,
            ));

            return $ctx['email'];
        } catch (\Throwable $e) {
            Log::warning('Activity cancellation email failed for activity #' . $activity->id . ': ' . $e->getMessage());

            return null;
        }
    }

    public function done(CrmActivity $activity)
    {
        $activity->update(['done_at' => now(), 'status' => 'done']);

        return back()->with('message', 'Marked done.');
    }

    /** Reopen an activity that was marked done (or cancelled). */
    public function undone(CrmActivity $activity)
    {
        $activity->update(['done_at' => null, 'status' => 'planned']);

        return back()->with('message', 'Marked as not done.');
    }

    /**
     * Reschedule a specific activity: move its due date (optionally tweak the
     * summary / link / notes), then re-issue a meeting request so the client
     * confirms the new slot. Any still-pending request for it is superseded.
     */
    public function update(Request $request, CrmActivity $activity)
    {
        $data = $request->validate([
            'summary' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:4000',
            'meeting_link' => 'nullable|string|max:500',
            'due_date' => 'nullable|date',
            'reason' => 'nullable|string|max:1000',
        ]);

        $activity->update([
            'subject' => $data['summary'] ?? $activity->subject,
            'summary' => $data['summary'] ?? $activity->summary,
            'body' => $data['notes'] ?? $activity->body,
            'notes' => $data['notes'] ?? $activity->notes,
            'meeting_link' => $data['meeting_link'] ?? $activity->meeting_link,
            'due_at' => $data['due_date'] ?? $activity->due_at,
            'due_date' => $data['due_date'] ?? $activity->due_date,
            'status' => 'planned',
            'done_at' => null,
        ]);

        // Supersede any request the client hasn't answered yet — a new one is
        // about to replace it, so stale links shouldn't linger in feedback.
        CrmMeetingRequest::where('activity_id', $activity->id)->where('status', 'pending')->delete();

        $isRequest = in_array($activity->type, ['call', 'meeting'], true);
        $emailedTo = $isRequest ? $this->sendMeetingRequest($activity) : null;

        return back()->with('message', $emailedTo
            ? "New {$activity->type} request emailed to {$emailedTo}."
            : 'Schedule updated.');
    }

    /**
     * Cancel a specific meeting/call with a reason and notify the client.
     * Called from the card's meeting-detail modal.
     */
    public function cancel(Request $request, CrmActivity $activity)
    {
        $data = $request->validate(['reason' => 'required|string|max:1000']);

        $activity->update(['status' => 'cancelled', 'done_at' => null]);
        CrmMeetingRequest::where('activity_id', $activity->id)->where('status', 'pending')->delete();

        $emailedTo = $this->emailCancellation($activity, $data['reason']);

        return back()->with('message', $emailedTo
            ? "Meeting cancelled — notice emailed to {$emailedTo}."
            : 'Meeting cancelled.');
    }

    public function destroy(CrmActivity $activity)
    {
        $activity->delete();

        return back()->with('message', 'Activity removed.');
    }
}
