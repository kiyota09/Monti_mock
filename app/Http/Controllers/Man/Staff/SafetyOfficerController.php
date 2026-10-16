<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\SafetyIncident;
use App\Support\NotifiesExecutive;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SafetyOfficerController extends ManufacturingStaffController
{
    public function index()
    {
        $recentIncidents = SafetyIncident::where('operator_id', $this->staff()->id)
            ->latest()
            ->take(10)
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/SafetyOfficer/Index', [
            'stats' => [
                'open_incidents' => SafetyIncident::whereIn('status', ['open', 'investigating'])->count(),
                'reported_today' => SafetyIncident::whereDate('processed_at', today())->count(),
                'closed_total' => SafetyIncident::where('status', 'closed')->count(),
            ],
            'recentIncidents' => $recentIncidents,
            'efficiency' => $this->staffEfficiency(SafetyIncident::class),
        ]);
    }

    /**
     * Incident & near-miss reporting work page.
     */
    public function incidents()
    {
        $incidents = SafetyIncident::with('operator')
            ->latest()
            ->take(50)
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/SafetyOfficer/Incidents', [
            'incidents' => $incidents,
        ]);
    }

    public function storeIncident(Request $request)
    {
        $validated = $request->validate([
            'incident_type' => 'required|in:injury,near_miss,fire,chemical_spill,equipment,other',
            'location' => 'nullable|string|max:255',
            'department' => 'nullable|in:knitting,dyeing,finishing,maintenance,warehouse,other',
            'incident_date' => 'required|date',
            'severity' => 'required|in:minor,moderate,major,critical',
            'description' => 'required|string',
            'corrective_action' => 'nullable|string',
        ]);

        SafetyIncident::create([
            ...$validated,
            'code' => $this->generateCode('SAFE', SafetyIncident::class),
            'status' => 'open',
            'operator_id' => $this->staff()->id,
            'shift' => $this->getShift(),
            'processed_at' => now(),
        ]);

        if (in_array($validated['severity'], ['major', 'critical'], true)) {
            NotifiesExecutive::push('incident', ucfirst($validated['severity']) . ' safety incident filed', ($validated['incident_type'] ?? 'incident') . ' at ' . ($validated['location'] ?? 'plant') . ' — immediate executive attention.', 'ceo.approvals');
        }

        return redirect()->back()->with('message', 'Incident report filed successfully.');
    }

    /**
     * Advance incident workflow (forward-only).
     */
    public function updateStatus(Request $request, SafetyIncident $incident)
    {
        $validated = $request->validate([
            'status' => 'required|in:investigating,closed',
            'corrective_action' => 'nullable|string',
        ]);

        $order = ['open' => 0, 'investigating' => 1, 'closed' => 2];
        if ($order[$validated['status']] < $order[$incident->status]) {
            return back()->withErrors(['status' => 'Incidents cannot move backwards.']);
        }

        $incident->update([
            'status' => $validated['status'],
            'corrective_action' => $validated['corrective_action'] ?? $incident->corrective_action,
        ]);

        return redirect()->back()->with('message', "Incident {$incident->code} marked as {$validated['status']}.");
    }

    /**
     * Personal work log — own incident reports only.
     */
    public function history()
    {
        return Inertia::render('Dashboard/MAN/Employee/Common/History', [
            'roleLabel' => 'Safety Officer',
            'historyRoute' => 'man.staff.safety-officer.history',
            'dateColumn' => 'processed_at',
            'jobs' => $this->staffHistory(SafetyIncident::class),
        ]);
    }
}
