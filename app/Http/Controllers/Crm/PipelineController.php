<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Crm\CrmContact;
use App\Models\Crm\CrmOpportunity;
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
        $stages = CrmStage::withCount('opportunities')
            ->orderBy('sequence')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'sequence' => $s->sequence,
                'is_folded' => (bool) $s->is_folded,
                'is_won' => (bool) $s->is_won,
                'default_probability' => (int) $s->default_probability,
                'opportunities_count' => $s->opportunities_count,
            ])
            ->values();

        $opps = CrmOpportunity::with([
                'contact:id,name,organization,email,phone,client_id',
                'client:id,company_name',
                'lead:id,company_name',
                'owner:id,name',
                'activities' => fn ($q) => $q->where('status', 'planned')->orderByRaw('due_date IS NULL, due_at IS NULL, COALESCE(due_date, due_at) ASC'),
            ])
            ->latest('updated_at')
            ->get()
            ->map(function ($o) {
                $next = $o->activities->first();
                $contactName = $o->contact?->name
                    ?? $o->client?->company_name
                    ?? $o->lead?->company_name
                    ?? '—';
                $org = $o->contact?->organization ?? $o->client?->company_name ?? null;

                return [
                    'id' => $o->id,
                    'title' => $o->title,
                    'stage_id' => $o->stage_id,
                    'stage' => $o->stage,
                    'contact_id' => $o->contact_id,
                    'contact_name' => $contactName,
                    'organization' => $org,
                    'email' => $o->email ?? $o->contact?->email,
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
                    'internal_notes' => $o->internal_notes,
                    'source' => $o->source,
                    'medium' => $o->medium,
                    'campaign' => $o->campaign,
                    'referred_by' => $o->referred_by,
                    'updated_at' => $o->updated_at,
                    'created_at' => $o->created_at,
                ];
            })
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

        return [
            'stages' => $stages,
            'opportunities' => $opps,
            'contacts' => $contacts,
            'salespeople' => $salespeople,
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
