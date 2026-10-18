<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Crm\CrmContact;
use App\Models\Crm\CrmMeetingRequest;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmPipelineNote;
use App\Models\Crm\CrmStage;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Odoo-style pipeline board data provider.
 * Keeps the legacy `crm.opportunities` route/permission names so the
 * sidebar, access control and audit trail keep working.
 */
class PipelineController extends Controller
{
    public static function boardProps(): array
    {
        // Column order is driven by the default probability (lowest first), so
        // e.g. a 10% "New" column sits ahead of a 20% one. Won/Lost are pinned
        // to the far right regardless of their probability.
        $stages = CrmStage::withCount(['opportunities' => fn ($q) => $q->whereNull('archived_at')])
            ->orderByRaw('(CASE WHEN is_won = 1 THEN 1 WHEN is_lost = 1 THEN 2 ELSE 0 END)')
            ->orderBy('default_probability')
            ->orderBy('sequence')
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'sequence' => $s->sequence,
                'is_folded' => (bool) $s->is_folded,
                'is_won' => (bool) $s->is_won,
                'is_lost' => (bool) $s->is_lost,
                'allows_uploads' => (bool) $s->allows_uploads,
                'default_probability' => (int) $s->default_probability,
                'opportunities_count' => $s->opportunities_count,
            ])
            ->values();

        $mapOpp = function ($o) {
            $next = $o->activities->first();
            $contactName = $o->contact?->name
                ?? $o->client?->company_name
                ?? $o->lead?->company_name
                ?? '—';
            $org = $o->contact?->organization ?? $o->client?->company_name ?? $o->lead?->company_name;

            // Every planned meeting/call/task on the card, so several can be
            // scheduled side by side instead of only the next one showing.
            $scheduled = $o->activities
                ->map(function ($a) {
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
                })
                ->values();

            $documents = $o->documents
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->original_name,
                    'url' => $d->file_url,
                    'mime' => $d->mime,
                    'size' => (int) $d->size,
                    'is_image' => $d->is_image,
                    'uploaded_by' => $d->uploader?->name,
                    'created_at' => $d->created_at?->toIso8601String(),
                ])
                ->values();

            return [
                'id' => $o->id,
                'title' => $o->title,
                'stage_id' => $o->stage_id,
                'stage' => $o->stage,
                'contact_id' => $o->contact_id,
                'client_id' => $o->client_id,
                'contact_name' => $contactName,
                'organization' => $org,
                'email' => $o->email ?? $o->contact?->email ?? $o->client?->email ?? $o->lead?->email,
                'phone' => $o->phone ?? $o->contact?->phone,
                'value' => (float) $o->value,
                'probability' => (int) $o->probability,
                'priority' => (int) ($o->priority ?? 0),
                'expected_close' => $o->expected_close,
                'owner_id' => $o->owner_id,
                'owner' => $o->owner?->only('id', 'name'),
                'activity_state' => $o->activity_state,
                'next_activity' => $next ? [
                    'id' => $next->id,
                    'type' => $next->type,
                    'summary' => $next->summary ?? $next->subject,
                    'due' => ($next->due_date ?? $next->due_at)?->toDateTimeString(),
                    'status' => $next->status,
                    'assigned_to' => $next->assigned_to,
                ] : null,
                'scheduled' => $scheduled,
                'documents' => $documents,
                'internal_notes' => $o->internal_notes,
                'source' => $o->source,
                'medium' => $o->medium,
                'campaign' => $o->campaign,
                'referred_by' => $o->referred_by,
                'lost_reason' => $o->lost_reason,
                'archived_at' => $o->archived_at?->toDateTimeString(),
                'updated_at' => $o->updated_at,
                'created_at' => $o->created_at,
            ];
        };

        $oppColumns = [
            'contact:id,name,organization,email,phone,client_id',
            'client:id,company_name,email',
            'lead:id,company_name,email',
            'owner:id,name',
            'documents.uploader:id,name',
            'activities' => fn ($q) => $q
                ->where('status', 'planned')
                ->with(['owner:id,name', 'assignee:id,name', 'meetingRequests:id,activity_id,status'])
                ->orderByRaw('due_date IS NULL, due_at IS NULL, COALESCE(due_date, due_at) ASC'),
        ];

        // Converted deals (now official client accounts) live in Accounts, not on the board.
        $opps = CrmOpportunity::with($oppColumns)
            ->whereNull('client_id')
            ->whereNull('archived_at')
            ->latest('updated_at')
            ->get()
            ->map($mapOpp)
            ->values();

        // Totally-failed deals live in the Archives tab only.
        $archived = CrmOpportunity::with($oppColumns)
            ->whereNotNull('archived_at')
            ->latest('archived_at')
            ->get()
            ->map($mapOpp)
            ->values();

        $contacts = CrmContact::with('client:id,company_name')
            ->orderBy('name')
            ->get(['id', 'name', 'organization', 'email', 'phone', 'client_id'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'organization' => $c->organization ?? $c->client?->company_name,
                'email' => $c->email,
                'phone' => $c->phone,
            ])
            ->values();

        $salespeople = User::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values();

        // Shared progress log (floating Notes panel). Newest first, capped.
        $notes = CrmPipelineNote::with('author:id,name')
            ->latest()
            ->take(100)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'body' => $n->body,
                'author_id' => $n->user_id,
                'author' => $n->author?->name ?? 'Unknown',
                'created_at' => $n->created_at?->toIso8601String(),
            ])
            ->values();

        // Client feedback from meeting/call request emails (floating panel).
        $feedback = CrmMeetingRequest::with(['opportunity:id,title', 'requestedBy:id,name'])
            ->latest('id')
            ->take(100)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'opportunity_id' => $r->opportunity_id,
                'opportunity_title' => $r->opportunity?->title,
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

        return [
            'stages' => $stages,
            'opportunities' => $opps,
            'archived' => $archived,
            'contacts' => $contacts,
            'salespeople' => $salespeople,
            'notes' => $notes,
            'feedback' => $feedback,
            'currentUserId' => Auth::id(),
        ];
    }

    public function index()
    {
        return Inertia::render('Dashboard/CRM/Opportunities', self::boardProps());
    }

    public function show(CrmOpportunity $opportunity)
    {
        $opportunity->load([
            'contact', 'client.contacts', 'lead', 'owner:id,name',
            'stageRef', 'histories.changer:id,name',
            'activities' => fn ($q) => $q->with('owner:id,name')->latest(),
        ]);

        $next = $opportunity->activities->where('status', 'planned')->sortBy(fn ($a) => $a->due_date ?? $a->due_at)->first();

        return Inertia::render('Dashboard/CRM/OpportunityShow', array_merge(self::boardProps(), [
            'opportunity' => array_merge($opportunity->toArray(), [
                'activity_state' => $opportunity->activity_state,
                'contact_name' => $opportunity->contact?->name
                    ?? $opportunity->client?->company_name
                    ?? $opportunity->lead?->company_name,
                'next_activity' => $next ? [
                    'id' => $next->id,
                    'type' => $next->type,
                    'summary' => $next->summary ?? $next->subject,
                    'due' => ($next->due_date ?? $next->due_at)?->toDateTimeString(),
                    'status' => $next->status,
                ] : null,
            ]),
        ]));
    }
}
