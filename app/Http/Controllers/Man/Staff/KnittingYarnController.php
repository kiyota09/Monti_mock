<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\Fabric;
use App\Models\Man\KnittingFlag;
use App\Models\Man\Machine;
use App\Models\Man\MachineReport;
use App\Models\Man\ManufacturingInventoryItem;
use App\Models\Ord\SalesOrder;
use App\Models\Inv\Material;
use App\Models\Man\Staff;
use App\Models\Hrm\EmployeeShift;


use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KnittingYarnController extends ManufacturingStaffController
{
    public function index()
    {
        $pendingCount  = Fabric::where('status', 'pending')->count();
        $recentFabrics = Fabric::with('machine')
            ->where('operator_id', $this->staff()->id)
            ->latest()
            ->take(10)
            ->get();

        // Next in queue: oldest unlinked pending fabrics (role-wide queue, read-only).
        $nextQueue = Fabric::with('machine')
            ->where('status', 'pending')
            ->whereNull('sales_order_id')
            ->orderBy('created_at', 'asc')
            ->take(3)
            ->get(['id', 'code', 'yarn_type', 'weight', 'created_at']);

        return Inertia::render('Dashboard/MAN/Employee/KnittingYarn/Index', [
            'stats' => [
                'pending'     => $pendingCount,
                'total_today' => Fabric::whereDate('processed_at', today())->count(),
            ],
            'recentFabrics' => $recentFabrics,
            // NEW — personal efficiency (own output only) + next queue + machine context.
            'efficiency' => array_merge(
                $this->staffEfficiency(Fabric::class),
                ['machines' => $this->machineAvailability('knitting')]
            ),
            'nextQueue' => $nextQueue,
        ]);
    }

    /**
     * Personal work log — own fabrics only, searchable/filterable.
     * Route is inside the knitting_yarn prefix so man.role:knitting_yarn applies.
     */
    public function history()
    {
        return Inertia::render('Dashboard/MAN/Employee/Common/History', [
            'roleLabel' => 'Knitting Yarn',
            'historyRoute' => 'man.staff.knitting-yarn.history',
            'dateColumn' => 'processed_at',
            'jobs' => $this->staffHistory(Fabric::class, ['machine', 'operator', 'salesOrder.client', 'salesOrder.recipe.product', 'dyeJobs', 'softenerJobs', 'packages']),
        ]);
    }

    public function knittingYarn()
    {
        $machines = Machine::where('type', 'knitting')
            ->where('status', 'available')
            ->get(['id', 'machine_no']);

        // Roll-based yarn inventory for knitting
        $yarns = ManufacturingInventoryItem::with('material')
            ->where('department', 'knitting')
            ->where('category', 'Yarn')
            ->where('status', '!=', 'depleted')
            ->whereNotNull('total_units')
            ->where('total_units', '>', 0)
            ->where('unit_type', 'roll')
            ->get()
            ->map(function ($item) {
                return [
                    'id'                 => $item->id,
                    'control_number'     => $item->control_number,
                    'material_name'      => $item->material->name,
                    'remaining_quantity' => $item->remaining_quantity,
                    'unit'               => $item->unit,
                    'total_units'        => $item->total_units,
                    'used_units'         => $item->used_units,
                    'unit_type'          => $item->unit_type,
                    'unit_weight'        => $item->unit_weight,
                    'available_units'    => $item->total_units - $item->used_units,
                ];
            });

        // All fabrics that are pending and not yet linked to any JO —
        // shown in the Mark Done fabric-selection modal.
        $availableFabrics = Fabric::with('machine')
            ->where('status', 'pending')
            ->whereNull('sales_order_id')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($f) => [
                'id'           => $f->id,
                'code'         => $f->code,
                'yarn_type'    => $f->yarn_type,
                'weight'       => $f->weight,
                'shift'        => $f->shift,
                'processed_at' => Carbon::parse($f->processed_at)->format('M d, Y g:i A'),
                'machine_no'   => $f->machine?->machine_no ?? 'N/A',
                'remarks'      => $f->remarks,
            ]);

        // Job orders in production — pending first, done last
        $jobOrders = SalesOrder::with('recipe')
            ->where('status', 'in_production')
            ->orderByRaw('knitting_done_at IS NOT NULL ASC')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($order) {
                $recipe        = $order->recipe;
                $materialsData = [];

                if ($recipe && $recipe->materials) {
                    // BomRecord casts materials to array, but older rows may
                    // still arrive as a JSON string — handle both.
                    $rawMaterials = $recipe->materials;
                    $jsonMaterials = is_string($rawMaterials)
                        ? json_decode($rawMaterials, true)
                        : $rawMaterials;
                    if (is_array($jsonMaterials)) {
                        $materials = Material::whereIn('id', array_keys($jsonMaterials))
                            ->get()->keyBy('id');
                        foreach ($jsonMaterials as $matId => $qty) {
                            $mat = $materials[$matId] ?? null;
                            if ($mat) {
                                $materialsData[] = [
                                    'id'        => $mat->id,
                                    'mat_id'    => $mat->mat_id,
                                    'name'      => $mat->name,
                                    'category'  => $mat->category,
                                    'quantity'  => $qty,
                                    'unit'      => $mat->unit,
                                    'unit_cost' => $mat->unit_cost,
                                ];
                            }
                        }
                    }
                }

                $formattedDoneAt = null;
                if ($order->knitting_done_at) {
                    $formattedDoneAt = Carbon::parse($order->knitting_done_at)
                        ->format('M d, Y — g:i A');
                }

                // Fabrics already linked to this JO
                $linkedFabrics = Fabric::where('sales_order_id', $order->id)
                    ->get(['id', 'code', 'yarn_type', 'weight'])
                    ->map(fn ($f) => [
                        'id'        => $f->id,
                        'code'      => $f->code,
                        'yarn_type' => $f->yarn_type,
                        'weight'    => $f->weight,
                    ])->values();

                return [
                    'id'               => $order->id,
                    'jo_number'        => $order->jo_number,
                    'control_number'   => $order->control_number,
                    'color'            => $order->color,
                    'quantity'         => $order->quantity,
                    'yarn_type'        => $order->yarn_type,
                    'design'           => $order->design,
                    'unit_price'       => $order->unit_price,
                    'total_amount'     => $order->total_amount,
                    'is_knitting_done' => !is_null($order->knitting_done_at),
                    'knitting_done_at' => $formattedDoneAt,
                    'linked_fabrics'   => $linkedFabrics,
                    'recipe'           => $recipe ? [
                        'id'           => $recipe->id,
                        'yarn_type'    => $recipe->yarn_type,
                        'dye_color'    => $recipe->dye_color,
                        'weave_design' => $recipe->weave_design,
                        'materials'    => $materialsData,
                        'created_at'   => $recipe->created_at?->format('M d, Y'),
                    ] : null,
                ];
            });

        return Inertia::render('Dashboard/MAN/Employee/KnittingYarn/KnittingYarn', [
            'machines'        => $machines,
            'yarns'           => $yarns,
            'jobOrders'       => $jobOrders,
            'availableFabrics' => $availableFabrics,   // NEW — fed to fabric-link modal
        ]);
    }

    /**
     * Mark a JO as knitting-done and link the selected fabrics to it.
     */
    public function markDone(Request $request, $id)
    {
        $order = SalesOrder::where('status', 'in_production')->findOrFail($id);

        if (!is_null($order->knitting_done_at)) {
            return back()->withErrors(['error' => 'This job order is already marked as done.']);
        }

        $validated = $request->validate([
            'fabric_ids'   => 'required|array|min:1',
            'fabric_ids.*' => 'required|exists:fabrics,id',
        ]);

        // Link selected fabrics to this JO (only unlinked ones to prevent re-assignment)
        $linked = Fabric::whereIn('id', $validated['fabric_ids'])
            ->whereNull('sales_order_id')
            ->update(['sales_order_id' => $order->id]);

        $order->knitting_done_at = now();
        $order->knitting_done_by = $this->staff()->id;
        $order->save();

        return redirect()->back()->with(
            'message',
            "Job Order {$order->jo_number} marked as knitting done. {$linked} fabric(s) linked."
        );
    }

    /**
     * Undo the knitting-done mark and unlink all fabrics from this JO.
     */
    public function unmarkDone(Request $request, $id)
    {
        $order = SalesOrder::where('status', 'in_production')->findOrFail($id);

        if (is_null($order->knitting_done_at)) {
            return back()->withErrors(['error' => 'This job order is not marked as done.']);
        }

        // Unlink all fabrics that were attached to this JO (resets them to available)
        Fabric::where('sales_order_id', $order->id)
            ->where('status', 'pending')           // only safe to unlink if not yet in dyeing
            ->update(['sales_order_id' => null]);

        $order->knitting_done_at = null;
        $order->knitting_done_by = null;
        $order->save();

        return redirect()->back()->with(
            'message',
            "Job Order {$order->jo_number} has been re-opened. Pending fabrics unlinked."
        );
    }

    /**
     * Record a new fabric, consuming yarn lots from inventory.
     */
    public function storeFabric(Request $request)
    {
        $validated = $request->validate([
            'machine_id'                     => 'required|exists:machines,id',
            'yarn_type'                      => 'required|string|max:255',
            'weight'                         => 'required|numeric|min:0',
            'remarks'                        => 'nullable|string',
            'yarns_used'                     => 'required|array|min:1',
            'yarns_used.*.inventory_item_id' => 'required|exists:manufacturing_inventory_items,id',
            'yarns_used.*.units_used'        => 'required|integer|min:1',
        ]);

        $insufficient = [];
        foreach ($validated['yarns_used'] as $usage) {
            $item      = ManufacturingInventoryItem::find($usage['inventory_item_id']);
            $available = $item->total_units - $item->used_units;
            if ($available < $usage['units_used']) {
                $insufficient[] = "{$item->material->name} ({$item->control_number}) — only {$available} rolls left";
            }
        }
        if (!empty($insufficient)) {
            return back()->withErrors(['yarns_used' => 'Insufficient rolls: ' . implode(', ', $insufficient)]);
        }

        DB::beginTransaction();
        try {
            $fabric = Fabric::create([
                'code'                   => $this->generateCode('FABRIC', Fabric::class),
                'manufacturing_order_id' => null,
                'machine_id'             => $validated['machine_id'],
                'yarn_type'              => $validated['yarn_type'],
                'weight'                 => $validated['weight'],
                'remarks'                => $validated['remarks'],
                'operator_id'            => $this->staff()->id,
                'shift'                  => $this->getShift(),
                'processed_at'           => now(),
                'status'                 => 'pending',
            ]);

            foreach ($validated['yarns_used'] as $usage) {
                $item     = ManufacturingInventoryItem::findOrFail($usage['inventory_item_id']);
                $consumed = $item->consumeUnits($usage['units_used']);
                if (!$consumed) {
                    throw new \Exception("Failed to consume units for {$item->material->name} ({$item->control_number})");
                }
            }

            DB::commit();
            return redirect()->back()->with(
                'message',
                'Fabric ' . $fabric->code . ' recorded. Yarn inventory updated for ' . count($validated['yarns_used']) . ' lot(s).'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to record fabric: ' . $e->getMessage()]);
        }
    }

    public function reports()
    {
        $machines  = Machine::where('type', 'knitting')->get(['id', 'machine_no', 'status']);
        $myReports = MachineReport::where('reported_by', $this->staff()->id)
            ->with('machine')->latest()->get();

        return Inertia::render('Dashboard/MAN/Employee/KnittingYarn/Reports', [
            'machines'  => $machines,
            'myReports' => $myReports,
        ]);
    }

    public function reportMachine(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'issue'      => 'required|string',
        ]);

        MachineReport::create([
            'machine_id'  => $validated['machine_id'],
            'reported_by' => $this->staff()->id,
            'issue'       => $validated['issue'],
            'status'      => 'pending',
        ]);

        return redirect()->back()->with('message', 'Machine issue reported.');
    }

    /**
     * Flag Reports: yarn staff flag knitting issues for the mechanics.
     * (Plant-wide breakdowns still go through Reports → Maintenance.)
     */
    public function flagReports()
    {
        $machines = Machine::where('type', 'knitting')
            ->orderBy('machine_no')
            ->get(['id', 'machine_no', 'status']);

        $myFlags = KnittingFlag::with(['machine', 'resolver'])
            ->where('reported_by', $this->staff()->id)
            ->latest()
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/KnittingYarn/FlagReports', [
            'machines' => $machines,
            'myFlags' => $myFlags,
        ]);
    }

    public function storeFlag(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => 'nullable|exists:machines,id',
            'fabric_id' => 'nullable|exists:fabrics,id',
            'issue_type' => 'required|in:machine_jam,yarn_feeder,needle_damage,tension_issue,quality_defect,other',
            'description' => 'required|string',
        ]);

        if (! empty($validated['machine_id'])) {
            $machine = Machine::findOrFail($validated['machine_id']);
            if ($machine->type !== 'knitting') {
                return back()->withErrors(['machine_id' => 'Only knitting machines belong to this department.']);
            }
        }

        KnittingFlag::create([
            'code' => $this->generateCode('KFLAG', KnittingFlag::class),
            'machine_id' => $validated['machine_id'] ?? null,
            'fabric_id' => $validated['fabric_id'] ?? null,
            'issue_type' => $validated['issue_type'],
            'description' => $validated['description'],
            'status' => 'open',
            'reported_by' => $this->staff()->id,
        ]);

        return redirect()->back()->with('message', 'Flag sent to the knitting mechanics.');
    }
}