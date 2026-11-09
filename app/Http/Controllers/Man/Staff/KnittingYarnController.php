<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\Fabric;
use App\Models\Man\KnittingMachineStaffAssignment;
use App\Models\Man\KnittingFlag;
use App\Models\Man\Machine;
use App\Models\Man\MachineReport;
use App\Models\Man\ManufacturingInventoryItem;
use App\Models\Man\ManTaskAssignment;
use App\Models\Man\KnittingMachineSetup;
use App\Models\Ord\SalesOrder;
use App\Models\Inv\Material;
use App\Models\Core\User;
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
                ['machines' => $this->machineAvailability(Machine::TYPE_CIRCULAR_KNITTING)]
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
        $machines = Machine::where('type', Machine::TYPE_CIRCULAR_KNITTING)
            ->where('status', 'available')
            ->whereIn('id', KnittingMachineStaffAssignment::where('staff_id', $this->staff()->id)->select('machine_id'))
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
                    'material_id'        => $item->material_id,
                    'control_number'     => $item->control_number,
                    'material_name'      => $item->material->display_name,
                    'remaining_quantity' => $item->remaining_quantity,
                    'unit'               => $item->unit,
                    'total_units'        => $item->total_units,
                    'used_units'         => $item->used_units,
                    'unit_type'          => $item->unit_type,
                    'unit_weight'        => $item->unit_weight ?: (($item->total_units > 0 && $item->initial_quantity > 0) ? $item->initial_quantity / $item->total_units : null),
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

        // Job orders in production — only those the knitting supervisor
        // assigned to this staff member; pending first, done last.
        $assignedSalesOrders = $this->myAssignmentScope()['so'];
        // Produced JOs leave this list — they are recorded in the work history
        // and now await the Knitting Inspector's quality gate. Only JOs still
        // needing knitting remain. If the supervisor rejects the output the
        // JO's knitting-done mark is cleared so it reappears here to be remade.
        $jobOrders = SalesOrder::with('recipe')
            ->where('status', 'in_production')
            ->whereIn('id', $assignedSalesOrders)
            ->whereNull('knitting_done_at')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($order) {
                $assignment = ManTaskAssignment::where('department', 'knitting')->where('stage', 'knitting')
                    ->where('assigned_to', $this->staff()->id)->where('sales_order_id', $order->id)
                    ->active()->latest()->first();
                if (! $assignment) return null;
                $assignment->load('dyeingRemakeRequest.fabrics.originalFabric', 'dyeingRemakeRequest.fabrics.replacementFabric');
                $remakeRequest = $assignment->dyeingRemakeRequest;
                $producedQuery = Fabric::where('sales_order_id', $order->id)->where('operator_id', $this->staff()->id)->where('status', '!=', 'rejected');
                if ($assignment->dyeing_remake_request_id) $producedQuery->where('dyeing_remake_request_id', $assignment->dyeing_remake_request_id);
                $producedWeight = (float) $producedQuery->sum('weight');
                $machineIds = array_map('intval', $assignment->machine_ids ?? []);
                $setupRows = KnittingMachineSetup::where('sales_order_id', $order->id)
                    ->whereIn('machine_id', $machineIds)->latest()->get()->unique('machine_id')->keyBy('machine_id');
                $rejectedByMachine = DB::table('knitting_inspections')
                    ->join('fabrics', 'fabrics.id', '=', 'knitting_inspections.fabric_id')
                    ->where('knitting_inspections.result', 'rejected')
                    ->where('fabrics.sales_order_id', $order->id)
                    ->where('fabrics.operator_id', $this->staff()->id)
                    ->whereIn('fabrics.machine_id', $machineIds)
                    ->select('fabrics.machine_id', DB::raw('MAX(knitting_inspections.created_at) as rejected_at'))
                    ->groupBy('fabrics.machine_id')->get()->keyBy('machine_id');
                $assignedMachines = Machine::whereIn('id', $machineIds)->orderBy('machine_no')->get(['id', 'machine_no'])
                    ->map(function ($machine) use ($setupRows, $rejectedByMachine) {
                        $setup = $setupRows->get($machine->id);
                        $latestRejected = $rejectedByMachine->get($machine->id);
                        $setupNeedsRedo = $latestRejected && (! $setup || ! $setup->processed_at || Carbon::parse($setup->processed_at)->lt($latestRejected->rejected_at));

                        return [
                            'id' => $machine->id,
                            'machine_no' => $machine->machine_no,
                            'setup_status' => $setupNeedsRedo ? 'needs_rework' : $setup?->status,
                            'setup_code' => $setup?->code,
                        ];
                    });
                $recipe        = $order->recipe;
                $requestedYarnName = null;
                $requestedYarnMaterialId = null;
                if ($recipe) {
                    $recipe->loadMissing('fabricSampleRequest');
                    $sampleRequest = $recipe->fabricSampleRequest ?: $recipe->sampleRequests()->latest('id')->first();
                    $requestedYarnName = trim((string) ($sampleRequest?->yarnTypeForSample() ?? '')) ?: null;
                    if ($requestedYarnName) {
                        $requestedYarnMaterialId = Material::where('category', 'Yarn')->get()
                            ->first(fn (Material $material) => mb_strtolower(trim($material->display_name)) === mb_strtolower($requestedYarnName))?->id;
                    }
                }
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
                                    'name'      => $mat->display_name,
                                    'base_name' => $mat->name,
                                    'yarn_tier' => $mat->yarn_tier,
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
                $rejectedCycles = Fabric::where('sales_order_id', $order->id)
                    ->where('operator_id', $this->staff()->id)->where('status', 'rejected')
                    ->with('machine')->latest('processed_at')->get()->map(function ($fabric) {
                        $inspection = DB::table('knitting_inspections')->where('fabric_id', $fabric->id)
                            ->where('result', 'rejected')->latest('created_at')->first();
                        return [
                            'code' => $fabric->code,
                            'weight' => (float) $fabric->weight,
                            'machine_no' => $fabric->machine?->machine_no,
                            'reason' => $inspection?->rejection_reason ?? $fabric->rejection_reason,
                        ];
                    })->values();
                $approvedWeight = (float) Fabric::where('manufacturing_order_id', $assignment->manufacturing_order_id)
                    ->where('status', 'knitting_approved')->sum('weight');
                $manufacturingOrder = $assignment->manufacturingOrder;

                return [
                    'id'               => $order->id,
                    'jo_number'        => $order->jo_number,
                    'control_number'   => $order->control_number,
                    'color'            => $order->color,
                    'quantity'         => $order->quantity,
                    'assigned_weight'  => (float) $assignment->assigned_weight,
                    'produced_weight'  => $producedWeight,
                    'remaining_assigned_weight' => max(0, round((float) $assignment->assigned_weight - $producedWeight, 2)),
                    'assignment_id'    => $assignment->id,
                    'deadline'         => $assignment->deadline?->toDateString(),
                    'remake_request_code' => $assignment->dyeingRemakeRequest?->code,
                    'remake_request_id' => $remakeRequest?->id,
                    'replacement_handoff_requested' => $remakeRequest?->status === 'awaiting_supervisor_review',
                    'can_submit_replacement_handoff' => $remakeRequest?->status === 'awaiting_staff_handoff'
                        && $remakeRequest->fabrics->isNotEmpty()
                        && $remakeRequest->fabrics->every(fn ($pair) => $pair->replacementFabric?->status === 'knitting_approved'),
                    'remake_fabrics' => $assignment->dyeingRemakeRequest?->fabrics?->map(fn($pair) => [
                        'id'=>$pair->id,'code'=>$pair->originalFabric?->code,
                        'yarn_type'=>$pair->originalFabric?->yarn_type,'weight'=>(float)($pair->originalFabric?->weight ?? 0),
                        'replacement_created'=>(bool)$pair->replacement_fabric_id,
                    ])->values() ?? [],
                    'assigned_machines' => $assignedMachines->values()->all(),
                    'yarn_type'        => $order->yarn_type,
                    'design'           => $order->design,
                    'unit_price'       => $order->unit_price,
                    'total_amount'     => $order->total_amount,
                    'is_knitting_done' => !is_null($order->knitting_done_at),
                    'handoff_requested' => !is_null($order->knitting_handoff_requested_at),
                    'approved_weight' => $approvedWeight,
                    'can_submit_handoff' => $manufacturingOrder
                        && $approvedWeight + 0.001 >= (float) $manufacturingOrder->total_quantity,
                    'knitting_done_at' => $formattedDoneAt,
                    'linked_fabrics'   => $linkedFabrics,
                    'rejected_cycles'  => $rejectedCycles,
                    'recipe'           => $recipe ? [
                        'id'           => $recipe->id,
                        'yarn_type'    => $recipe->yarn_type,
                        'dye_color'    => $recipe->dye_color,
                        'weave_design' => $recipe->weave_design,
                        'requested_yarn' => $requestedYarnName,
                        'requested_yarn_material_id' => $requestedYarnMaterialId,
                        'materials'    => $materialsData,
                        'created_at'   => $recipe->created_at?->format('M d, Y'),
                    ] : null,
                ];
            })->filter()->values();

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

        if (ManTaskAssignment::where('department', 'knitting')->where('stage', 'knitting')
            ->where('sales_order_id', $order->id)->whereNotNull('assigned_weight')->exists()) {
            return back()->withErrors(['error' => 'Planned knitting JOs are completed by inspector approvals and the supervisor handoff.']);
        }

        if (! in_array($order->id, $this->myAssignmentScope()['so'], true)) {
            return back()->withErrors(['error' => 'This Job Order is not assigned to you by your supervisor.']);
        }

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

    public function submitForSupervisor(SalesOrder $salesOrder)
    {
        abort_unless($salesOrder->status === 'in_production', 422, 'This Job Order is not in production.');
        $assignment = ManTaskAssignment::where('department', 'knitting')->where('stage', 'knitting')
            ->where('sales_order_id', $salesOrder->id)->where('assigned_to', $this->staff()->id)
            ->active()->latest()->firstOrFail();
        if ($assignment->dyeing_remake_request_id) {
            DB::transaction(function () use ($assignment) {
                $remake = \App\Models\Man\DyeingRemakeRequest::with('fabrics.replacementFabric')
                    ->lockForUpdate()->findOrFail($assignment->dyeing_remake_request_id);
                abort_unless($remake->status === 'awaiting_staff_handoff', 422, 'The replacement must be approved by the Knitting Inspector before supervisor handoff.');
                abort_unless($remake->fabrics->count() === 1
                    && $remake->fabrics->first()?->replacementFabric?->status === 'knitting_approved',
                    422, 'The approved replacement fabric for this request could not be found.');
                $remake->update(['status' => 'awaiting_supervisor_review']);
            });

            return back()->with('message', 'The approved replacement fabric was sent to the Knitting Supervisor for review.');
        }

        abort_if($salesOrder->knitting_handoff_requested_at, 422, 'This Job Order was already sent to the supervisor.');

        $order = $assignment->manufacturingOrder;
        abort_unless($order, 422, 'The manufacturing Job Order could not be found.');
        $approvedWeight = (float) Fabric::where('manufacturing_order_id', $order->id)
            ->where('status', 'knitting_approved')->sum('weight');
        abort_unless($approvedWeight + 0.001 >= (float) $order->total_quantity, 422,
            'The Knitting Inspector must approve the full JO weight before you send it to the supervisor.');

        $salesOrder->update([
            'knitting_handoff_requested_at' => now(),
            'knitting_handoff_requested_by' => $this->staff()->id,
        ]);

        return back()->with('message', "JO {$salesOrder->jo_number} sent to the knitting supervisor for review.");
    }

    /**
     * Undo the knitting-done mark and unlink all fabrics from this JO.
     */
    public function unmarkDone(Request $request, $id)
    {
        $order = SalesOrder::where('status', 'in_production')->findOrFail($id);

        if (ManTaskAssignment::where('department', 'knitting')->where('stage', 'knitting')
            ->where('sales_order_id', $order->id)->whereNotNull('assigned_weight')->exists()) {
            return back()->withErrors(['error' => 'Planned knitting JOs are reopened by the inspector when output is rejected.']);
        }

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
            'sales_order_id'                  => 'required|exists:sales_orders,id',
            'machine_id'                     => 'required|exists:machines,id',
            'yarn_type'                      => 'required|string|max:255',
            'weight'                         => 'required|numeric|min:0.01',
            'remarks'                        => 'nullable|string',
            'yarns_used'                     => 'required|array|min:1',
            'yarns_used.*.inventory_item_id' => 'required|exists:manufacturing_inventory_items,id',
            'yarns_used.*.weight_kg'         => 'required|numeric|min:0.01',
        ]);

        $assignment = ManTaskAssignment::where('department', 'knitting')->where('stage', 'knitting')
            ->where('sales_order_id', $validated['sales_order_id'])->where('assigned_to', $this->staff()->id)
            ->active()->latest()->firstOrFail();
        $salesOrder = SalesOrder::with(['recipe.fabricSampleRequest', 'recipe.sampleRequests'])
            ->findOrFail($validated['sales_order_id']);
        $sampleRequest = $salesOrder->recipe?->fabricSampleRequest
            ?: $salesOrder->recipe?->sampleRequests?->sortByDesc('id')->first();
        $requestedYarnName = trim((string) ($sampleRequest?->yarnTypeForSample() ?? ''));
        if ($requestedYarnName === '') {
            return back()->withErrors(['yarns_used' => 'The linked client sample request does not specify a yarn. Ask the supervisor to confirm the requested yarn before recording production.']);
        }
        $requestedYarnMaterial = Material::where('category', 'Yarn')->get()
            ->first(fn (Material $material) => mb_strtolower(trim($material->display_name)) === mb_strtolower($requestedYarnName));
        if (! $requestedYarnMaterial) {
            return back()->withErrors(['yarns_used' => 'The client-requested yarn "'.$requestedYarnName.'" does not match a yarn material in Inventory. Ask the supervisor to verify it.']);
        }
        $machineId = (int) $validated['machine_id'];
        if (! in_array($machineId, array_map('intval', $assignment->machine_ids ?? []), true)) {
            return back()->withErrors(['machine_id' => 'This machine is not assigned to you for this Job Order.']);
        }
        $setup = KnittingMachineSetup::where('sales_order_id', $validated['sales_order_id'])
            ->where('machine_id', $machineId)->latest()->first();
        $latestRejectedAt = DB::table('knitting_inspections')
            ->join('fabrics', 'fabrics.id', '=', 'knitting_inspections.fabric_id')
            ->where('knitting_inspections.result', 'rejected')->where('fabrics.sales_order_id', $validated['sales_order_id'])
            ->where('fabrics.operator_id', $this->staff()->id)->where('fabrics.machine_id', $machineId)
            ->max('knitting_inspections.created_at');
        if (! $setup || $setup->status !== 'done' || ($latestRejectedAt && (! $setup->processed_at || Carbon::parse($setup->processed_at)->lt($latestRejectedAt)))) {
            return back()->withErrors(['machine_id' => 'The mechanic must finish setup for this machine and JO before production.']);
        }
        $insufficient = [];
        foreach ($validated['yarns_used'] as $usage) {
            $item      = ManufacturingInventoryItem::find($usage['inventory_item_id']);
            if ((int) $item->material_id !== (int) $requestedYarnMaterial->id) {
                return back()->withErrors(['yarns_used' => 'Use only the client-requested yarn: '.$requestedYarnName.'.']);
            }
            $availableKg = (float) $item->remaining_quantity;
            if ($availableKg + 0.00001 < (float) $usage['weight_kg']) {
                $insufficient[] = "{$item->material->display_name} ({$item->control_number}) — only ".number_format($availableKg, 2).' kg left';
            }
        }
        if (!empty($insufficient)) {
            return back()->withErrors(['yarns_used' => 'Insufficient yarn: ' . implode(', ', $insufficient)]);
        }

        DB::beginTransaction();
        try {
            $lockedAssignment = ManTaskAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $replacement = null;
            if ($lockedAssignment->dyeing_remake_request_id) {
                $pendingReplacements = \App\Models\Man\DyeingRemakeRequestFabric::with('originalFabric')
                    ->where('dyeing_remake_request_id', $lockedAssignment->dyeing_remake_request_id)
                    ->whereNull('replacement_fabric_id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($pendingReplacements->isEmpty()) {
                    throw new \RuntimeException('This replacement request already has a fabric recorded. Ask the Knitting Supervisor to review the existing roll.');
                }

                // A request for one rejected fabric has one unambiguous target;
                // link its replacement even if the produced weight differs a
                // little from the original. Keep attribute matching for legacy
                // multi-fabric requests where the target must be identified.
                $replacement = $pendingReplacements->count() === 1
                    ? $pendingReplacements->first()
                    : $pendingReplacements->first(fn ($pair) => $pair->originalFabric
                        && mb_strtolower(trim((string) $pair->originalFabric->yarn_type)) === mb_strtolower(trim((string) $validated['yarn_type']))
                        && abs((float) $pair->originalFabric->weight - (float) $validated['weight']) <= 0.01);

                if (! $replacement) {
                    throw new \RuntimeException('This yarn type and weight do not match any remaining fabric on the replacement request.');
                }
            }

            $fabric = Fabric::create([
                'code'                   => $this->generateCode('FABRIC', Fabric::class),
                'manufacturing_order_id' => $lockedAssignment->manufacturing_order_id,
                'sales_order_id'         => $validated['sales_order_id'],
                'dyeing_remake_request_id' => $lockedAssignment->dyeing_remake_request_id,
                'machine_id'             => $validated['machine_id'],
                'yarn_type'              => $validated['yarn_type'],
                'weight'                 => $validated['weight'],
                'remarks'                => $validated['remarks'],
                'operator_id'            => $this->staff()->id,
                'shift'                  => $this->getShift(),
                'processed_at'           => now(),
                'status'                 => 'pending',
            ]);

            if ($replacement) {
                $replacement->update(['replacement_fabric_id'=>$fabric->id]);
                $remake = \App\Models\Man\DyeingRemakeRequest::with('fabrics')->findOrFail($lockedAssignment->dyeing_remake_request_id);
                if ($remake->fabrics->every(fn($pair) => $pair->replacement_fabric_id)) {
                    $remake->update(['status'=>'awaiting_inspection']);
                }
            }

            foreach ($validated['yarns_used'] as $usage) {
                $item     = ManufacturingInventoryItem::findOrFail($usage['inventory_item_id']);
                $consumed = $item->consumeWeight((float) $usage['weight_kg']);
                if (!$consumed) {
                    throw new \Exception("Failed to consume units for {$item->material->display_name} ({$item->control_number})");
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

    public function requestSetup(Request $request, SalesOrder $salesOrder)
    {
        $validated = $request->validate(['machine_id' => 'required|exists:machines,id']);
        $assignment = ManTaskAssignment::where('department', 'knitting')->where('stage', 'knitting')
            ->where('sales_order_id', $salesOrder->id)->where('assigned_to', $this->staff()->id)
            ->active()->firstOrFail();
        $machineId = (int) $validated['machine_id'];
        abort_unless(in_array($machineId, array_map('intval', $assignment->machine_ids ?? []), true), 403);

        $openRequest = KnittingMachineSetup::where('sales_order_id', $salesOrder->id)
            ->where('machine_id', $machineId)->where('requested_by', $this->staff()->id)
            ->whereIn('status', ['pending', 'in_progress'])->exists();
        if ($openRequest) {
            return back()->with('message', 'A mechanic setup request is already open for this machine.');
        }

        $mechanic = User::where('role', 'MAN')->where('manufacturing_role', 'knitting_mechanic')
            ->where('is_active', 1)->orderBy('id')->first();
        if (! $mechanic) {
            return back()->withErrors(['machine_id' => 'No active Knitting Mechanic is available to receive the request.']);
        }

        KnittingMachineSetup::create([
            'code' => $this->generateCode('KSET', KnittingMachineSetup::class),
            'machine_id' => $machineId,
            'sales_order_id' => $salesOrder->id,
            'task_type' => 'setup',
            'settings' => ['pattern_ref' => $salesOrder->design],
            'status' => 'pending',
            'remarks' => 'Setup requested by '.$this->staff()->name.' for JO '.$salesOrder->jo_number.'. Mechanic: confirm the design and enter the approved GSM before setup completion.',
            'operator_id' => $mechanic->id,
            'requested_by' => $this->staff()->id,
            'shift' => $this->getShift(),
            'processed_at' => now(),
        ]);

        return back()->with('message', 'Knitting Mechanic assistance requested for machine '.$machineId.'.');
    }

    public function reports()
    {
        $machines  = Machine::where('type', Machine::TYPE_CIRCULAR_KNITTING)->get(['id', 'machine_no', 'status']);
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
        $machines = Machine::where('type', Machine::TYPE_CIRCULAR_KNITTING)
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
            if ($machine->type !== Machine::TYPE_CIRCULAR_KNITTING) {
                return back()->withErrors(['machine_id' => 'Only Circular Knitting machines belong to Knitting Yarn.']);
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
