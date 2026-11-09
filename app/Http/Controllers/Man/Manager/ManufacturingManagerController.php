<?php

namespace App\Http\Controllers\Man\Manager;

use App\Http\Controllers\Controller;
use App\Models\Man\Fabric;
use App\Models\Man\DyeJob;
use App\Models\Man\DyeingBatchFabric;
use App\Models\Man\DyeingRemakeRequest;
use App\Models\Inv\Material;
use App\Models\Man\Machine;
use App\Models\Man\BoilerLog;
use App\Models\Man\KnittingMachineSetup;
use App\Models\Man\LabDipRequest;
use App\Models\Man\MachineReport;
use App\Models\Man\PcoRecord;
use App\Models\Man\SafetyIncident;
use App\Models\Vp\VpBulletin;
use App\Models\Man\ShiftHandover;
use App\Models\Man\IronJob;
use App\Models\Man\ManufacturingOrder;
use App\Models\Man\ManTaskAssignment;
use App\Models\Man\Package;
use App\Models\Man\SqueezerJob;
use App\Models\Man\FinishingWorkOrder;
use App\Models\Ord\OrderQueue;
use App\Models\Ord\PurchaseOrder;
use App\Models\Ord\SalesOrder;
use App\Models\Core\User;
use App\Models\War\Warehouse;
use App\Models\War\WarehouseReject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ManufacturingManagerController extends Controller
{
    /**
     * Get the current authenticated department supervisor.
     * There is no manufacturing manager — each of the 4 supervisors
     * (knitting, dyeing, finishing, maintenance) oversees their own department.
     */
    protected function getCurrentManager()
    {
        return Auth::user();
    }

    /**
     * Filter staff list based on supervisor's department.
     * Excludes the logged-in supervisor from the list.
     * Non-supervisors see nobody (no manager-wide view exists anymore).
     */
    protected function getFilteredStaff()
    {
        $user = $this->getCurrentManager();

        // Base query: MAN staff with position 'staff'
        $query = User::where('role', 'MAN')
            ->where('position', 'staff')
            ->select('id', 'name', 'email', 'manufacturing_role', 'is_manufacturing_supervisor');

        // Manufacturing supervisors see only their department's staff, excluding self
        if ($user->is_manufacturing_supervisor && $user->supervisor_department) {
            $supervisedRoles = $user->supervised_roles;
            $query->whereIn('manufacturing_role', $supervisedRoles)
                  ->where('id', '!=', $user->id);
        } else {
            // No manufacturing manager exists — non-supervisors get an empty list
            $query->whereRaw('1 = 0');
        }

        return $query->get()->map(fn($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'manufacturing_role' => $u->manufacturing_role,
            'is_manufacturing_supervisor' => $u->is_manufacturing_supervisor,
        ]);
    }

    public function index()
    {
        $user = $this->getCurrentManager();

        $receivedOrders = PurchaseOrder::whereHas('queue', function ($q) {
            $q->where('stage', 'man_production');
        })->count()
            // Job Orders pushed from SCM (sales orders) land in the same queue.
            + OrderQueue::where('stage', 'man_production')
                ->whereNotNull('sales_order_id')
                ->count();

        $inProduction = ManufacturingOrder::where('status', 'in_progress')->count();
        $activeMachines = Machine::where('status', 'available')->count();

        $staff = $this->getFilteredStaff();

        return Inertia::render('Dashboard/MAN/Manager/index', [
            'stats' => [
                'receivedOrders' => $receivedOrders,
                'inProduction' => $inProduction,
                'activeMachines' => $activeMachines,
            ],
            'staff' => $staff,
            // Department overview for the logged-in supervisor (BC-safe extras)
            'department' => $user->supervisor_department,
            'departmentStats' => $this->getDepartmentStats($user),
            // Shift handover continuity + VP bulletins for this department
            'recentHandovers' => $user->supervisor_department
                ? ShiftHandover::with('author:id,name')
                    ->where('department', $user->supervisor_department)
                    ->latest()->take(5)->get()
                : [],
            'activeBulletins' => VpBulletin::where(function ($q) use ($user) {
                    $q->where('audience', 'company');
                    if ($user->supervisor_department) {
                        $q->orWhere('audience', $user->supervisor_department);
                    }
                })->where(function ($q) {
                    $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', today());
                })->latest()->take(5)->get(),
        ]);
    }

    /**
     * Log a shift handover for the supervisor's own department.
     */
    public function storeHandover(Request $request)
    {
        $user = $this->getCurrentManager();

        if (! $user->is_manufacturing_supervisor || ! $user->supervisor_department) {
            abort(403, 'Only department supervisors can log handovers.');
        }

        $validated = $request->validate([
            'shift_date' => 'required|date',
            'shift_type' => 'nullable|string|max:64',
            'unfinished_work' => 'nullable|string',
            'machine_notes' => 'nullable|string',
            'hot_jobs' => 'nullable|string',
            'safety_notes' => 'nullable|string',
        ]);

        ShiftHandover::create([
            ...$validated,
            'department' => $user->supervisor_department,
            'author_id' => $user->id,
        ]);

        return redirect()->back()->with('message', 'Shift handover logged for ' . $user->supervisor_department . '.');
    }

    /**
     * Department-scoped overview counts for the supervisor's own department.
     */
    protected function getDepartmentStats($user): array
    {
        return match ($user->supervisor_department) {
            'knitting' => [
                'pending_fabrics' => Fabric::where('status', 'pending')->count(),
                'fabrics_today' => Fabric::whereDate('processed_at', today())->count(),
                'pending_setups' => KnittingMachineSetup::whereIn('status', ['pending', 'in_progress'])->count(),
            ],
            'dyeing' => [
                'in_dyeing' => Fabric::where('status', 'dyeing')->count(),
                'in_bleaching' => Fabric::where('status', 'bleaching')->count(),
                'pending_lab_dips' => LabDipRequest::whereIn('status', ['pending', 'in_progress', 'awaiting_spectro'])->count(),
            ],
            'finishing' => [
                'in_squeezer' => Fabric::where('status', 'squeezer')->count(),
                'in_iron' => Fabric::where('status', 'iron')->count(),
                'ready_to_pack' => Fabric::where('status', 'packed')->count(),
                'packages_pending' => Package::where('status', 'pending')->count(),
                'rejected_fabrics' => Fabric::where('status', 'rejected')->count(),
            ],
            'maintenance' => [
                'pending_reports' => MachineReport::where('status', 'pending')->count(),
                'machines_down' => Machine::where('status', '!=', 'available')->count(),
                'total_machines' => Machine::count(),
                'open_incidents' => SafetyIncident::whereIn('status', ['open', 'investigating'])->count(),
                'non_compliant' => PcoRecord::where('compliant', false)->count(),
            ],
            'boiler' => [
                'logs_today' => BoilerLog::whereDate('processed_at', today())->count(),
                'boilers_down' => Machine::where('type', 'boiler')->where('status', '!=', 'available')->count(),
                'total_boilers' => Machine::where('type', 'boiler')->count(),
            ],
            default => [],
        };
    }

    public function production(Request $request)
    {
        $user = $this->getCurrentManager();

        // Supervisors act on their own department; CEO / oversight may inspect
        // any department via ?department=…  A null department shows the full
        // pipeline (oversight view).
        $department = ($user->is_manufacturing_supervisor && $user->supervisor_department)
            ? $user->supervisor_department
            : $request->query('department');

        if (! in_array($department, ['knitting', 'dyeing', 'finishing'], true)) {
            $department = null;
        }

        $finishingReadyOrders = FinishingWorkOrder::with(['salesOrder', 'manufacturingOrder', 'items.fabric'])
            ->whereIn('status', ['ready_logistics', 'packing'])
            ->latest()
            ->get()
            ->map(function (FinishingWorkOrder $workOrder) {
                $items = $workOrder->items->map(fn ($item) => [
                    'code' => $item->fabric?->code,
                    'weight' => (float) ($item->fabric?->weight ?? 0),
                ])->filter(fn ($item) => $item['code'])->values();

                return [
                    'id' => $workOrder->id,
                    'jo_number' => $workOrder->salesOrder?->jo_number ?? $workOrder->manufacturingOrder?->code,
                    'items' => $items,
                    'total_weight_kg' => round($items->sum('weight'), 3),
                ];
            })
            ->filter(fn ($workOrder) => $workOrder['items']->isNotEmpty())
            ->values();

        $fabrics = Fabric::with('machine', 'operator', 'salesOrder')
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();

        $dyeingStages = collect();
        if ($department === 'dyeing') {
            $dyeingStages = DyeingBatchFabric::with([
                'fabric.manufacturingOrder',
                'fabric.salesOrder.recipe',
                'batch.manufacturingOrder',
                'batch.machine',
                'batch.operator',
                'batch.fabrics.fabric',
                'batch.fabrics.records',
                'records.operator',
            ])->whereIn('current_stage', ['bleaching', 'pre_treatment', 'color', 'post_treatment'])
                ->where('status', 'awaiting_review')->latest()->get()->map(function (DyeingBatchFabric $item) {
                    $record = $item->records->first(fn ($record) => $record->stage === $item->current_stage && $record->review_status === 'awaiting_review');
                    $order = $item->fabric?->manufacturingOrder ?? $item->batch?->manufacturingOrder;
                    $salesOrder = $item->fabric?->salesOrder;
                    $formula = $order?->dyeing_production_recipe;
                    $recipeSource = $formula ? 'Production order recipe' : null;
                    if (! $formula) {
                        $formula = $salesOrder?->recipe?->dyeing_formula;
                        $recipeSource = $formula ? 'Sample recipe (production order recipe not set)' : null;
                    }
                    $weight = (float) ($item->fabric?->weight ?? 0);
                    $liquorRatio = (float) ($formula['liquor_ratio'] ?? 10);
                    $sampleWeightGrams = max(0.0001, (float) ($formula['sample_weight_g'] ?? 100));
                    $recipeGroups = [];
                    $groupDefinitions = [
                        'dyestuffs' => ['aliases' => ['dyestuffs', 'dye_stuffs'], 'dose' => 'pct'],
                        'basic_chemicals' => ['aliases' => ['basic_chemicals', 'basic_chemical'], 'dose' => 'gpl'],
                        'auxiliary_agents' => ['aliases' => ['auxiliary_agents', 'auxiliaries', 'auxiliary'], 'dose' => 'gpl'],
                        'post_treatment' => ['aliases' => ['post_treatment', 'post_treatment_agents'], 'dose' => 'gpl'],
                    ];
                    foreach ($groupDefinitions as $group => $definition) {
                        $rows = collect($definition['aliases'])->flatMap(fn ($key) => $formula[$key] ?? [])->values();
                        $materialIds = $rows->pluck('material_id')->filter()->unique();
                        $materials = $materialIds->isEmpty() ? collect() : Material::whereIn('id', $materialIds)->get()->keyBy('id');
                        $recipeGroups[$group] = $rows->map(function ($row) use ($definition, $weight, $liquorRatio, $sampleWeightGrams, $materials) {
                            $dose = array_key_exists($definition['dose'], $row)
                                ? (float) $row[$definition['dose']]
                                : (array_key_exists('grams', $row)
                                    ? ((float) $row['grams'] / $sampleWeightGrams * ($definition['dose'] === 'pct' ? 100 : 1000 / $liquorRatio))
                                    : (float) ($row['quantity'] ?? 0));
                            $amount = $definition['dose'] === 'pct' ? $weight * $dose / 100 : $dose * $liquorRatio * $weight / 1000;
                            $material = $materials->get($row['material_id'] ?? null);
                            return [
                                'name' => $material?->display_name ?? $row['name'] ?? $row['material_name'] ?? 'Material',
                                'dose' => $dose,
                                'dose_unit' => $definition['dose'] === 'pct' ? '% OWF' : 'g/L',
                                'amount_kg' => round($amount, 3),
                            ];
                        })->all();
                    }
                    $stageFabrics = $item->batch?->fabrics ?? collect();
                    $passedCount = $stageFabrics->filter(fn ($fabric) => $fabric->records->contains(fn ($stageRecord) =>
                        $stageRecord->stage === $item->current_stage && $stageRecord->review_status === 'approved'
                    ))->count();
                    $batchFabricRows = $stageFabrics->map(fn ($batchFabric) => [
                        'code' => $batchFabric->fabric?->code,
                        'weight' => (float) ($batchFabric->fabric?->weight ?? 0),
                        'current_stage' => $batchFabric->current_stage,
                        'status' => $batchFabric->status,
                    ])->values();

                    return [
                        'id' => $item->id,
                        'record_id' => $record?->id,
                        'fabric_code' => $item->fabric?->code,
                        'weight' => $weight,
                        'jo_number' => $salesOrder?->jo_number,
                        'yarn_type' => $item->fabric?->yarn_type,
                        'color' => $salesOrder?->recipe?->dye_color ?? $salesOrder?->color,
                        'current_stage' => $item->current_stage,
                        'requires_bleaching' => (bool) $item->requires_bleaching,
                        'batch_code' => $item->batch?->code,
                        'machine_no' => $item->batch?->machine?->machine_no,
                        'operator' => $record?->operator?->name ?? $item->batch?->operator?->name,
                        'review_status' => $record?->review_status,
                        'attempt' => $record?->attempt,
                        'stage_progress' => ['passed' => $passedCount, 'total' => $stageFabrics->count()],
                        'batch_fabrics' => $batchFabricRows,
                        'batch_total_weight_kg' => round($batchFabricRows->sum('weight'), 3),
                        'recipe_source' => $recipeSource,
                        'recipe_groups' => $recipeGroups,
                    ];
                })->values();
        }

        $squeezerJobs = SqueezerJob::with('fabric.salesOrder', 'machine', 'operator', 'ironJob')
            ->whereHas('fabric', fn($q) => $q->where('status', 'squeezer'))
            ->get();

        $ironJobs = IronJob::with('squeezerJob.fabric.salesOrder', 'operator')
            ->whereHas('squeezerJob.fabric', fn($q) => $q->where('status', 'iron'))
            ->get();

        // Inspector handles 'pending'; the finishing supervisor pushes 'checked'.
        $packages = Package::with(['fabric.salesOrder.recipe.product', 'operator'])
            ->whereIn('status', ['pending', 'checked'])
            ->orderBy('created_at')
            ->get()
            ->map(function ($pkg) {
                $product = $pkg->fabric->salesOrder->recipe->product ?? null;
                return [
                    'id' => $pkg->id,
                    'code' => $pkg->code,
                    'quantity' => $pkg->quantity,
                    'status' => $pkg->status,
                    'product_name' => $product->name ?? 'Unknown Product',
                    'product_sku' => $product->sku ?? '',
                    'fabric_code' => $pkg->fabric->code ?? '',
                    'yarn_type' => $pkg->fabric->yarn_type ?? '',
                    'weight' => $pkg->fabric->weight ?? 0,
                    'operator' => $pkg->operator->name ?? '',
                ];
            });

        $knittingHandoffs = collect();
        $knittingReplacementHandoffs = collect();
        if ($department === 'knitting') {
            $knittingHandoffs = SalesOrder::with('client')
                ->whereNotNull('knitting_handoff_requested_at')
                ->whereHas('activeManufacturingOrder', fn ($query) => $query->whereHas('fabrics', fn ($fabrics) =>
                    $fabrics->where('status', 'knitting_approved')))
                ->latest('knitting_handoff_requested_at')
                ->get()
                ->map(function (SalesOrder $salesOrder) {
                    $order = $salesOrder->activeManufacturingOrder;
                    $fabrics = Fabric::where('manufacturing_order_id', $order->id)
                        ->where('status', 'knitting_approved')->get();
                    $allocations = ManTaskAssignment::with('assignee:id,name')
                        ->where('manufacturing_order_id', $order->id)
                        ->where('department', 'knitting')->where('stage', 'knitting')
                        ->whereIn('status', ['pending', 'in_progress'])->get();

                    return [
                        'id' => $order->id,
                        'jo_number' => $salesOrder->jo_number,
                        'client' => $salesOrder->client?->company_name ?? 'N/A',
                        'total_quantity' => (float) $order->total_quantity,
                        'approved_weight' => (float) $fabrics->sum('weight'),
                        'submitted_at' => $salesOrder->knitting_handoff_requested_at?->format('M d, Y h:i A'),
                        'staff' => $allocations->map(fn ($allocation) => $allocation->assignee?->name)->filter()->unique()->values(),
                        'fabrics' => $fabrics->map(fn ($fabric) => [
                            'id' => $fabric->id,
                            'code' => $fabric->code,
                            'weight' => (float) $fabric->weight,
                            'operator' => $fabric->operator?->name,
                        ])->values(),
                    ];
                })->values();

            $knittingReplacementHandoffs = DyeingRemakeRequest::with([
                'salesOrder:id,jo_number',
                'assignee:id,name',
                'fabrics.originalFabric:id,code,weight,yarn_type',
                'fabrics.replacementFabric:id,code,weight,yarn_type,status,operator_id',
                'fabrics.replacementFabric.operator:id,name',
            ])->where('status', 'awaiting_supervisor_review')->latest()->get()
                ->map(fn (DyeingRemakeRequest $remake) => [
                    'id' => $remake->id,
                    'code' => $remake->code,
                    'jo_number' => $remake->salesOrder?->jo_number,
                    'staff' => $remake->assignee?->name,
                    'reason' => $remake->reason,
                    'original_fabric' => $remake->fabrics->first()?->originalFabric ? [
                        'code' => $remake->fabrics->first()->originalFabric->code,
                        'weight' => (float) $remake->fabrics->first()->originalFabric->weight,
                        'yarn_type' => $remake->fabrics->first()->originalFabric->yarn_type,
                    ] : null,
                    'replacement_fabric' => $remake->fabrics->first()?->replacementFabric ? [
                        'id' => $remake->fabrics->first()->replacementFabric->id,
                        'code' => $remake->fabrics->first()->replacementFabric->code,
                        'weight' => (float) $remake->fabrics->first()->replacementFabric->weight,
                        'yarn_type' => $remake->fabrics->first()->replacementFabric->yarn_type,
                        'operator' => $remake->fabrics->first()->replacementFabric->operator?->name,
                    ] : null,
                ])->values();
        }

        return Inertia::render('Dashboard/MAN/Manager/Production', [
            'department' => $department,
            'fabrics' => $fabrics,
            'dyeingStages' => $dyeingStages,
            'squeezerJobs' => $squeezerJobs,
            'ironJobs' => $ironJobs,
            'packages' => $packages,
            'finishingReadyOrders' => $finishingReadyOrders,
            'knittingHandoffs' => $knittingHandoffs,
            'knittingReplacementHandoffs' => $knittingReplacementHandoffs,
        ]);
    }

    /**
     * Show rejected items: both fabrics and form jobs.
     */
   public function rejected()
{
    $user = $this->getCurrentManager();

    // Rejected Fabrics
    $rejectedFabricsQuery = Fabric::with('salesOrder')
        ->where('status', 'rejected');

    if ($user->is_manufacturing_supervisor && $user->supervisor_department) {
        $supervisedRoles = $user->supervised_roles;
        $rejectedFabricsQuery->whereHas('operator', function ($q) use ($supervisedRoles) {
            $q->whereIn('manufacturing_role', $supervisedRoles);
        });
    }

    $rejectedFabrics = $rejectedFabricsQuery->latest()->get()->map(function ($fabric) {
        return [
            'id'             => $fabric->id,
            'code'           => $fabric->code,
            'product_name'   => $fabric->yarn_type ?? 'Fabric',
            'quantity'       => $fabric->weight ?? 1,
            'rejected_by'    => $fabric->operator->name ?? 'System',
            'reason'         => $fabric->rejection_reason ?? 'No reason',
            'rejected_at'    => $fabric->updated_at,
            'type'           => 'fabric',
        ];
    });

    // Forming stage removed: rejected items are fabrics only.
    $rejectedItems = $rejectedFabrics
        ->sortByDesc('rejected_at')
        ->values();

    // Get warehouses for total reject modal
    $warehouses = Warehouse::select('id', 'name', 'location')->get();

    return Inertia::render('Dashboard/MAN/Manager/Rejected', [
        'rejectedItems' => $rejectedItems,
        'warehouses'    => $warehouses,
    ]);
}

    /**
     * Recolor a rejected fabric (send back to dyeing stage).
     */
    public function recolorFabric($fabricId)
    {
        $fabric = Fabric::findOrFail($fabricId);
        
        if ($fabric->status !== 'rejected') {
            return back()->with('error', 'Fabric is not in rejected state.');
        }

        DB::transaction(function () use ($fabric) {
            // Redo replaces the failed QC rows: drop this fabric's dye jobs
            // so it re-queues on the dyeing page (fabrics with a recorded
            // dye job are otherwise excluded as "awaiting quality check").
            // Downstream rows cannot exist here — bleaching/squeezer/iron
            // work all require later fabric statuses.
            DyeJob::where('fabric_id', $fabric->id)->delete();

            $fabric->update([
                'status' => 'dyeing',
                'rejection_action' => 'recolor',
            ]);
        });

        return redirect()->back()->with('message', 'Fabric sent back for recoloring.');
    }

    /**
     * Totally reject a fabric and send to warehouse as rejected material.
     */
    public function rejectFabricTotally(Request $request, $fabricId)
    {
        $fabric = Fabric::findOrFail($fabricId);
        
        if ($fabric->status !== 'rejected') {
            return back()->with('error', 'Fabric is not in rejected state.');
        }

        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        DB::transaction(function () use ($fabric, $validated) {
            // Update fabric
            $fabric->update([
                'rejection_action' => 'total',
                'status' => 'warehouse_rejected', // or keep 'rejected' but mark action
            ]);

            // Create warehouse reject record
            WarehouseReject::create([
                'rejectable_type' => Fabric::class,
                'rejectable_id' => $fabric->id,
                'source' => 'manufacturing',
                'warehouse_id' => $validated['warehouse_id'],
                'quantity' => $fabric->weight,
                'unit' => 'kg',
                'reason' => $fabric->rejection_reason ?? 'Rejected from manufacturing',
                'status' => 'pending_return',
            ]);
        });

        return redirect()->back()->with('message', 'Fabric sent to warehouse as rejected material.');
    }

    /**
     * Forward an order (Purchase Order or Sales Order) to the Finishing Quality Checker.
     * Creates a ManufacturingOrder record and updates the order's stage/status.
     */
    public function forwardToChecker(Request $request, $orderId)
    {
        $type = $request->input('type', 'purchase_order');
        $user = Auth::user();

        DB::beginTransaction();
        try {
            if ($type === 'purchase_order') {
                $order = PurchaseOrder::findOrFail($orderId);

                // Check if already forwarded
                $existing = ManufacturingOrder::where('purchase_order_id', $order->id)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->first();
                if ($existing) {
                    return redirect()->back()->with('error', 'This Purchase Order has already been forwarded to production.');
                }

                $totalQuantity = $order->items->sum('quantity');

                ManufacturingOrder::create([
                    'purchase_order_id' => $order->id,
                    'total_quantity'    => $totalQuantity,
                    'remaining_quantity'=> $totalQuantity,
                    'status'            => 'pending',
                    'notes'             => 'Forwarded to manufacturing by ' . $user->name,
                ]);

                // Optionally update queue stage? Already man_production, keep as is.

            } elseif ($type === 'sales_order') {
                $order = SalesOrder::findOrFail($orderId);
                $totalQuantity = $order->quantity ?? 0;

                // Check if already forwarded
                $existing = ManufacturingOrder::where('sales_order_id', $order->id)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->first();
                if ($existing) {
                    return redirect()->back()->with('error', 'This Job Order has already been forwarded to production.');
                }

                ManufacturingOrder::create([
                    'sales_order_id'     => $order->id,
                    'total_quantity'     => $totalQuantity,
                    'remaining_quantity' => $totalQuantity,
                    'status'             => 'pending',
                    'notes'              => 'Forwarded to manufacturing by ' . $user->name,
                ]);

                // Status remains 'in_production' (already set by SCM push)
            } else {
                abort(400, 'Invalid order type.');
            }

            DB::commit();
            return redirect()->back()->with('message', 'Order forwarded to production successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to forward order: ' . $e->getMessage());
        }
    }

    public function updateStaffRole(Request $request, $staffId)
    {
        $user = $this->getCurrentManager();
        $staff = User::findOrFail($staffId);

        if ($user->is_manufacturing_supervisor) {
            if (!$user->canSuperviseRole($staff->manufacturing_role)) {
                return back()->with('error', 'You are not allowed to update this staff member.');
            }
        }

        $validated = $request->validate([
            'manufacturing_role' => 'required|in:knitting_yarn,knitting_mechanic,knitting_inspector,knitting_sample,dyeing_color,dyeing_bleaching,dyeing_pre_treatment,dyeing_treatment,dyeing_lab_chemist,finishing_squeezer,finishing_ironing,finishing_hydro,finishing_tubetex,finishing_packaging,finishing_inspector,maintenance_checker,pollution_control_operator,safety_officer,boiler_operator,checker_quality',
        ]);

        // Supervisors may only move staff within their own department —
        // check both the current role and the requested new role.
        if ($user->is_manufacturing_supervisor
            && (! $user->canSuperviseRole($validated['manufacturing_role']))) {
            return back()->with('error', 'You can only assign roles within your own department.');
        }

        $staff->update(['manufacturing_role' => $validated['manufacturing_role']]);
        return redirect()->back()->with('message', "Staff role updated.");
    }
}
