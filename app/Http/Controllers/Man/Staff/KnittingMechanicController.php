<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\Fabric;
use App\Models\Man\KnittingFlag;
use App\Models\Man\KnittingMachineSetup;
use App\Models\Man\Machine;
use App\Models\Man\MachineReport;
use App\Models\Inv\Material;
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
                ['machines' => $this->machineAvailability(Machine::TYPE_CIRCULAR_KNITTING)]
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
        $machines = Machine::where('type', Machine::TYPE_CIRCULAR_KNITTING)
            ->orderBy('machine_no')
            ->get(['id', 'machine_no', 'status']);

        $yarnOptions = Material::where('category', 'Yarn')->orderBy('name')->get()
            ->map(fn (Material $material) => $material->display_name)->unique()->values();

        $jobOrders = SalesOrder::where('status', 'in_production')
            ->orderBy('created_at', 'asc')
            ->take(30)
            ->get(['id', 'jo_number', 'color', 'design', 'yarn_type', 'quantity']);

        $fabricDesignOptions = SalesOrder::where('status', 'in_production')
            ->whereNotNull('design')->where('design', '!=', '')
            ->orderBy('design')->distinct()->pluck('design')->values();

        $fabrics = Fabric::where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->take(30)
            ->get(['id', 'code', 'yarn_type', 'weight']);

        $myOpenSetups = KnittingMachineSetup::with([
            'machine', 'fabric', 'salesOrder.client', 'salesOrder.recipe.product',
            'salesOrder.recipe.fabricSampleRequest', 'requester:id,name',
        ])
            ->where('operator_id', $this->staff()->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->latest()
            ->get();

        $myOpenSetups->each(function (KnittingMachineSetup $setup) {
            $order = $setup->salesOrder;
            $recipe = $order?->recipe;
            $sample = $recipe?->fabricSampleRequest ?: $recipe?->sampleRequests()->latest('id')->first();
            $setup->setAttribute('jo_details', [
                'client' => $order?->client?->company_name,
                'product' => $recipe?->product?->name,
                'fabric_design' => $order?->design ?: $recipe?->weave_design ?: $sample?->fabric_name,
                'color' => $order?->color ?: $recipe?->dye_color,
                'yarn_type' => $order?->yarn_type ?: $recipe?->yarn_type,
                'quantity' => $order?->quantity,
                'texture' => $sample?->texture,
                'requested_gsm' => $sample?->gsm,
            ]);
        });

        return Inertia::render('Dashboard/MAN/Employee/KnittingMechanic/Setups', [
            'machines' => $machines,
            'yarnOptions' => $yarnOptions,
            'jobOrders' => $jobOrders,
            'fabricDesignOptions' => $fabricDesignOptions,
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
            'gsm_target' => 'nullable|numeric|min:1',
            'yarn_type' => 'nullable|string|max:255',
            'cams_config' => 'nullable|string',
            'pattern_ref' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        $machine = Machine::findOrFail($validated['machine_id']);
        if ($machine->type !== Machine::TYPE_CIRCULAR_KNITTING) {
            return back()->withErrors(['machine_id' => 'Only Circular Knitting machines belong to this department.']);
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
                'yarn_type' => $validated['yarn_type'] ?? null,
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
            'gsm_target' => $setup->requested_by && $request->input('status') === 'done' ? 'required|numeric|min:1' : 'nullable|numeric|min:1',
            'pattern_ref' => 'nullable|string|max:255',
            'yarn_type' => $setup->requested_by && $request->input('status') === 'done' ? 'required|string|max:255' : 'nullable|string|max:255',
        ]);

        $order = ['pending' => 0, 'in_progress' => 1, 'done' => 2];
        if ($order[$validated['status']] < $order[$setup->status]) {
            return back()->withErrors(['status' => 'Setup sheets cannot move backwards.']);
        }

        $settings = $setup->settings ?? [];
        if (array_key_exists('gsm_target', $validated) && $validated['gsm_target'] !== null) {
            $settings['gsm_target'] = $validated['gsm_target'];
        }
        if (array_key_exists('pattern_ref', $validated) && $validated['pattern_ref'] !== null) {
            $settings['pattern_ref'] = $validated['pattern_ref'];
        }
        if (array_key_exists('yarn_type', $validated) && $validated['yarn_type'] !== null) {
            $settings['yarn_type'] = $validated['yarn_type'];
        }

        $setup->update([
            'status' => $validated['status'],
            'settings' => $settings,
            'processed_at' => $validated['status'] === 'done' ? now() : $setup->processed_at,
        ]);

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
        $machines = Machine::where('type', Machine::TYPE_CIRCULAR_KNITTING)->get(['id', 'machine_no', 'status']);
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
        if ($machine->type !== Machine::TYPE_CIRCULAR_KNITTING) {
            return back()->withErrors(['machine_id' => 'Only Circular Knitting machines belong to this department.']);
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
