<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\Fabric;
use App\Models\Man\Machine;
use App\Models\Man\MachineReport;
use App\Models\Man\ManufacturingInventoryItem;
use App\Models\Man\SoftenerJob;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class DyeingFabricSoftenerController extends ManufacturingStaffController
{
    public function index()
    {
        // Checker gate: fabrics at 'softener' with no live job row yet
        // ('softened' awaiting QC, or 'squeezed' already moved on). Fabrics
        // the checker sent back for rework ('resoften') re-appear here so
        // staff can redo them; recorded work sits with the checker until
        // approved and never lingers in this queue.
        $pendingCount = Fabric::where('status', 'softener')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('softener_jobs')
                    ->whereColumn('softener_jobs.fabric_id', 'fabrics.id')
                    ->whereIn('softener_jobs.status', ['softened', 'squeezed']);
            })
            ->count();
        $recentJobs = SoftenerJob::with('fabric')
            ->where('operator_id', $this->staff()->id)
            ->latest()
            ->take(10)
            ->get();

        $nextQueue = Fabric::where('status', 'softener')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('softener_jobs')
                    ->whereColumn('softener_jobs.fabric_id', 'fabrics.id')
                    ->whereIn('softener_jobs.status', ['softened', 'squeezed']);
            })
            ->orderBy('created_at', 'asc')
            ->take(3)
            ->get(['id', 'code', 'yarn_type', 'weight', 'created_at']);

        return Inertia::render('Dashboard/MAN/Employee/DyeingFabricSoftener/Index', [
            'stats' => [
                'pending' => $pendingCount,
                'total_today' => SoftenerJob::whereDate('processed_at', today())->count(),
            ],
            'recentJobs' => $recentJobs,
            'efficiency' => array_merge(
                $this->staffEfficiency(SoftenerJob::class),
                ['machines' => $this->machineAvailability('softening')]
            ),
            'nextQueue' => $nextQueue,
        ]);
    }

    /**
     * Personal work log — own softener jobs only.
     */
    public function history()
    {
        return Inertia::render('Dashboard/MAN/Employee/Common/History', [
            'roleLabel' => 'Dyeing Fabric Softener',
            'historyRoute' => 'man.staff.dyeing-fabric-softener.history',
            'dateColumn' => 'processed_at',
            'jobs' => $this->staffHistory(SoftenerJob::class, ['fabric.machine', 'fabric.salesOrder.client', 'fabric.salesOrder.recipe.product', 'machine', 'operator', 'squeezerJob', 'fabric.dyeJobs', 'fabric.packages']),
        ]);
    }

    public function dyeingFabricSoftener()
    {
        // Only fabrics that are AT the softener stage AND have no live job
        // row yet ('softened' awaiting QC, or 'squeezed' already moved on).
        // Fabrics the checker sent back for rework ('resoften') re-appear
        // here for redo; recorded work stays at status 'softener' with the
        // quality checker until approved.
        $fabrics = Fabric::with('machine', 'salesOrder')
            ->where('status', 'softener')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('softener_jobs')
                    ->whereColumn('softener_jobs.fabric_id', 'fabrics.id')
                    ->whereIn('softener_jobs.status', ['softened', 'squeezed']);
            })
            ->get();

        $machines = Machine::where('type', 'softening')
            ->where('status', 'available')
            ->get(['id', 'machine_no']);

        // Fetch softener supplies from manufacturing inventory
        $softenerSupplies = ManufacturingInventoryItem::with('material')
            ->where('department', 'dyeing')
            ->whereHas('material', fn($q) => $q->where('category', 'Supplies'))
            ->where('status', '!=', 'depleted')
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'control_number' => $item->control_number,
                'material_name' => $item->material->name ?? 'Unknown',
                'remaining_quantity' => $item->remaining_quantity,
                'unit' => $item->unit,
            ]);

        return Inertia::render('Dashboard/MAN/Employee/DyeingFabricSoftener/DyeingFabricSoftener', [
            'fabrics' => $fabrics,
            'machines' => $machines,
            'softenerSupplies' => $softenerSupplies,
        ]);
    }

    public function storeSoftener(Request $request)
    {
        $validated = $request->validate([
            'fabric_ids' => 'required|array|min:1',
            'fabric_ids.*' => 'exists:fabrics,id',
            'machine_id' => 'required|exists:machines,id',
            'softener_inventory_id' => 'required|exists:manufacturing_inventory_items,id',
            'softener_used' => 'required|numeric|min:0.01',
            'remarks' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $inventoryItem = ManufacturingInventoryItem::findOrFail($validated['softener_inventory_id']);

            // Check sufficient inventory
            if ($inventoryItem->remaining_quantity < $validated['softener_used']) {
                return back()->withErrors(['softener_used' => 'Insufficient softener quantity available.']);
            }

            // Consume softener
            $inventoryItem->decrement('remaining_quantity', $validated['softener_used']);
            if ($inventoryItem->remaining_quantity <= 0) {
                $inventoryItem->update(['status' => 'depleted']);
            }

            // Process each selected fabric
            foreach ($validated['fabric_ids'] as $fabricId) {
                $fabric = Fabric::findOrFail($fabricId);

                // Ensure fabric is in correct status
                if ($fabric->status !== 'softener') {
                    continue;
                }

                // Skip fabrics already recorded and awaiting QC; clear prior
                // checker-rejected ('resoften') rows so the redo replaces
                // them and the checker sees a single fresh job. Fabrics
                // holding any other job row (e.g. already squeezed) are not
                // re-workable here — they belong to a later gate.
                $existingStates = SoftenerJob::where('fabric_id', $fabric->id)->pluck('status')->all();
                if (in_array('softened', $existingStates, true) || in_array('squeezed', $existingStates, true)) {
                    continue;
                }
                SoftenerJob::where('fabric_id', $fabric->id)->where('status', 'resoften')->delete();

                // Create softener job
                SoftenerJob::create([
                    'fabric_id' => $fabric->id,
                    'machine_id' => $validated['machine_id'],
                    'softener_type' => $inventoryItem->material->name,
                    'softener_no' => $inventoryItem->control_number,
                    'remarks' => $validated['remarks'],
                    'operator_id' => $this->staff()->id,
                    'shift' => $this->getShift(),
                    'code' => $this->generateCode('SOFT', SoftenerJob::class),
                    'processed_at' => now(),
                    'status' => 'softened',
                ]);

                // IMPORTANT: Do NOT change fabric status here.
                // Fabric remains 'softener' until quality checker approves.
            }

            DB::commit();
            return redirect()->back()->with('message', 'Softening recorded successfully. Awaiting quality check.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to process softener: ' . $e->getMessage());
        }
    }

    public function reports()
    {
        $machines = Machine::where('type', 'softening')->get(['id', 'machine_no', 'status']);
        $myReports = MachineReport::where('reported_by', $this->staff()->id)
            ->with('machine')
            ->latest()
            ->get();

        return Inertia::render('Dashboard/MAN/Employee/DyeingFabricSoftener/Reports', [
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

        MachineReport::create([
            'machine_id' => $validated['machine_id'],
            'reported_by' => $this->staff()->id,
            'issue' => $validated['issue'],
            'status' => 'pending',
        ]);

        return redirect()->back()->with('message', 'Machine issue reported.');
    }
}
