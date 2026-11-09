<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\BoilerLog;
use App\Models\Man\Machine;
use App\Models\Man\MachineReport;
use App\Models\Inv\Material;
use App\Services\Man\BoilerSteamService;
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
        $steamAvailable = app(BoilerSteamService::class)->isAvailable();
        $steamConsumers = Machine::query()
            ->whereIn('type', ['dyeing', 'squeezer', 'hydro', 'heatsetting', 'ironing', 'tubetex', 'tubetext'])
            ->orderBy('type')->orderBy('machine_no')->get(['id', 'machine_no', 'type', 'power', 'status', 'load_capacity_kg'])
            ->filter(fn (Machine $machine) => strtolower(trim((string) $machine->power)) === 'steam')
            ->map(fn (Machine $machine) => [
                'id' => $machine->id,
                'machine_no' => $machine->machine_no,
                'type' => $machine->type,
                'status' => $machine->status,
                'load_capacity_kg' => $machine->load_capacity_kg,
            ])->values();

        return Inertia::render('Dashboard/MAN/Employee/BoilerOperator/Index', [
            'stats' => [
                'boilers' => $boilers,
                'boilers_down' => $boilersDown,
                'logs_today' => BoilerLog::whereDate('processed_at', today())->count(),
            ],
            'recentLogs' => $recentLogs,
            'steamConsumers' => $steamConsumers,
            'steamAvailable' => $steamAvailable,
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

        $todayLogCount = BoilerLog::whereDate('processed_at', today())->count();
        $todayLogs = BoilerLog::with('machine')
            ->whereDate('processed_at', today())
            ->latest()
            ->get();
        $activeRunLogs = BoilerLog::with('machine')
            ->where('processed_at', '>=', now()->subDays(7))
            ->latest('processed_at')
            ->get()
            ->unique('machine_id')
            ->filter(fn (BoilerLog $log) => $log->steam_available
                && $log->processed_at
                && (float) $log->operating_hours > 0
                && $log->processed_at->copy()->addMinutes((int) round((float) $log->operating_hours * 60))->isFuture());
        $todayLogs = $todayLogs->concat($activeRunLogs)->unique('id')->sortByDesc('processed_at')->values();
        $chemicalMaterials = Material::query()
            ->where('category', 'Boiler Chemicals')
            ->orderBy('name')
            ->get(['id', 'name', 'unit']);

        return Inertia::render('Dashboard/MAN/Employee/BoilerOperator/Logs', [
            'machines' => $machines,
            'todayLogs' => $todayLogs,
            'todayLogCount' => $todayLogCount,
            'chemicalMaterials' => $chemicalMaterials,
        ]);
    }

    public function storeLog(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'steam_pressure' => 'required|numeric|min:0',
            'water_level' => 'required|in:low,normal,high',
            'fuel_used' => 'required|numeric|min:0',
            'fuel_unit' => 'nullable|in:L,kg',
            'blowdown_done' => 'nullable|boolean',
            'chemical_doses' => 'nullable|array',
            'chemical_doses.*.chemical_id' => 'required|exists:materials,id',
            'chemical_doses.*.quantity' => 'required|numeric|gt:0',
            'operating_hours' => 'required|numeric|gt:0',
            'steam_available' => 'nullable|boolean',
            'remarks' => 'nullable|string',
        ]);

        $machine = Machine::findOrFail($validated['machine_id']);
        if ($machine->type !== 'boiler') {
            return back()->withErrors(['machine_id' => 'Only boiler units belong to this department.']);
        }

        $chemicalDoses = $validated['chemical_doses'] ?? [];
        $chemicals = Material::query()->whereIn('id', collect($chemicalDoses)->pluck('chemical_id'))
            ->where('category', 'Boiler Chemicals')->get()->keyBy('id');
        if ($chemicals->count() !== collect($chemicalDoses)->pluck('chemical_id')->unique()->count()) {
            return back()->withErrors(['chemical_doses' => 'Choose every chemical from Boiler Chemicals inventory.']);
        }
        $chemicalDoses = collect($chemicalDoses)->map(function (array $dose) use ($chemicals) {
            $chemical = $chemicals->get($dose['chemical_id']);
            return [
                'chemical_id' => (int) $chemical->id,
                'name' => $chemical->name,
                'quantity' => (float) $dose['quantity'],
                'unit' => $chemical->unit ?: 'units',
            ];
        })->values()->all();
        BoilerLog::create([
            ...collect($validated)->except(['chemical_doses'])->all(),
            'chemical_doses' => $chemicalDoses ?: null,
            'code' => $this->generateCode('BOILER', BoilerLog::class),
            'blowdown_done' => (bool) ($validated['blowdown_done'] ?? false),
            'steam_available' => (bool) ($validated['steam_available'] ?? false),
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
