<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\Machine;
use App\Models\Man\MachineReport;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceCheckerController extends ManufacturingStaffController
{
    public function index()
    {
        $pendingReports = MachineReport::where('status', 'pending')->count();
        $resolvedToday = MachineReport::whereDate('resolved_at', today())->count();
        $staffId = $this->staff()->id;

        return Inertia::render('Dashboard/MAN/Employee/MaintenanceChecker/Index', [
            'stats' => [
                'pending_reports' => $pendingReports,
                'resolved_today' => $resolvedToday,
                'total_machines' => Machine::count(),
                'available_machines' => Machine::where('status', 'available')->count(),
            ],
            // NEW — personal efficiency (own resolutions only).
            'efficiency' => [
                'my_resolved_today' => MachineReport::where('resolved_by', $staffId)
                    ->whereDate('resolved_at', today())->count(),
                'my_resolved_week' => MachineReport::where('resolved_by', $staffId)
                    ->whereBetween('resolved_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'my_resolved_total' => MachineReport::where('resolved_by', $staffId)->count(),
            ],
        ]);
    }

    public function maintenance()
    {
        $machines = Machine::orderBy('type')->orderBy('machine_no')->get();

        return Inertia::render('Dashboard/MAN/Employee/MaintenanceChecker/Maintenance', [
            'machines' => $machines,
        ]);
    }

    public function storeMachine(Request $request)
    {
        $validated = $request->validate([
            'machine_no' => 'required|string|unique:machines,machine_no',
            'type' => 'required|string|in:knitting,dyeing,softening,squeezer,boiler',
            'remarks' => 'nullable|string',
        ]);

        Machine::create([
            'machine_no' => $validated['machine_no'],
            'type' => $validated['type'],
            'status' => 'available',
            'remarks' => $validated['remarks'],
        ]);

        return redirect()->back()->with('message', 'Machine added successfully.');
    }

    public function updateMachineStatus(Request $request, $machineId)
    {
        $validated = $request->validate([
            'status' => 'required|in:available,under_maintenance,retired',
            'remarks' => 'nullable|string',
        ]);

        $machine = Machine::findOrFail($machineId);
        $machine->update([
            'status' => $validated['status'],
            'remarks' => $validated['remarks'] ?? $machine->remarks,
        ]);

        return redirect()->back()->with('message', 'Machine status updated.');
    }

    public function reports()
    {
        $reports = MachineReport::with(['machine', 'reporter', 'resolver'])
            ->latest()
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/MaintenanceChecker/Reports', [
            'reports' => $reports,
        ]);
    }

    public function resolveReport(Request $request, $reportId)
    {
        $validated = $request->validate([
            'resolution_notes' => 'nullable|string',
        ]);

        $report = MachineReport::findOrFail($reportId);
        $report->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $this->staff()->id,
        ]);

        // Optionally, update the machine status if needed (maybe set back to available)
        // This depends on business logic.

        return redirect()->back()->with('message', 'Report marked as resolved.');
    }
}
