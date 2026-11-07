<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Client;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmCase;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmFeedback;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmStage;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Traits\HasPagePermissions;

class CrmDashboardController extends Controller
{
    use HasPagePermissions;

    public function index()
    {
        $user = Auth::user();

        // Allow CRM users (any position) plus the President (CEO)
        // and the Vice President (COO / vice_president) — full access.
        // Route page.permission middleware is the source of truth for access.
        // Explicit IT grants may give this dashboard to an employee outside CRM.

        $stats = [
            'total_clients' => Client::where('status', 'active')->count(),
            'pending_clients' => Client::where('status', 'pending')->count(),
            'total_leads' => CrmLead::count(),
            'active_leads' => CrmLead::whereNotIn('status', ['Closed-Won', 'Lost', 'Converted', 'Archived'])->count(),
            'open_feedback' => CrmFeedback::where('status', 'open')->count(),
        ];

        // Pipeline + weighted forecast (open deals only).
        $openOpps = CrmOpportunity::whereNotIn('stage', ['won', 'lost'])->get();
        $pipeline = [
            'deals' => $openOpps->count(),
            'value' => round($openOpps->sum('value'), 2),
            'forecast' => round($openOpps->sum(fn ($o) => ((float) $o->value) * ((int) $o->probability) / 100), 2),
        ];

        // Funnel: leads in → qualified (has BANT data) → open deals → won.
        $leadsTotal = CrmLead::count();
        $wonDeals = CrmOpportunity::where('stage', 'won')->count();
        $lostDeals = CrmOpportunity::where('stage', 'lost')->count();
        $wonLeads = CrmLead::where('status', 'Closed-Won')->count();
        $funnel = [
            'leads' => $leadsTotal,
            'qualified' => CrmLead::whereNotNull('budget')->orWhereNotNull('next_step')->count(),
            'open_deals' => $pipeline['deals'],
            'won' => $wonDeals + $wonLeads,
            'win_rate' => ($wonDeals + $lostDeals) > 0
                ? round($wonDeals / ($wonDeals + $lostDeals) * 100, 1)
                : 0,
            'closed_deals' => $wonDeals + $lostDeals,
        ];

        // Avg sales cycle: days from deal creation to won.
        $won = CrmOpportunity::where('stage', 'won')->get(['created_at', 'updated_at']);
        $cycle = $won->count()
            ? round($won->avg(fn ($o) => $o->created_at->diffInDays($o->updated_at, false) ?? 0), 1)
            : null;

        $service = [
            'open_cases' => CrmCase::whereIn('status', ['open', 'in_progress'])->count(),
            'urgent_cases' => CrmCase::whereIn('status', ['open', 'in_progress'])->where('severity', 'urgent')->count(),
            'overdue_tasks' => CrmActivity::where('status', 'planned')->whereNull('done_at')->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('due_date')->where('due_date', '<', now());
                })->orWhere(function ($q) {
                    $q->whereNull('due_date')->whereNotNull('due_at')->where('due_at', '<', now());
                });
            })->count(),
        ];

        $openStages = CrmStage::pipelineOrder()->filter(fn ($stage) => ! $stage->is_won && ! $stage->is_lost);
        $stageSummary = $openStages->map(function ($stage) use ($openOpps) {
            $deals = $openOpps->where('stage_id', $stage->id);

            return [
                'id' => $stage->id,
                'name' => $stage->name,
                'count' => $deals->count(),
                'value' => round($deals->sum('value'), 2),
                'forecast' => round($deals->sum(fn ($deal) => (float) $deal->value * (int) $deal->probability / 100), 2),
            ];
        })->values();

        $upcomingActivities = CrmActivity::query()
            ->where('status', 'planned')
            ->whereNull('done_at')
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('due_date')->where('due_date', '<=', now()->addDays(7)->endOfDay());
                })->orWhere(function ($q) {
                    $q->whereNull('due_date')->whereNotNull('due_at')->where('due_at', '<=', now()->addDays(7)->endOfDay());
                });
            })
            ->with(['opportunity:id,title', 'lead:id,company_name', 'client:id,company_name', 'assignee:id,name'])
            ->orderByRaw('COALESCE(due_date, due_at) ASC')
            ->take(8)
            ->get()
            ->map(fn ($activity) => [
                'id' => $activity->id,
                'type' => $activity->type,
                'title' => $activity->title ?: ucfirst($activity->type),
                'due_at' => $activity->due?->toIso8601String(),
                'account' => $activity->client?->company_name
                    ?? $activity->lead?->company_name
                    ?? $activity->opportunity?->title
                    ?? 'Unlinked activity',
                'assignee' => $activity->assignee?->name,
                'overdue' => $activity->due?->isPast() ?? false,
            ])->values();

        $recentLeads = CrmLead::query()
            ->latest()
            ->take(6)
            ->get(['id', 'company_name', 'contact_person', 'status', 'source', 'estimated_value', 'created_at']);

        $permissions = $this->getPagePermissionsForModule('CRM');

        return Inertia::render('Dashboard/CRM/CRMDashboard', [
            'stats' => $stats,
            'pipeline' => $pipeline,
            'funnel' => $funnel,
            'avgCycle' => $cycle,
            'service' => $service,
            'stageSummary' => $stageSummary,
            'upcomingActivities' => $upcomingActivities,
            'recentLeads' => $recentLeads,
            'permissions' => $permissions,
        ]);
    }
}
