<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Client;
use App\Models\Crm\CrmCase;
use App\Models\Crm\CrmClientAssignment;
use App\Models\Crm\CrmFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CaseController extends Controller
{
    protected function scopeForUser($query)
    {
        $user = Auth::user();
        if ($user->role === 'CRM' && $user->position === 'staff') {
            $ids = CrmClientAssignment::where('staff_id', $user->id)->pluck('client_id');
            $query->whereIn('client_id', $ids);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $status = $request->get('status', 'open');

        $query = CrmCase::with(['client:id,company_name', 'owner:id,name']);
        $this->scopeForUser($query);
        if (in_array($status, CrmCase::STATUSES, true)) {
            $query->where('status', $status);
        }

        $clients = Client::where('status', 'active')->orderBy('company_name')->get(['id', 'company_name']);
        $complaints = CrmFeedback::with('client:id,company_name')
            ->where('type', 'complaint')->where('status', 'open')
            ->latest()->take(20)->get();

        return Inertia::render('Dashboard/CRM/Cases', [
            'status' => $status,
            'cases' => $query->latest()->take(150)->get(),
            'counts' => [
                'open' => CrmCase::where('status', 'open')->count(),
                'in_progress' => CrmCase::where('status', 'in_progress')->count(),
                'urgent' => CrmCase::whereIn('status', ['open', 'in_progress'])->where('severity', 'urgent')->count(),
            ],
            'clients' => $clients,
            'complaints' => $complaints,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'feedback_id' => 'nullable|exists:crm_feedback,id',
            'subject' => 'required|string|max:255',
            'category' => 'required|in:defect,shortage,delay,billing,other',
            'severity' => 'required|in:low,medium,high,urgent',
        ]);

        CrmCase::create([...$data, 'status' => 'open', 'owner_id' => Auth::id()]);

        return back()->with('message', 'Case opened.');
    }

    public function status(Request $request, CrmCase $case)
    {
        $data = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        $case->update([
            'status' => $data['status'],
            'resolution_notes' => $data['resolution_notes'] ?? $case->resolution_notes,
            'resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? now() : null,
        ]);

        return back()->with('message', "Case marked {$data['status']}.");
    }
}
