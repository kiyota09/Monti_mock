<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmCampaign;
use App\Models\Crm\CrmLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = CrmCampaign::withCount('leads')->with('creator:id,name')
            ->latest()->get()
            ->map(fn ($c) => array_merge($c->toArray(), [
                'leads_count' => $c->leads_count,
                'cost_per_lead' => $c->leads_count > 0 ? round(((float) $c->cost) / $c->leads_count, 2) : null,
            ]));

        $openLeads = CrmLead::whereNotIn('status', ['Converted', 'Archived', 'Lost'])
            ->orderBy('company_name')->get(['id', 'company_name']);

        return Inertia::render('Dashboard/CRM/Campaigns', [
            'campaigns' => $campaigns,
            'openLeads' => $openLeads,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'channel' => 'required|in:social,email,tradeshow,referral',
            'audience' => 'nullable|string|max:255',
            'cost' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'status' => 'required|in:draft,active,completed',
        ]);

        CrmCampaign::create([...$data, 'cost' => $data['cost'] ?? 0, 'created_by' => Auth::id()]);

        return back()->with('message', 'Campaign saved.');
    }

    public function attachLead(Request $request, CrmCampaign $campaign)
    {
        $data = $request->validate(['lead_id' => 'required|exists:crm_leads,id']);
        $campaign->leads()->syncWithoutDetaching([$data['lead_id']]);

        return back()->with('message', 'Lead attributed to campaign.');
    }

    public function status(Request $request, CrmCampaign $campaign)
    {
        $data = $request->validate(['status' => 'required|in:draft,active,completed']);
        $campaign->update($data);

        return back()->with('message', "Campaign marked {$data['status']}.");
    }
}
