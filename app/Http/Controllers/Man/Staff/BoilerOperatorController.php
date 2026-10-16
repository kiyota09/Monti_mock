<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\BoilerLog;
use App\Models\Man\Machine;
use App\Models\Man\MachineReport;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BoilerOperatorController extends ManufacturingStaffController
{
    public function index()
    {
        $boilers = Machine::where('type', 'boiler')->count();
        $boilersDown = Machine::where('type', 'boiler')->where('status', '!=', 'available')->count();
        $recentLogs = BoilerLog::with('machine')
            ->where('operator_id', $this->staff()->id)
            ->latest()
            ->take(10)
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/BoilerOperator/Index', [
            'stats' => [
                'boilers' => $boilers,
                'boilers_down' => $boilersDown,
                'logs_today' => BoilerLog::whereDate('processed_at', today())->count(),
            ],
            'recentLogs' => $recentLogs,
            'efficiency' => array_merge(
                $this->staffEfficiency(BoilerLog::class),
                ['machines' => $this->machineAvailability('boiler')]
            ),
        ]);
    }

    /**
     * Shift operating log work page.
     */
    public function logs()
    {
        $machines = Machine::where('type', 'boiler')
            ->orderBy('machine_no')
            ->get(['id', 'machine_no', 'status']);

        $todayLogs = BoilerLog::with('machine')
            ->whereDate('processed_at', today())
            ->latest()
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/BoilerOperator/Logs', [
            'machines' => $machines,
            'todayLogs' => $todayLogs,
        ]);
    }

    public function storeLog(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'steam_pressure' => 'nullable|numeric|min:0',
            'water_level' => 'nullable|in:low,normal,high',
            'fuel_used' => 'nullable|numeric|min:0',
            'fuel_unit' => 'nullable|in:L,kg',
            'blowdown_done' => 'nullable|boolean',
            'chemical_dosing' => 'nullable|string|max:255',
            'operating_hours' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
        ]);

        $machine = Machine::findOrFail($validated['machine_id']);
        if ($machine->type !== 'boiler') {
            return back()->withErrors(['machine_id' => 'Only boiler units belong to this department.']);
        }

        BoilerLog::create([
            ...$validated,
            'code' => $this->generateCode('BOILER', BoilerLog::class),
            'blowdown_done' => (bool) ($validated['blowdown_done'] ?? false),
            'operator_id' => $this->staff()->id,
            'shift' => $this->getShift(),
            'processed_at' => now(),
        ]);

        return redirect()->back()->with('message', 'Boiler log recorded successfully.');
    }

    /**
     * Personal work log — own boiler logs only.
     */
    public function history()
    {
        return Inertia::render('Dashboard/MAN/Employee/Common/History', [
            'roleLabel' => 'Boiler Operator',
            'historyRoute' => 'man.staff.boiler-operator.history',
            'dateColumn' => 'processed_at',
            'jobs' => $this->staffHistory(BoilerLog::class, ['machine']),
        ]);
    }

    public function reports()
    {
        $machines = Machine::where('type', 'boiler')->get(['id', 'machine_no', 'status']);
        $myReports = MachineReport::where('reported_by', $this->staff()->id)
            ->with('machine')->latest()->get();

        return Inertia::render('Dashboard/MAN/Employee/BoilerOperator/Reports', [
            'machines' => $machines,
            'myReports' => $myReports,
        ]);
    }

    public function reportMachine(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'issue' => 'required|string',
        ]);

        $machine = Machine::findOrFail($validated['machine_id']);
        if ($machine->type !== 'boiler') {
            return back()->withErrors(['machine_id' => 'Only boiler units belong to this department.']);
        }

        MachineReport::create([
            'machine_id' => $validated['machine_id'],
            'reported_by' => $this->staff()->id,
            'issue' => $validated['issue'],
            'status' => 'pending',
        ]);

        return redirect()->back()->with('message', 'Boiler issue reported. Maintenance will resolve it.');
    }
}
