<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Client;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmClientAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $query = CrmActivity::with(['client:id,company_name', 'opportunity:id,title', 'lead:id,company_name', 'owner:id,name']);
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
            'due_at' => 'nullable|date',
            'due_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
            'mark_done' => 'nullable|boolean',
        ]);
        if (empty($data['client_id']) && empty($data['opportunity_id']) && empty($data['lead_id'])) {
            return back()->withErrors(['error' => 'Attach the activity to an account, deal or lead.'])->withInput();
        }
        if (empty($data['subject']) && empty($data['summary'])) {
            return back()->withErrors(['summary' => 'Give the activity a summary.'])->withInput();
        }

        $summary = $data['summary'] ?? $data['subject'];
        $due = $data['due_date'] ?? $data['due_at'] ?? null;
        $done = ! empty($data['mark_done']);

        CrmActivity::create([
            'client_id' => $data['client_id'] ?? null,
            'opportunity_id' => $data['opportunity_id'] ?? null,
            'lead_id' => $data['lead_id'] ?? null,
            'type' => $data['type'],
            'subject' => $summary,
            'summary' => $summary,
            'body' => $data['notes'] ?? $data['body'] ?? null,
            'notes' => $data['notes'] ?? $data['body'] ?? null,
            'due_at' => $due,
            'due_date' => $due,
            'done_at' => $done ? now() : null,
            'status' => $done ? 'done' : 'planned',
            'owner_id' => Auth::id(),
            'assigned_to' => $data['assigned_to'] ?? Auth::id(),
        ]);

        return back()->with('message', $done ? 'Activity logged as done.' : 'Activity scheduled.');
    }

    public function done(CrmActivity $activity)
    {
        $activity->update(['done_at' => now(), 'status' => 'done']);

        return back()->with('message', 'Marked done.');
    }

    public function cancel(CrmActivity $activity)
    {
        $activity->update(['status' => 'cancelled']);

        return back()->with('message', 'Activity cancelled.');
    }

    public function destroy(CrmActivity $activity)
    {
        $activity->delete();

        return back()->with('message', 'Activity removed.');
    }
}
