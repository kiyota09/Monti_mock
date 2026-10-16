<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Client;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmCase;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmFeedback;
use App\Models\Crm\CrmMeeting;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmClientAssignment;
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
        if (!in_array($user->role, ['CRM', 'CEO', 'COO'], true)) {
            abort(403, 'Unauthorized access.');
        }

        $stats = [
            'total_clients' => Client::where('status', 'active')->count(),
            'pending_clients' => Client::where('status', 'pending')->count(),
            'total_leads' => CrmLead::count(),
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
        $wonLeads = CrmLead::where('status', 'Closed-Won')->count();
        $funnel = [
            'leads' => $leadsTotal,
            'qualified' => CrmLead::whereNotNull('budget')->orWhereNotNull('next_step')->count(),
            'open_deals' => $pipeline['deals'],
            'won' => $wonDeals + $wonLeads,
            'win_rate' => $leadsTotal > 0 ? round(($wonDeals + $wonLeads) / $leadsTotal * 100, 1) : 0,
        ];

        // Avg sales cycle: days from deal creation to won.
        $won = CrmOpportunity::where('stage', 'won')->get(['created_at', 'updated_at']);
        $cycle = $won->count()
            ? round($won->avg(fn ($o) => $o->created_at->diffInDays($o->updated_at, false) ?? 0), 1)
            : null;

        $service = [
            'open_cases' => CrmCase::whereIn('status', ['open', 'in_progress'])->count(),
            'urgent_cases' => CrmCase::whereIn('status', ['open', 'in_progress'])->where('severity', 'urgent')->count(),
            'overdue_tasks' => CrmActivity::whereNull('done_at')->where('due_at', '<', now())->count(),
        ];

        // If user is staff and has assigned clients for investigation, show only those
        if ($user->position === 'staff') {
            $assignedClientIds = CrmClientAssignment::where('staff_id', $user->id)->pluck('client_id');
            $recentFeedback = CrmFeedback::whereIn('client_id', $assignedClientIds)->latest()->take(5)->get();
        } else {
            $recentFeedback = CrmFeedback::latest()->take(5)->get();
        }

        $permissions = $this->getPagePermissionsForModule('CRM');

        return Inertia::render('Dashboard/CRM/CRMDashboard', [
            'stats' => $stats,
            'pipeline' => $pipeline,
            'funnel' => $funnel,
            'avgCycle' => $cycle,
            'service' => $service,
            'recentFeedback' => $recentFeedback,
            'permissions' => $permissions,
        ]);
    }
}