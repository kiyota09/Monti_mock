<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\It\ItSystem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MonitoringController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard/IT/Manager/Monitoring', [
            'systems' => ItSystem::with(['checks' => fn ($q) => $q->with('checker:id,name')->limit(5)])
                ->orderByRaw("FIELD(status, 'down', 'degraded', 'maintenance', 'operational')")
                ->orderBy('name')
                ->get(),
            'recentChecks' => \App\Models\It\ItSystemCheck::with(['system:id,name', 'checker:id,name'])
                ->latest()
                ->limit(15)
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'system_type' => 'required|in:server,network,application,power,security,plant_ot,endpoint',
            'location' => 'nullable|string|max:255',
            'host' => 'nullable|string|max:255',
            'status' => 'required|in:operational,degraded,down,maintenance',
            'notes' => 'nullable|string|max:2000',
        ]);

        $system = ItSystem::create([
            ...$data,
            'last_checked_at' => now(),
            'last_checked_by' => auth()->id(),
        ]);

        $system->checks()->create([
            'status' => $system->status,
            'checked_by' => auth()->id(),
            'notes' => 'Initial baseline recorded.',
        ]);

        return redirect()->back()->with('success', "System '{$system->name}' is now monitored.");
    }

    /**
     * Log a status check (any IT staff on rounds). Appends to the audit log.
     */
    public function check(Request $request, ItSystem $system)
    {
        $data = $request->validate([
            'status' => 'required|in:operational,degraded,down,maintenance',
            'notes' => 'nullable|string|max:1000',
        ]);

        $system->checks()->create([
            'status' => $data['status'],
            'checked_by' => auth()->id(),
            'notes' => $data['notes'] ?? null,
        ]);

        $system->update([
            'status' => $data['status'],
            'last_checked_at' => now(),
            'last_checked_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', "Status for '{$system->name}' logged as {$data['status']}.");
    }

    public function destroy(ItSystem $system)
    {
        $system->delete();

        return redirect()->back()->with('success', 'Monitored system removed.');
    }
}
