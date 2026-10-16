<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\Fabric;
use App\Models\Man\KnittingFlag;
use App\Models\Man\KnittingMachineSetup;
use App\Models\Man\Machine;
use App\Models\Man\MachineReport;
use App\Models\Ord\SalesOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KnittingMechanicController extends ManufacturingStaffController
{
    public function index()
    {
        $pendingCount = KnittingMachineSetup::whereIn('status', ['pending', 'in_progress'])->count();
        $recentSetups = KnittingMachineSetup::with(['machine', 'fabric'])
            ->where('operator_id', $this->staff()->id)
            ->latest()
            ->take(10)
            ->get();

        // Next in queue: oldest open setup sheets (role-wide queue, read-only).
        $nextQueue = KnittingMachineSetup::with('machine')
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('created_at', 'asc')
            ->take(3)
            ->get(['id', 'code', 'task_type', 'status', 'created_at']);

        return Inertia::render('Dashboard/MAN/Employee/KnittingMechanic/Index', [
            'stats' => [
                'pending' => $pendingCount,
                'total_today' => KnittingMachineSetup::whereDate('processed_at', today())->count(),
            ],
            'recentSetups' => $recentSetups,
            'efficiency' => array_merge(
                $this->staffEfficiency(KnittingMachineSetup::class),
                ['machines' => $this->machineAvailability('knitting')]
            ),
            'nextQueue' => $nextQueue,
        ]);
    }

    /**
     * Setup & changeover work page: knitting machines, open job orders,
     * pending fabrics, and the mechanic's own open sheets.
     */
    public function setups()
    {
        $machines = Machine::where('type', 'knitting')
            ->orderBy('machine_no')
            ->get(['id', 'machine_no', 'status']);

        $jobOrders = SalesOrder::where('status', 'in_production')
            ->orderBy('created_at', 'asc')
            ->take(30)
            ->get(['id', 'jo_number', 'color', 'design', 'yarn_type', 'quantity']);

        $fabrics = Fabric::where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->take(30)
            ->get(['id', 'code', 'yarn_type', 'weight']);

        $myOpenSetups = KnittingMachineSetup::with(['machine', 'fabric', 'salesOrder'])
            ->where('operator_id', $this->staff()->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->latest()
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/KnittingMechanic/Setups', [
            'machines' => $machines,
            'jobOrders' => $jobOrders,
            'fabrics' => $fabrics,
            'myOpenSetups' => $myOpenSetups,
        ]);
    }

    /**
     * Record a setup / changeover / calibration / preventive / repair sheet.
     * Scoped to knitting machines only.
     */
    public function storeSetup(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'task_type' => 'required|in:setup,changeover,calibration,preventive,repair',
            'sales_order_id' => 'nullable|exists:sales_orders,id',
            'fabric_id' => 'nullable|exists:fabrics,id',
            'stitch_length' => 'nullable|string|max:64',
            'yarn_tension' => 'nullable|string|max:64',
            'takeup_pressure' => 'nullable|string|max:64',
            'gsm_target' => 'nullable|string|max:64',
            'cams_config' => 'nullable|string',
            'pattern_ref' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        $machine = Machine::findOrFail($validated['machine_id']);
        if ($machine->type !== 'knitting') {
            return back()->withErrors(['machine_id' => 'Only knitting machines belong to this department.']);
        }

        KnittingMachineSetup::create([
            'code' => $this->generateCode('KSET', KnittingMachineSetup::class),
            'machine_id' => $validated['machine_id'],
            'sales_order_id' => $validated['sales_order_id'] ?? null,
            'fabric_id' => $validated['fabric_id'] ?? null,
            'task_type' => $validated['task_type'],
            'settings' => [
                'stitch_length' => $validated['stitch_length'] ?? null,
                'yarn_tension' => $validated['yarn_tension'] ?? null,
                'takeup_pressure' => $validated['takeup_pressure'] ?? null,
                'gsm_target' => $validated['gsm_target'] ?? null,
                'cams_config' => $validated['cams_config'] ?? null,
                'pattern_ref' => $validated['pattern_ref'] ?? null,
            ],
            'status' => 'pending',
            'remarks' => $validated['remarks'] ?? null,
            'operator_id' => $this->staff()->id,
            'shift' => $this->getShift(),
            'processed_at' => now(),
        ]);

        return redirect()->back()->with('message', 'Setup sheet recorded successfully.');
    }

    /**
     * Advance own setup sheet: pending → in_progress → done.
     */
    public function updateStatus(Request $request, KnittingMachineSetup $setup)
    {
        if ($setup->operator_id !== $this->staff()->id) {
            abort(403, 'You can only update your own setup sheets.');
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,done',
        ]);

        $order = ['pending' => 0, 'in_progress' => 1, 'done' => 2];
        if ($order[$validated['status']] < $order[$setup->status]) {
            return back()->withErrors(['status' => 'Setup sheets cannot move backwards.']);
        }

        $setup->update(['status' => $validated['status']]);

        return redirect()->back()->with('message', "Setup sheet {$setup->code} marked as {$validated['status']}.");
    }

    /**
     * Personal work log — own setup sheets only.
     */
    public function history()
    {
        return Inertia::render('Dashboard/MAN/Employee/Common/History', [
            'roleLabel' => 'Knitting Mechanic',
            'historyRoute' => 'man.staff.knitting-mechanic.history',
            'dateColumn' => 'processed_at',
            'jobs' => $this->staffHistory(KnittingMachineSetup::class, ['machine', 'fabric', 'salesOrder']),
        ]);
    }

    public function reports()
    {
        $machines = Machine::where('type', 'knitting')->get(['id', 'machine_no', 'status']);
        $myReports = MachineReport::where('reported_by', $this->staff()->id)
            ->with('machine')->latest()->get();

        // Flags raised by Knitting Yarn staff (open + acknowledged first).
        $incomingFlags = KnittingFlag::with(['machine', 'fabric', 'reporter'])
            ->orderByRaw("FIELD(status, 'open', 'acknowledged', 'resolved')")
            ->latest()
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/KnittingMechanic/Reports', [
            'machines' => $machines,
            'myReports' => $myReports,
            'incomingFlags' => $incomingFlags,
        ]);
    }

    public function reportMachine(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'issue' => 'required|string',
        ]);

        $machine = Machine::findOrFail($validated['machine_id']);
        if ($machine->type !== 'knitting') {
            return back()->withErrors(['machine_id' => 'Only knitting machines belong to this department.']);
        }

        MachineReport::create([
            'machine_id' => $validated['machine_id'],
            'reported_by' => $this->staff()->id,
            'issue' => $validated['issue'],
            'status' => 'pending',
        ]);

        return redirect()->back()->with('message', 'Machine issue reported. Maintenance will resolve it.');
    }

    /**
     * Acknowledge a yarn-staff flag (open → acknowledged).
     */
    public function acknowledgeFlag(KnittingFlag $flag)
    {
        if ($flag->status !== 'open') {
            return back()->withErrors(['error' => 'Only open flags can be acknowledged.']);
        }

        $flag->update(['status' => 'acknowledged']);

        return redirect()->back()->with('message', "Flag {$flag->code} acknowledged.");
    }

    /**
     * Resolve a yarn-staff flag (acknowledged → resolved).
     */
    public function resolveFlag(KnittingFlag $flag)
    {
        if ($flag->status === 'resolved') {
            return back()->withErrors(['error' => 'This flag is already resolved.']);
        }

        $flag->update([
            'status' => 'resolved',
            'resolved_by' => $this->staff()->id,
            'resolved_at' => now(),
        ]);

        return redirect()->back()->with('message', "Flag {$flag->code} resolved.");
    }
}
