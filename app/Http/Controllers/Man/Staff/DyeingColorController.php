<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\DyeJob;
use App\Models\Man\DyeJobChemical;
use App\Models\Man\Fabric;
use App\Models\Man\Machine;
use App\Models\Man\MachineReport;
use App\Models\Man\ManufacturingInventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DyeingColorController extends ManufacturingStaffController
{
    public function index()
    {
        // Only fabrics still awaiting dye work: at 'dyeing' stage with no dye
        // job recorded yet. Once recorded, the fabric sits with the quality
        // checker until approved — it must not linger in the dye queue.
        $pendingCount = Fabric::where('status', 'dyeing')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('dye_jobs')
                    ->whereColumn('dye_jobs.fabric_id', 'fabrics.id');
            })
            ->count();
        $recentJobs   = DyeJob::with('fabric')
            ->where('operator_id', $this->staff()->id)
            ->latest()
            ->take(10)
            ->get();

        $nextQueue = Fabric::with('machine')
            ->where('status', 'dyeing')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('dye_jobs')
                    ->whereColumn('dye_jobs.fabric_id', 'fabrics.id');
            })
            ->orderBy('created_at', 'asc')
            ->take(3)
            ->get(['id', 'code', 'yarn_type', 'weight', 'created_at']);

        return Inertia::render('Dashboard/MAN/Employee/DyeingColor/Index', [
            'stats' => [
                'pending'     => $pendingCount,
                'total_today' => DyeJob::whereDate('processed_at', today())->count(),
            ],
            'recentJobs' => $recentJobs,
            'efficiency' => array_merge(
                $this->staffEfficiency(DyeJob::class),
                ['machines' => $this->machineAvailability('dyeing')]
            ),
            'nextQueue' => $nextQueue,
        ]);
    }

    /**
     * Personal work log — own dye jobs only.
     */
    public function history()
    {
        return Inertia::render('Dashboard/MAN/Employee/Common/History', [
            'roleLabel' => 'Dyeing Color',
            'historyRoute' => 'man.staff.dyeing-color.history',
            'dateColumn' => 'processed_at',
            'jobs' => $this->staffHistory(DyeJob::class, ['fabric.machine', 'fabric.salesOrder.client', 'fabric.salesOrder.recipe.product', 'machine', 'operator', 'chemicals', 'fabric.softenerJobs', 'fabric.packages']),
        ]);
    }

    public function dyeingColor()
    {
        // Fabrics ready for dyeing — eager-load their linked JO + recipe.
        // Only fabrics with no dye job recorded yet: once recorded, the
        // fabric waits on the quality checker (stays 'dyeing') until the
        // checker approves it to softener or rejects it.
        $fabrics = Fabric::with(['machine', 'operator', 'salesOrder.recipe'])
            ->where('status', 'dyeing')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('dye_jobs')
                    ->whereColumn('dye_jobs.fabric_id', 'fabrics.id');
            })
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($fabric) {
                $salesOrder = $fabric->salesOrder;
                $recipe     = $salesOrder?->recipe;

                // Decode recipe materials if present (array via model cast,
                // or a raw JSON string on older rows).
                $materialsData = [];
                if ($recipe && $recipe->materials) {
                    $raw = $recipe->materials;
                    $json = is_string($raw) ? json_decode($raw, true) : $raw;
                    if (is_array($json)) {
                        foreach ($json as $matId => $qty) {
                            $materialsData[] = ['material_id' => $matId, 'quantity' => $qty];
                        }
                    }
                }

                return [
                    'id'           => $fabric->id,
                    'code'         => $fabric->code,
                    'yarn_type'    => $fabric->yarn_type,
                    'weight'       => $fabric->weight,
                    'shift'        => $fabric->shift,
                    'remarks'      => $fabric->remarks,
                    'processed_at' => $fabric->processed_at?->format('M d, Y g:i A'),
                    'machine'      => $fabric->machine
                        ? ['machine_no' => $fabric->machine->machine_no] : null,
                    'operator'     => $fabric->operator
                        ? ['name' => $fabric->operator->name] : null,

                    // Linked job order info
                    'sales_order'  => $salesOrder ? [
                        'jo_number' => $salesOrder->jo_number,
                        'color'     => $salesOrder->color,
                        'design'    => $salesOrder->design,
                        'quantity'  => $salesOrder->quantity,
                        'yarn_type' => $salesOrder->yarn_type,
                    ] : null,

                    // Recipe — the dye staff uses this to know the color formula
                    'recipe'       => $recipe ? [
                        'id'           => $recipe->id,
                        'yarn_type'    => $recipe->yarn_type,
                        'dye_color'    => $recipe->dye_color,
                        'weave_design' => $recipe->weave_design,
                        'materials'    => $materialsData,
                    ] : null,
                ];
            });

        $machines = Machine::where('type', 'dyeing')
            ->where('status', 'available')
            ->get(['id', 'machine_no']);

        // Dye chemicals available in production inventory (dyeing department)
        $dyes = ManufacturingInventoryItem::with('material')
            ->where('department', 'dyeing')
            ->where('category', 'Dye')
            ->where('status', '!=', 'depleted')
            ->where('remaining_quantity', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($item) => [
                'id'                 => $item->id,
                'control_number'     => $item->control_number,
                'material_name'      => $item->material->name,
                'remaining_quantity' => $item->remaining_quantity,
                'unit'               => $item->unit,
            ]);

        return Inertia::render('Dashboard/MAN/Employee/DyeingColor/DyeingColor', [
            'fabrics'  => $fabrics,
            'machines' => $machines,
            'dyes'     => $dyes,       // NEW — inventory-sourced dye chemicals
        ]);
    }

    /**
     * Record a dye job, consuming chemical lots from production inventory.
     *
     * dyes_used[] mirrors the knitting yarns_used[] pattern:
     *   [{ inventory_item_id, quantity_used }]
     */
    public function storeDye(Request $request)
    {
        $validated = $request->validate([
            'fabric_id'                       => 'required|exists:fabrics,id',
            'machine_id'                      => 'required|exists:machines,id',
            'remarks'                         => 'nullable|string',
            'dyes_used'                       => 'required|array|min:1',
            'dyes_used.*.inventory_item_id'   => 'required|exists:manufacturing_inventory_items,id',
            'dyes_used.*.quantity_used'       => 'required|numeric|min:0.01',
        ]);

        // Pre-validate sufficient quantity for every dye lot
        $insufficient = [];
        foreach ($validated['dyes_used'] as $usage) {
            $item = ManufacturingInventoryItem::with('material')
                ->find($usage['inventory_item_id']);
            if (!$item) {
                return back()->withErrors(['dyes_used' => 'Invalid dye item selected.']);
            }
            if ($item->remaining_quantity < $usage['quantity_used']) {
                $insufficient[] = "{$item->material->name} ({$item->control_number})"
                    . " — only {$item->remaining_quantity} {$item->unit} left";
            }
        }
        if (!empty($insufficient)) {
            return back()->withErrors([
                'dyes_used' => 'Insufficient chemicals: ' . implode(', ', $insufficient),
            ]);
        }

        DB::beginTransaction();
        try {
            // Checker gate: only fabrics the checker placed at 'dyeing' may
            // be dyed (recolored rejects re-enter here through the checker).
            $fabric = Fabric::findOrFail($validated['fabric_id']);
            if ($fabric->status !== 'dyeing') {
                DB::rollBack();
                return back()->withErrors(['fabric_id' => 'This fabric is not awaiting dyeing (it may already be recorded or approved).']);
            }
            if ($fabric->dyeJobs()->exists()) {
                DB::rollBack();
                return back()->withErrors(['fabric_id' => 'A dye job is already recorded for this fabric — awaiting quality check.']);
            }

            // Use the first dye lot as the "primary" for the dye_jobs main columns
            $primaryItem = ManufacturingInventoryItem::with('material')
                ->findOrFail($validated['dyes_used'][0]['inventory_item_id']);

            $dyeJob = DyeJob::create([
                'fabric_id'    => $validated['fabric_id'],
                'machine_id'   => $validated['machine_id'],
                'dye_type'     => $primaryItem->material->name,
                'chemical_no'  => $primaryItem->control_number,
                'remarks'      => $validated['remarks'],
                'operator_id'  => $this->staff()->id,
                'shift'        => $this->getShift(),
                'code'         => $this->generateCode('CHEM', DyeJob::class),
                'processed_at' => now(),
            ]);

            // Create chemical detail rows and deduct from each inventory lot
            foreach ($validated['dyes_used'] as $usage) {
                $item = ManufacturingInventoryItem::with('material')
                    ->findOrFail($usage['inventory_item_id']);

                DyeJobChemical::create([
                    'dye_job_id'        => $dyeJob->id,
                    'inventory_item_id' => $item->id,
                    'dye_type'          => $item->material->name,
                    'control_number'    => $item->control_number,
                    'quantity_used'     => $usage['quantity_used'],
                ]);

                // Deduct quantity from inventory (kg-based, not roll-based)
                $item->remaining_quantity = max(0, $item->remaining_quantity - $usage['quantity_used']);
                $item->status = $item->remaining_quantity <= 0 ? 'depleted' : 'partial';
                $item->save();
            }

            // Checker gate: fabric stays 'dyeing' until the quality checker
            // approves it (passDye → softener) or rejects it. Staff must
            // never advance it — that would bypass quality inspection.
            DB::commit();
            return redirect()->back()->with(
                'message',
                'Dye job recorded. Awaiting quality check before it can move to softener.'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to record dye job: ' . $e->getMessage()]);
        }
    }

    public function reports()
    {
        $machines  = Machine::where('type', 'dyeing')->get(['id', 'machine_no', 'status']);
        $myReports = MachineReport::where('reported_by', $this->staff()->id)
            ->with('machine')->latest()->get();

        return Inertia::render('Dashboard/MAN/Employee/DyeingColor/Reports', [
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
}