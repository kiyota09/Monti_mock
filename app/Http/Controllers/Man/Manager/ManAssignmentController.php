<?php

namespace App\Http\Controllers\Man\Manager;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Man\Fabric;
use App\Models\Man\DyeingBatchFabric;
use App\Models\Man\FinishingWorkItem;
use App\Models\Man\FinishingWorkOrder;
use App\Models\Man\KnittingMachineStaffAssignment;
use App\Models\Man\ManTaskAssignment;
use App\Models\Man\Machine;
use App\Models\Man\ManufacturingOrder;
use App\Models\Man\Package;
use App\Models\Inv\Material;
use App\Support\ManufacturingRoles;
use App\Services\Man\BoilerSteamService;
use App\Services\Man\DyeingInitialBatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Supervisor â†’ staff Job Order assignment board.
 *
 * A supervisor receives a JO for their department and assigns the specific
 * stage to a specific staff member so two people never work the same JO.
 */
class ManAssignmentController extends Controller
{
    /**
     * Resolve the department this request acts on: the supervisor's own
     * department, or (for CEO / oversight) an explicit `department` request.
     */
    protected function resolveDepartment(Request $request): ?string
    {
        $user = Auth::user();

        if ($user->is_manufacturing_supervisor && $user->supervisor_department) {
            return $user->supervisor_department;
        }

        $requested = $request->input('department') ?? $request->query('department');

        return in_array($requested, ManufacturingRoles::PRODUCTION_DEPARTMENTS, true)
            ? $requested
            : null;
    }

    protected function staffForDepartment(?string $department)
    {
        $roles = ManufacturingRoles::rolesForDepartment($department);

        $staff = User::where('role', 'MAN')
            ->whereIn('manufacturing_role', $roles)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'manufacturing_role', 'profile_photo_path']);

        $loads = ManTaskAssignment::query()
            ->whereIn('assigned_to', $staff->pluck('id'))
            ->active()
            ->selectRaw('assigned_to, COUNT(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        return $staff
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'manufacturing_role' => $member->manufacturing_role,
                'role_label' => ManufacturingRoles::label($member->manufacturing_role),
                'photo' => $member->profile_photo_path
                    ? '/storage/'.ltrim($member->profile_photo_path, '/')
                    : null,
                'active_load' => (int) ($loads[$member->id] ?? 0),
            ])
            ->sortBy([
                ['active_load', 'asc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    /**
     * Fabrics belonging to a Job Order. They link either by
     * manufacturing_order_id or, once the knitting staff mark the JO done,
     * by sales_order_id.
     */
    protected function fabricsForOrder(ManufacturingOrder $order)
    {
        $moId = $order->id;
        $salesOrderId = $order->sales_order_id;

        return Fabric::query()->where(function ($q) use ($moId, $salesOrderId) {
            if ($moId) {
                $q->where('manufacturing_order_id', $moId);
                if ($salesOrderId) {
                    $q->orWhere('sales_order_id', $salesOrderId);
                }
            } elseif ($salesOrderId) {
                $q->where('sales_order_id', $salesOrderId);
            } else {
                $q->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Packages belonging to a Job Order â€” linked directly by
     * manufacturing_order_id or through one of its fabrics.
     */
    protected function packagesForOrder(ManufacturingOrder $order)
    {
        $fabricIds = $this->fabricsForOrder($order)->pluck('id');

        return Package::query()->where(function ($q) use ($order, $fabricIds) {
            $q->where('manufacturing_order_id', $order->id);
            if ($fabricIds->isNotEmpty()) {
                $q->orWhereIn('fabric_id', $fabricIds);
            }
        });
    }

    /**
     * Stages that may currently be assigned for a Job Order. The dyeing
     * department is strictly sequential (colour â†’ bleaching â†’ finishing
     * squeezer â†’ ironing), so a stage opens only once a fabric for the JO has reached the
     * required status â€” e.g. bleaching staff cannot be assigned before the
     * colour process is done. The finishing department likewise cannot hand a
     * JO to the Finishing Quality Checker before the finishing packaging process has
     * produced a package (packaging â†’ quality check).
     *
     * @return array<int, string>
     */
    protected function availableStagesForOrder(ManufacturingOrder $order, string $department): array
    {
        $allStages = array_keys(ManufacturingRoles::stagesForDepartment($department));

        if ($department === 'dyeing') {
            if ($order->dyeing_bleaching_required === null) {
                return [];
            }

            $stageByCurrentStage = [
                'bleaching' => 'dyeing_bleaching',
                'pre_treatment' => 'dyeing_pre_treatment',
                'color' => 'dyeing_color',
                'post_treatment' => 'dyeing_treatment',
            ];
            $available = DyeingBatchFabric::query()
                ->whereIn('fabric_id', $this->fabricsForOrder($order)->select('id'))
                ->where('status', 'active')
                ->pluck('current_stage')->map(fn ($stage) => $stageByCurrentStage[$stage] ?? null)
                ->filter()->unique()->values()->all();

            $hasUnbatchedFabrics = $this->fabricsForOrder($order)
                ->whereIn('status', ['dyeing', 'bleaching'])
                ->whereDoesntHave('dyeingBatchFabric')->exists();
            if ($hasUnbatchedFabrics) {
                $available[] = $order->dyeing_bleaching_required
                    ? 'dyeing_bleaching'
                    : 'dyeing_pre_treatment';
            }

            return array_values(array_intersect($allStages, array_unique($available)));
        }

        if ($department === 'finishing') {
            $workOrder = FinishingWorkOrder::where('manufacturing_order_id', $order->id)->first();
            if (! $workOrder) {
                $hasUnclaimedRolls = $this->fabricsForOrder($order)->where('status', 'squeezer')
                    ->whereDoesntHave('finishingWorkItem')->exists();
                return $hasUnclaimedRolls
                    ? array_values(array_intersect($allStages, ['water_extraction_squeezer', 'water_extraction_hydro']))
                    : [];
            }

            if ($workOrder->status === 'awaiting_drying_assignment') {
                return array_values(array_intersect($allStages, ['drying_heatsetting', 'drying_tubetex']));
            }

            return [];
        }

        $statuses = $this->fabricsForOrder($order)->pluck('status')->unique()->all();

        return array_values(array_filter($allStages, function ($stage) use ($order, $statuses) {
            $required = ManufacturingRoles::requiredFabricStatusForStage($stage);
            if ($required !== null && ! in_array($required, $statuses, true)) {
                return false;
            }

            return match ($stage) {
                // Packaging needs a packed fabric that has no package yet.
                'finishing_packaging' => $this->fabricsForOrder($order)
                    ->where('status', 'packed')
                    ->whereDoesntHave('packages')
                    ->exists(),

                // Quality check needs a package awaiting inspection, which only
                // exists once the packaging process has been done.
                'quality_check' => $this->packagesForOrder($order)
                    ->where('status', 'pending')
                    ->exists(),

                default => true,
            };
        }));
    }

    /**
     * Does this Job Order currently have real work waiting for the given
     * department? Derived from the physical fabric flow so a JO is never shown
     * to downstream departments before work reaches them:
     *   knitting  â†’ no fabric produced yet, or fabrics still at the knitting gate
     *   dyeing    â†’ fabrics that have entered colour/bleaching
     *   finishing â†’ fabrics in squeezer/ironing/packaging or open packages
     */
    protected function departmentHasWork(ManufacturingOrder $order, string $department): bool
    {
        $fabricQuery = fn () => $this->fabricsForOrder($order);

        return match ($department) {
            'knitting' => ($fabricQuery())->whereIn('status', ['pending', 'knitting_approved', 'rejected'])->exists()
                || ! ($fabricQuery())->exists(),

            'dyeing' => ($fabricQuery())
                ->whereIn('status', ['dyeing', 'bleaching'])
                ->exists(),

            'finishing' => ($fabricQuery())
                ->whereIn('status', ['squeezer', 'iron', 'packed'])
                ->where(function ($fabrics) {
                    $fabrics->whereIn('status', ['squeezer', 'iron'])
                        ->orWhere(function ($packed) {
                            $packed->where('status', 'packed')->whereDoesntHave('packages');
                        });
                })
                ->exists()
                || $this->packagesForOrder($order)
                    ->whereIn('status', ['pending', 'checked'])
                    ->exists(),

            default => false,
        };
    }

    public function index(Request $request)
    {
        // Supervisors are pinned to their department; oversight may switch via
        // ?department=â€¦ and otherwise lands on knitting.
        $department = $this->resolveDepartment($request)
            ?? $request->query('department')
            ?? 'knitting';

        if (! in_array($department, ManufacturingRoles::PRODUCTION_DEPARTMENTS, true)) {
            $department = 'knitting';
        }

        $stages = ManufacturingRoles::stagesForDepartment($department);
        $stageKeys = array_keys($stages);

        $assignments = ManTaskAssignment::with(['assignee:id,name', 'assigner:id,name', 'fabric:id,code,weight'])
            ->where('department', $department)
            ->latest()
            ->get();

        $knittingMachines = collect();
        $allocatedMachineIds = [];
        if ($department === 'knitting') {
            $machineDirectory = Machine::where('type', 'knitting')->pluck('machine_no', 'id');
            $assignments->each(fn ($assignment) => $assignment->setAttribute(
                'machine_numbers',
                collect($assignment->machine_ids ?? [])->map(fn ($id) => $machineDirectory->get($id))->filter()->values()->all()
            ));
            $allocatedMachineIds = ManTaskAssignment::where('department', 'knitting')
                ->active()->get(['machine_ids'])->flatMap(fn ($assignment) => $assignment->machine_ids ?? [])
                ->map(fn ($id) => (int) $id)->unique()->all();
            $knittingMachines = Machine::where('type', 'knitting')->where('status', 'available')
                ->with('knittingStaffAssignment:id,machine_id,staff_id')->orderBy('machine_no')->get(['id', 'machine_no'])
                ->filter(fn ($machine) => ! in_array($machine->id, $allocatedMachineIds, true))
                ->map(fn ($machine) => [
                    'id' => $machine->id,
                    'machine_no' => $machine->machine_no,
                    'staff_id' => $machine->knittingStaffAssignment?->staff_id,
                ])
                ->values();
        }

        // Which stages are already assigned, per Job Order. A JO stays on the
        // board until EVERY stage in this department has an active assignment
        // (dyeing: colour â†’ bleaching; finishing: squeezer â†’ ironing â†’ packaging).
        $assignedByOrder = ManTaskAssignment::where('department', $department)
            ->active()
            ->whereNotNull('manufacturing_order_id')
            ->get(['manufacturing_order_id', 'stage'])
            ->groupBy('manufacturing_order_id');

        $orders = ManufacturingOrder::with(['purchaseOrder.client', 'purchaseOrder.items', 'salesOrder.client', 'salesOrder.recipe.product'])
            ->whereIn('status', ['pending', 'in_progress'])
            ->get()
            // A department only sees a JO once work has actually reached it â€”
            // knitting is the entry point; dyeing appears only after a fabric is
            // passed from knitting; finishing appears once a fabric is bleached.
            ->filter(fn ($order) => $this->departmentHasWork($order, $department))
            ->map(function ($order) use ($assignedByOrder, $stageKeys, $department, $assignments) {
                $assignedStages = $assignedByOrder->get($order->id, collect())
                    ->pluck('stage')->unique()->values()->all();
                $orderAssignments = $assignments->where('manufacturing_order_id', $order->id)
                    ->where('stage', 'knitting')->where('status', '!=', 'rejected')->values();
                $plannedWeight = (float) $orderAssignments->sum(fn ($assignment) => (float) $assignment->assigned_weight);
                $fabricQuery = $this->fabricsForOrder($order);
                $activeFabricAssignments = $department === 'dyeing'
                    ? ManTaskAssignment::with('assignee:id,name')->where('manufacturing_order_id', $order->id)->where('department', 'dyeing')->active()->get(['fabric_id', 'stage', 'assigned_to'])
                    : collect();
                $orderFabricRows = $department === 'dyeing'
                    ? (clone $fabricQuery)->with('dyeingBatchFabric')->orderBy('code')->get()->map(fn ($fabric) => [
                        'id' => $fabric->id,
                        'code' => $fabric->code,
                        'weight' => (float) $fabric->weight,
                        'stage' => $fabric->dyeingBatchFabric?->current_stage ?? $fabric->status,
                        'status' => $fabric->dyeingBatchFabric?->status ?? $fabric->status,
                        'assignment_stage' => $fabric->dyeingBatchFabric
                            ? (['bleaching' => 'dyeing_bleaching', 'pre_treatment' => 'dyeing_pre_treatment', 'color' => 'dyeing_color', 'post_treatment' => 'dyeing_treatment'][$fabric->dyeingBatchFabric->current_stage] ?? null)
                            : (in_array($fabric->status, ['dyeing', 'bleaching'], true)
                                ? ($order->dyeing_bleaching_required ? 'dyeing_bleaching' : 'dyeing_pre_treatment')
                                : null),
                        'ready_for_assignment' => $fabric->dyeingBatchFabric
                            ? $fabric->dyeingBatchFabric->status === 'active'
                            : in_array($fabric->status, ['dyeing', 'bleaching'], true),
                        'assigned_to' => $activeFabricAssignments->first(fn ($assignment) => (int) $assignment->fabric_id === (int) $fabric->id
                            && $assignment->stage === ($fabric->dyeingBatchFabric
                                ? (['bleaching' => 'dyeing_bleaching', 'pre_treatment' => 'dyeing_pre_treatment', 'color' => 'dyeing_color', 'post_treatment' => 'dyeing_treatment'][$fabric->dyeingBatchFabric->current_stage] ?? null)
                                : (in_array($fabric->status, ['dyeing', 'bleaching'], true) ? ($order->dyeing_bleaching_required ? 'dyeing_bleaching' : 'dyeing_pre_treatment') : null)))?->assigned_to,
                        'assigned_staff' => $activeFabricAssignments->first(fn ($assignment) => (int) $assignment->fabric_id === (int) $fabric->id
                            && $assignment->stage === ($fabric->dyeingBatchFabric
                                ? (['bleaching' => 'dyeing_bleaching', 'pre_treatment' => 'dyeing_pre_treatment', 'color' => 'dyeing_color', 'post_treatment' => 'dyeing_treatment'][$fabric->dyeingBatchFabric->current_stage] ?? null)
                                : (in_array($fabric->status, ['dyeing', 'bleaching'], true) ? ($order->dyeing_bleaching_required ? 'dyeing_bleaching' : 'dyeing_pre_treatment') : null)))?->assignee?->name,
                    ])->values()
                    : collect();
                $producedWeight = (float) (clone $fabricQuery)->where('status', '!=', 'rejected')->sum('weight');
                $approvedWeight = (float) (clone $fabricQuery)->where('status', 'knitting_approved')->sum('weight');
                // Only stages whose prerequisite work is physically done can be
                // assigned (dyeing: colour before bleaching; then finishing stages).
                $assignable = $this->availableStagesForOrder($order, $department);
                $remaining = $department === 'knitting'
                    ? ($plannedWeight + 0.001 < (float) $order->total_quantity ? ['knitting'] : [])
                    : ($department === 'dyeing'
                        ? array_values(array_filter($assignable, fn ($stage) => $orderFabricRows->contains(fn ($fabric) =>
                            $fabric['assignment_stage'] === $stage && $fabric['ready_for_assignment'] && $fabric['assigned_to'] === null
                        ) && ! $activeFabricAssignments->contains(fn ($assignment) => $assignment->fabric_id === null && $assignment->stage === $stage)))
                        : array_values(array_intersect(array_diff($stageKeys, $assignedStages), $assignable)));

                $salesOrder = $order->salesOrder;
                $purchaseOrder = $order->purchaseOrder;
                $items = $purchaseOrder?->items->map(fn ($item) => [
                    'fabric' => $item->fabric,
                    'design' => $item->design,
                    'color' => $item->color,
                    'quantity' => (float) $item->quantity,
                    'kilos' => $item->kilos !== null ? (float) $item->kilos : null,
                ])->values()->all() ?? [];

                $expectedShipDate = $salesOrder?->expected_ship_date
                    ?? $purchaseOrder?->expected_ship_date;
                $recipe = $salesOrder?->recipe;
                $recipeMaterials = is_array($recipe?->materials) ? $recipe->materials : [];
                $recipeMaterialRows = collect();
                if ($recipeMaterials) {
                    $recipeMaterialRows = Material::whereIn('id', array_keys($recipeMaterials))->get()
                        ->keyBy('id')->map(fn ($material, $id) => [
                            'id' => $material->id,
                            'mat_id' => $material->mat_id,
                            'name' => $material->display_name ?? $material->name,
                            'quantity' => $recipeMaterials[$id] ?? null,
                            'unit' => $material->unit,
                        ])->values();
                }

                return [
                    'id' => $order->id,
                    'reference' => $salesOrder?->jo_number
                        ?? $purchaseOrder?->po_number
                        ?? ('MO-'.$order->id),
                    'client' => $salesOrder?->client?->company_name
                        ?? $purchaseOrder?->client?->company_name
                        ?? 'N/A',
                    'total_quantity' => $order->total_quantity,
                    'remaining_quantity' => $order->remaining_quantity,
                    'assigned_weight' => round($plannedWeight, 2),
                    'remaining_assignment_weight' => max(0, round((float) $order->total_quantity - $plannedWeight, 2)),
                    'produced_weight' => round($producedWeight, 2),
                    'approved_weight' => round($approvedWeight, 2),
                    'fabric_count' => $orderFabricRows->count(),
                    'fabric_total_weight' => round($orderFabricRows->sum('weight'), 2),
                    'fabric_codes' => $orderFabricRows,
                    'handoff_requested' => (bool) $salesOrder?->knitting_handoff_requested_at,
                    'dyeing_bleaching_required' => $order->dyeing_bleaching_required,
                    'dyeing_route_locked' => DyeingBatchFabric::whereHas('fabric', fn ($query) => $query->where('manufacturing_order_id', $order->id))->exists(),
                    'knitting_allocations' => $department === 'knitting' ? $orderAssignments->map(fn ($assignment) => [
                        'id' => $assignment->id,
                        'staff_id' => $assignment->assigned_to,
                        'staff_name' => $assignment->assignee?->name,
                        'assigned_weight' => (float) $assignment->assigned_weight,
                        'machine_ids' => $assignment->machine_ids ?? [],
                        'machine_count' => count($assignment->machine_ids ?? []),
                        'machine_numbers' => Machine::whereIn('id', $assignment->machine_ids ?? [])->orderBy('machine_no')->pluck('machine_no')->all(),
                        'status' => $assignment->status,
                    ])->all() : [],
                    'status' => $order->status,
                    'assigned_stages' => $assignedStages,
                    'remaining_stages' => $remaining,
                    'source' => $salesOrder ? 'sales_order' : ($purchaseOrder ? 'purchase_order' : 'manufacturing_order'),
                    'source_label' => $salesOrder ? 'Sales Order' : ($purchaseOrder ? 'Purchase Order' : 'Manufacturing Order'),
                    'jo_number' => $salesOrder?->jo_number,
                    'po_number' => $salesOrder?->purchase_order_id ?? $purchaseOrder?->po_number,
                    'control_number' => $salesOrder?->control_number ?? $purchaseOrder?->control_number,
                    'color' => $salesOrder?->color,
                    'yarn_type' => $salesOrder?->yarn_type ?? $purchaseOrder?->yarn,
                    'design' => $salesOrder?->design,
                    'priority' => $salesOrder?->priority ?? $purchaseOrder?->priority,
                    'order_status' => $salesOrder?->status ?? $purchaseOrder?->status,
                    'expected_ship_date' => $expectedShipDate?->toDateString(),
                    'ordered_at' => $order->created_at?->toDateString(),
                    'notes' => $order->notes,
                    'order_notes' => $purchaseOrder?->notes,
                    'items' => $items,
                    'recipe' => $recipe ? [
                        'id' => $recipe->id,
                        'product' => $recipe->product?->name,
                        'yarn_type' => $recipe->yarn_type,
                        'dye_color' => $recipe->dye_color,
                        'weave_design' => $recipe->weave_design,
                        'materials' => $recipeMaterialRows,
                    ] : null,
                    'unit_price' => $salesOrder?->unit_price,
                    'total_amount' => $salesOrder?->total_amount ?? $purchaseOrder?->total_amount,
                ];
            })
            // Drop JOs whose whole department flow is already assigned.
            ->filter(fn ($order) => ($department === 'knitting'
                    && $order['remaining_assignment_weight'] > 0.001)
                || ($department === 'dyeing' && $order['dyeing_bleaching_required'] === null)
                || ($department !== 'knitting' && count($order['remaining_stages']) > 0))
            ->values();

        return Inertia::render('Dashboard/MAN/Manager/Assignments', [
            'department' => $department,
            'departmentLabel' => ManufacturingRoles::departmentLabel($department),
            'departments' => ManufacturingRoles::PRODUCTION_DEPARTMENTS,
            'canSwitchDepartment' => ! (Auth::user()->is_manufacturing_supervisor && Auth::user()->supervisor_department),
            'stages' => $stages,
            'staff' => $this->staffForDepartment($department),
            'orders' => $orders,
            'assignments' => $assignments,
            'knittingMachines' => $knittingMachines,
            'prefillOrderId' => $request->query('order_id'),
            'prefillStage' => $request->query('stage'),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $department = $this->resolveDepartment($request);

        if (! $department) {
            return back()->with('error', 'Select a production department first.');
        }

        $validated = $request->validate([
            'manufacturing_order_id' => 'required|exists:manufacturing_orders,id',
            'stage' => 'required|string',
            'assigned_to' => 'required|exists:users,id',
            'notes' => 'nullable|string|max:500',
            'assigned_weight' => $department === 'knitting' ? 'required|numeric|min:0.01' : 'nullable|numeric|min:0.01',
            'machine_ids' => $department === 'knitting' ? 'required|array|min:1|max:10' : 'nullable|array',
            'machine_ids.*' => 'required|integer|distinct|exists:machines,id',
            'fabric_ids' => $department === 'dyeing' ? 'required|array|min:1' : 'nullable|array',
            'fabric_ids.*' => $department === 'dyeing' ? 'required|integer|distinct|exists:fabrics,id' : 'nullable|integer',
        ]);

        $stages = ManufacturingRoles::stagesForDepartment($department);
        if (! array_key_exists($validated['stage'], $stages)) {
            return back()->with('error', 'That stage does not belong to the selected department.');
        }

        $assignedRole = $stages[$validated['stage']];

        $steamMachineType = match (true) {
            $department === 'dyeing' && $validated['stage'] === 'dyeing_color' => 'dyeing',
            $department === 'finishing' && $validated['stage'] === 'finishing_tubetex' => 'tubetex',
            default => null,
        };
        if ($steamMachineType && Machine::where('type', $steamMachineType)->where('power', 'Steam')->exists()
            && ! app(BoilerSteamService::class)->isAvailable()) {
            return back()->withErrors(['assigned_to' => 'Boiler steam is not available yet. The Boiler Operator must record an operating boiler and mark steam available first.']);
        }

        $assignee = User::where('id', $validated['assigned_to'])
            ->where('manufacturing_role', $assignedRole)
            ->first();

        if (! $assignee) {
            return back()->with('error', 'The selected staff member cannot perform that stage.');
        }

        if ($department !== 'knitting') {
            $alreadyQuery = ManTaskAssignment::where('manufacturing_order_id', $validated['manufacturing_order_id'])
                ->where('department', $department)->where('stage', $validated['stage'])->active();
            if ($department === 'dyeing') {
                $legacyAssignmentExists = (clone $alreadyQuery)->whereNull('fabric_id')->exists();
                $assignedFabricIds = (clone $alreadyQuery)->whereIn('fabric_id', $validated['fabric_ids'])->pluck('fabric_id')->all();
                if ($legacyAssignmentExists || $assignedFabricIds) {
                    return back()->with('error', 'One or more selected fabric codes are already assigned for this stage. Refresh the page and choose unassigned codes.');
                }
            } elseif ($alreadyQuery->exists()) {
                return back()->with('error', 'This stage is already assigned for that Job Order.');
            }
        }

        $order = ManufacturingOrder::find($validated['manufacturing_order_id']);

        if ($department === 'knitting' && (! $order || ! $order->sales_order_id)) {
            return back()->withErrors(['manufacturing_order_id' => 'Knitting work plans require a Job Order linked to a Sales Order.']);
        }

        // Enforce the physical sequence before assigning downstream work: the
        // stage must be currently available for the JO (dyeing: colour before
        // bleaching; finishing: squeezer/ironing/packaging before quality check).
        if (! $order || ! in_array($validated['stage'], $this->availableStagesForOrder($order, $department), true)) {
            return back()->with('error', 'This Job Order cannot be assigned to that stage yet â€” the previous process step has not been completed.');
        }

        if ($department === 'dyeing') {
            $selectedFabrics = $this->fabricsForOrder($order)->with('dyeingBatchFabric')->whereIn('id', $validated['fabric_ids'])->get();
            if ($selectedFabrics->count() !== count($validated['fabric_ids'])) {
                return back()->withErrors(['fabric_ids' => 'Choose fabric codes that belong to this Job Order.']);
            }
            $currentStageByAssignment = [
                'bleaching' => 'dyeing_bleaching', 'pre_treatment' => 'dyeing_pre_treatment',
                'color' => 'dyeing_color', 'post_treatment' => 'dyeing_treatment',
            ];
            foreach ($selectedFabrics as $fabric) {
                $batchFabric = $fabric->dyeingBatchFabric;
                $fabricAssignmentStage = $batchFabric
                    ? ($currentStageByAssignment[$batchFabric->current_stage] ?? null)
                    : (in_array($fabric->status, ['dyeing', 'bleaching'], true)
                        ? ($order->dyeing_bleaching_required ? 'dyeing_bleaching' : 'dyeing_pre_treatment')
                        : null);
                $isReady = $batchFabric ? $batchFabric->status === 'active'
                    : in_array($fabric->status, ['dyeing', 'bleaching'], true);
                if ($fabricAssignmentStage !== $validated['stage'] || ! $isReady) {
                    return back()->withErrors(['fabric_ids' => 'Every selected code must be ready for the selected stage. Fabrics awaiting supervisor review cannot be assigned forward.']);
                }
            }
        }

        $machineIds = [];
        if ($department === 'knitting') {
            $machineIds = array_map('intval', $validated['machine_ids']);
            $validMachineCount = Machine::where('type', 'knitting')->where('status', 'available')->whereIn('id', $machineIds)->count();
            if ($validMachineCount !== count($machineIds)) {
                return back()->withErrors(['machine_ids' => 'Choose available knitting machines only.']);
            }

            $staffMachineIds = KnittingMachineStaffAssignment::where('staff_id', $assignee->id)->pluck('machine_id')
                ->map(fn ($id) => (int) $id)->all();
            if (array_diff($machineIds, $staffMachineIds)) {
                return back()->withErrors(['machine_ids' => 'Choose machines assigned to this Knitting Yarn staff member on the Knitting Machines page.']);
            }

            $currentlyAllocated = ManTaskAssignment::where('department', 'knitting')->active()
                ->where('assigned_to', $assignee->id)->where('manufacturing_order_id', $order->id)->exists();
            if ($currentlyAllocated) {
                return back()->withErrors(['assigned_to' => 'This staff member already has an active allocation for this Job Order.']);
            }

            $otherAllocations = ManTaskAssignment::where('department', 'knitting')->active()
                ->where('manufacturing_order_id', $order->id)->where('stage', 'knitting')->get();
            $plannedWeight = (float) $otherAllocations->sum('assigned_weight');
            if ($plannedWeight + (float) $validated['assigned_weight'] > (float) $order->total_quantity + 0.001) {
                return back()->withErrors(['assigned_weight' => 'The staff allocations cannot exceed the Job Order quantity.']);
            }

            $allocatedIds = $otherAllocations->flatMap(fn ($assignment) => $assignment->machine_ids ?? [])
                ->merge(ManTaskAssignment::where('department', 'knitting')->active()
                ->where('manufacturing_order_id', '!=', $order->id)->get(['machine_ids'])
                ->flatMap(fn ($assignment) => $assignment->machine_ids ?? []))
                ->map(fn ($id) => (int) $id)->all();
            if (array_intersect($machineIds, $allocatedIds)) {
                return back()->withErrors(['machine_ids' => 'A knitting machine is already assigned to another active operator.']);
            }
        }

        $sequence = array_search($validated['stage'], array_keys($stages), true);

        $assignmentData = [
            'manufacturing_order_id' => $validated['manufacturing_order_id'],
            'sales_order_id' => $order?->sales_order_id,
            'department' => $department,
            'stage' => $validated['stage'],
            'assigned_role' => $assignedRole,
            'assigned_to' => $assignee->id,
            'assigned_by' => $user->id,
            'status' => 'pending',
            'sequence' => $sequence === false ? 0 : $sequence,
            'notes' => $validated['notes'] ?? null,
            'assigned_weight' => $department === 'knitting' ? $validated['assigned_weight'] : null,
            'machine_ids' => $department === 'knitting' ? $machineIds : null,
        ];
        if ($department === 'dyeing') {
            DB::transaction(function () use ($assignmentData, $validated, $order, $assignee, $user) {
                foreach ($validated['fabric_ids'] as $fabricId) {
                    ManTaskAssignment::create($assignmentData + ['fabric_id' => (int) $fabricId]);
                }
                app(DyeingInitialBatchService::class)->prepare(
                    $order,
                    $validated['stage'],
                    (int) $assignee->id,
                    (int) $user->id,
                    array_map('intval', $validated['fabric_ids']),
                );
            });
        } elseif ($department === 'finishing') {
            DB::transaction(function () use ($assignmentData, $order, $assignee, $validated) {
                ManTaskAssignment::create($assignmentData);
                $this->syncFinishingAssignment($order, $validated['stage'], $assignee);
            });
        } else {
            ManTaskAssignment::create($assignmentData);
        }

        return back()->with('message', 'Job Order assigned to '.$assignee->name.'.');
    }

    private function syncFinishingAssignment(ManufacturingOrder $order, string $stage, User $assignee): void
    {
        $extractionStages = [
            'water_extraction_squeezer' => 'squeezer',
            'water_extraction_hydro' => 'hydro',
        ];
        $dryingStages = [
            'drying_heatsetting' => 'heatsetting',
            'drying_tubetex' => 'tubetex',
        ];

        if (isset($extractionStages[$stage])) {
            abort_unless($order->sales_order_id, 422, 'This Job Order needs a linked Sales Order before Finishing can start.');
            $workOrder = FinishingWorkOrder::where('manufacturing_order_id', $order->id)->lockForUpdate()->first();
            abort_if($workOrder, 422, 'A Finishing workflow already exists for this Job Order.');
            $fabrics = $this->fabricsForOrder($order)->where('status', 'squeezer')
                ->whereDoesntHave('finishingWorkItem')->lockForUpdate()->get();
            abort_if($fabrics->isEmpty(), 422, 'No Dyeing-approved fabric codes are ready for Water Extraction.');

            $workOrder = FinishingWorkOrder::create([
                'manufacturing_order_id' => $order->id,
                'sales_order_id' => $order->sales_order_id,
                'extraction_method' => $extractionStages[$stage],
                'extraction_assigned_to' => $assignee->id,
                'status' => 'extraction',
            ]);
            foreach ($fabrics as $fabric) {
                FinishingWorkItem::create([
                    'finishing_work_order_id' => $workOrder->id,
                    'fabric_id' => $fabric->id,
                    'status' => 'extraction',
                ]);
            }

            return;
        }

        if (isset($dryingStages[$stage])) {
            $workOrder = FinishingWorkOrder::where('manufacturing_order_id', $order->id)->lockForUpdate()->firstOrFail();
            abort_unless($workOrder->status === 'awaiting_drying_assignment', 422, 'All Water Extraction fabric codes must finish before Drying can be assigned.');
            $workOrder->update([
                'drying_method' => $dryingStages[$stage],
                'drying_assigned_to' => $assignee->id,
                'status' => 'drying',
            ]);
            $workOrder->items()->where('status', 'extraction_done')->update(['status' => 'drying']);
        }
    }

    public function updateDyeingRoute(Request $request, ManufacturingOrder $order)
    {
        abort_unless($this->resolveDepartment($request) === 'dyeing', 403);
        $validated = $request->validate(['requires_bleaching' => 'required|boolean']);
        abort_if(DyeingBatchFabric::whereHas('fabric', fn ($query) => $query->where('manufacturing_order_id', $order->id))->exists(),
            422, 'The Dyeing route cannot be changed after a batch has been created for this JO.');
        abort_if($order->dyeing_bleaching_required !== null, 422, 'A Dyeing route has already been selected for this JO.');

        $order->update(['dyeing_bleaching_required' => (bool) $validated['requires_bleaching']]);

        return back()->with('message', $validated['requires_bleaching']
            ? 'Bleaching has been added to this JO route.'
            : 'This JO will go directly to Pre Treatment.');
    }

    public function releaseKnitting(Request $request, ManufacturingOrder $order)
    {
        if ($this->resolveDepartment($request) !== 'knitting') {
            abort(403, 'Only the knitting supervisor can release this Job Order.');
        }
        $salesOrder = $order->salesOrder;
        if (! $salesOrder) {
            return back()->withErrors(['release' => 'This knitting Job Order is not linked to a sales order.']);
        }
        if (! $salesOrder->knitting_handoff_requested_at) {
            return back()->withErrors(['release' => 'Knitting Yarn staff must send this JO to the supervisor before it can be released to Dyeing.']);
        }

        $allocations = ManTaskAssignment::where('manufacturing_order_id', $order->id)
            ->where('department', 'knitting')->where('stage', 'knitting')->where('status', '!=', 'rejected')->get();
        $planned = (float) $allocations->sum('assigned_weight');
        $approved = (float) $this->fabricsForOrder($order)->where('status', 'knitting_approved')->sum('weight');
        if ($planned + 0.001 < (float) $order->total_quantity) {
            return back()->withErrors(['release' => 'Allocate the full JO quantity to knitting yarn staff before release.']);
        }
        if ($approved + 0.001 < (float) $order->total_quantity) {
            return back()->withErrors(['release' => 'The Knitting Inspector must approve the full JO weight before it can move to Dyeing.']);
        }

        $this->fabricsForOrder($order)->where('status', 'knitting_approved')->update(['status' => 'dyeing']);
        $salesOrder->update(['knitting_done_at' => now(), 'knitting_done_by' => Auth::id()]);
        ManTaskAssignment::whereIn('id', $allocations->pluck('id'))->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return back()->with('message', "JO {$salesOrder->jo_number} released to the Dyeing Department.");
    }

    public function declineKnittingHandoff(Request $request, ManufacturingOrder $order)
    {
        abort_unless($this->resolveDepartment($request) === 'knitting', 403);
        $validated = $request->validate(['reason' => 'required|string|max:500']);
        $salesOrder = $order->salesOrder;
        abort_unless($salesOrder?->knitting_handoff_requested_at, 422, 'This Job Order has not been submitted for supervisor review.');

        $fabrics = $this->fabricsForOrder($order)->where('status', 'knitting_approved')->get();
        abort_if($fabrics->isEmpty(), 422, 'There are no inspector-approved knitting fabrics to return for remake.');

        DB::transaction(function () use ($fabrics, $salesOrder, $validated) {
            foreach ($fabrics as $fabric) {
                $fabric->update([
                    'status' => 'rejected',
                    'rejection_action' => null,
                    'rejection_reason' => $validated['reason'],
                ]);
            }

            $salesOrder->update([
                'knitting_done_at' => null,
                'knitting_done_by' => null,
                'knitting_handoff_requested_at' => null,
                'knitting_handoff_requested_by' => null,
            ]);
        });

        return back()->with('message', "JO {$salesOrder->jo_number} declined. The assigned Knitting Yarn staff can remake the returned fabric.");
    }

    public function update(Request $request, ManTaskAssignment $assignment)
    {
        $user = Auth::user();
        $department = $this->resolveDepartment($request);

        if ($assignment->department !== $department) {
            return back()->with('error', 'That assignment is outside your department.');
        }

        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
            'status' => 'nullable|in:pending,in_progress,completed,rejected',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($assignment->department === 'knitting' && $assignment->assigned_weight !== null
            && isset($validated['status']) && ! in_array($validated['status'], ['pending', 'in_progress'], true)) {
            return back()->withErrors(['status' => 'Planned knitting allocations complete only after inspector approval and supervisor handoff.']);
        }

        if (! empty($validated['assigned_to'])) {
            $assignee = User::where('id', $validated['assigned_to'])
                ->where('manufacturing_role', $assignment->assigned_role)
                ->first();

            if (! $assignee) {
                return back()->with('error', 'The selected staff member cannot perform that stage.');
            }

            $assignment->assigned_to = $assignee->id;
            $assignment->assigned_by = $user->id;
        }

        if (! empty($validated['status'])) {
            $assignment->status = $validated['status'];
            $assignment->completed_at = in_array($validated['status'], ['completed', 'rejected'], true)
                ? now()
                : null;
        }

        if (array_key_exists('notes', $validated)) {
            $assignment->notes = $validated['notes'];
        }

        $assignment->save();

        return back()->with('message', 'Assignment updated.');
    }

    public function destroy(Request $request, ManTaskAssignment $assignment)
    {
        if ($assignment->department !== $this->resolveDepartment($request)) {
            return back()->with('error', 'That assignment is outside your department.');
        }

        $assignment->delete();

        return back()->with('message', 'Assignment removed.');
    }
}
