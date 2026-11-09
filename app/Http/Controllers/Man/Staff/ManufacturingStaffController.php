<?php

namespace App\Http\Controllers\Man\Staff;

use App\Http\Controllers\Controller;
use App\Models\Hrm\EmployeeShift;
use App\Models\Man\Machine;
use App\Models\Man\MachineReport;
use App\Models\Man\ManTaskAssignment;
use App\Models\Man\DyeingBatchFabric;
use App\Models\Man\Fabric;
use App\Models\Man\LabTrial;
use App\Models\Man\ManufacturingOrder;
use App\Models\Inv\Material;
use App\Support\ManufacturingRoles;
use App\Support\ManufacturingPages;
use Illuminate\Support\Facades\Auth;

abstract class ManufacturingStaffController extends Controller
{
    /**
     * Get the currently authenticated staff member.
     *
     * @return \App\Models\Core\User
     */
    protected function staff()
    {
        $user = Auth::user();
        if ($user?->is_manufacturing_supervisor) {
            // Staff page controllers use manufacturing_role to select their
            // stage-specific labels and workflow. Give the supervisor the
            // current route role while retaining the supervisor identity.
            $user->manufacturing_role = ManufacturingPages::roleForRouteName(request()->route()?->getName());
        }

        return $user;
    }

    /**
     * Get the staff's current shift.
     * If a shift field exists on the user, it uses that; otherwise,
     * it tries to fetch the shift from the employee_shifts table for today.
     *
     * @return string
     */
    protected function getShift()
    {
        $user = $this->staff();

        // Option 1: if the user has a direct 'shift' attribute
        if (isset($user->shift)) {
            return $user->shift;
        }

        // Option 2: fetch from employee_shifts table
        $today = now()->toDateString();
        $shift = EmployeeShift::where('user_id', $user->id)
            ->where('effective_date', $today)
            ->first();

        return $shift ? $shift->shift_type : 'Morning'; // fallback
    }

    /**
     * Manufacturing-order / sales-order ids assigned to the logged-in staff
     * member for the stage their role performs. Staff only see Job Orders
     * their supervisor assigned to them, so an empty result means "nothing
     * on my plate" — the work lists intentionally render empty.
     *
     * @return array{mo: array<int, int>, so: array<int, int>}
     */
    protected function myAssignmentScope(): array
    {
        $user = $this->staff();
        $role = $this->workspaceRole($user);
        $stage = ManufacturingRoles::stageForRole($role);

        $base = ManTaskAssignment::active();
        if ($user->is_manufacturing_supervisor) {
            // A supervisor monitors every JO assigned to anyone in the
            // selected staff role, regardless of which employee has it.
            $staffIds = $role
                ? \App\Models\Core\User::where('role', 'MAN')->where('position', 'staff')
                    ->where('is_manufacturing_supervisor', false)->where('manufacturing_role', $role)->pluck('id')
                : collect();
            $base->whereIn('assigned_to', $staffIds);
        } else {
            $base->where('assigned_to', $user->id);
            if ($stage) {
                $base->where('stage', $stage);
            }
        }

        $assignmentRows = (clone $base)->get(['fabric_id', 'manufacturing_order_id', 'sales_order_id']);
        $orderAssignments = $assignmentRows->whereNull('fabric_id');
        $assignedFabricIds = $assignmentRows->pluck('fabric_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

        return [
            'mo' => $orderAssignments->whereNotNull('manufacturing_order_id')->pluck('manufacturing_order_id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'so' => $orderAssignments->whereNotNull('sales_order_id')->pluck('sales_order_id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'fabric_ids' => $assignedFabricIds->all(),
        ];
    }

    /**
     * Constrain a query to the staff member's assigned Job Orders. Matches on
     * either the manufacturing-order or the sales-order column (whichever the
     * model links on).
     */
    protected function scopeToAssignment($query, string $moColumn = 'manufacturing_order_id', string $soColumn = 'sales_order_id')
    {
        $scope = $this->myAssignmentScope();

        return $query->where(function ($q) use ($scope, $moColumn, $soColumn) {
            if (! $scope['mo'] && ! $scope['so'] && ! $scope['fabric_ids']) {
                $q->whereRaw('1 = 0');
                return;
            }
            if ($scope['mo']) $q->whereIn($moColumn, $scope['mo']);
            if ($scope['so']) $q->orWhereIn($soColumn, $scope['so']);
            if ($scope['fabric_ids']) $q->orWhereIn('id', $scope['fabric_ids']);
        });
    }

    /**
     * True when the given Job Order (by manufacturing-order or sales-order id)
     * is assigned to the logged-in staff member for their stage. Staff must
     * never touch work their supervisor did not hand them.
     */
    protected function isAssignedItem(?int $manufacturingOrderId, ?int $salesOrderId): bool
    {
        $scope = $this->myAssignmentScope();

        if ($manufacturingOrderId && in_array((int) $manufacturingOrderId, $scope['mo'], true)) {
            return true;
        }

        if ($salesOrderId && in_array((int) $salesOrderId, $scope['so'], true)) {
            return true;
        }

        return false;
    }

    protected function isAssignedFabric(int $fabricId, ?int $manufacturingOrderId, ?int $salesOrderId): bool
    {
        $scope = $this->myAssignmentScope();

        return in_array($fabricId, $scope['fabric_ids'], true)
            || ($manufacturingOrderId && in_array($manufacturingOrderId, $scope['mo'], true))
            || ($salesOrderId && in_array($salesOrderId, $scope['so'], true));
    }

    /** Keep Dyeing Lab Chemist formula sections instead of inferring them from inventory categories. */
    protected function dyeingFormulaGroups(?array $formula, float $productionWeightKg = 0): array
    {
        $definitions = [
            ['label' => 'Dye stuffs', 'aliases' => ['dyestuffs', 'dye_stuffs'], 'quantity_keys' => ['grams', 'amount', 'pct', 'percentage', 'quantity'], 'dose' => 'grams'],
            ['label' => 'Basic Chemicals', 'aliases' => ['basic_chemicals', 'basic_chemical'], 'quantity_keys' => ['grams', 'amount', 'gpl', 'g_per_liter', 'quantity'], 'dose' => 'grams'],
            ['label' => 'Auxiliary Agents', 'aliases' => ['auxiliary_agents', 'auxiliaries', 'auxiliary'], 'quantity_keys' => ['grams', 'amount', 'gpl', 'g_per_liter', 'quantity'], 'dose' => 'grams'],
            ['label' => 'Post Treatment', 'aliases' => ['post_treatment', 'post_treatment_agents'], 'quantity_keys' => ['grams', 'amount', 'gpl', 'g_per_liter', 'quantity'], 'dose' => 'grams'],
        ];
        $itemsByGroup = collect($definitions)->mapWithKeys(fn ($definition) => [
            $definition['label'] => collect($definition['aliases'])
                ->flatMap(fn ($key) => $formula[$key] ?? [])
                ->values(),
        ]);
        $materialIds = $itemsByGroup->flatten(1)
            ->pluck('material_id')->filter()->unique()->values();
        $materials = $materialIds->isEmpty()
            ? collect()
            : Material::whereIn('id', $materialIds)->get()->keyBy('id');

        $liquorRatio = $this->dyeingLiquorRatio($formula['liquor_ratio'] ?? null);
        $sampleWeightGrams = max(0.0001, (float) ($formula['sample_weight_g'] ?? 100));

        return collect($definitions)->map(function ($definition) use ($itemsByGroup, $materials, $productionWeightKg, $liquorRatio, $sampleWeightGrams) {
            $rows = $itemsByGroup->get($definition['label'], collect())->map(function ($item) use ($definition, $materials, $productionWeightKg, $liquorRatio, $sampleWeightGrams) {
                $materialId = $item['material_id'] ?? null;
                $material = $materialId ? $materials->get($materialId) : null;
                $labQuantity = null;
                foreach ($definition['quantity_keys'] as $key) {
                    if (array_key_exists($key, $item)) {
                        $labQuantity = (float) $item[$key];
                        break;
                    }
                }
                $labGrams = array_key_exists('grams', $item)
                    ? (float) $item['grams']
                    : (array_key_exists('pct', $item) || array_key_exists('percentage', $item)
                        ? (float) ($item['pct'] ?? $item['percentage']) * $sampleWeightGrams / 100
                        : (array_key_exists('gpl', $item) || array_key_exists('g_per_liter', $item)
                            ? (float) ($item['gpl'] ?? $item['g_per_liter']) * $liquorRatio * $sampleWeightGrams / 1000
                            : (float) $labQuantity));
                $percentageWof = $labGrams / $sampleWeightGrams * 100;
                $quantity = $definition['dose'] === 'grams'
                    ? $productionWeightKg * ($labGrams / $sampleWeightGrams)
                    : ($definition['dose'] === 'pct'
                        ? $productionWeightKg * (float) $labQuantity / 100
                        : (float) $labQuantity * $liquorRatio * $productionWeightKg / 1000);
                $displayDose = rtrim(rtrim(number_format((float) $labQuantity, 4, '.', ''), '0'), '.');
                $weight = number_format($productionWeightKg, 2, '.', '');
                $sampleWeight = number_format($sampleWeightGrams, 2, '.', '');
                $rateKgPerKg = $labGrams / $sampleWeightGrams;
                $rateDisplay = rtrim(rtrim(number_format($rateKgPerKg, 6, '.', ''), '0'), '.');
                $percentageDisplay = rtrim(rtrim(number_format($percentageWof, 4, '.', ''), '0'), '.');
                $calculation = $definition['dose'] === 'grams'
                    ? "{$labGrams} g ÷ {$sampleWeight} g = {$rateDisplay} kg/kg; {$rateDisplay} kg/kg × {$weight} kg"
                    : "{$displayDose} g/L × {$liquorRatio} L/kg × {$weight} kg ÷ 1000";

                return [
                    'material_id' => $materialId,
                    'name' => $material?->display_name ?? ($item['name'] ?? $item['material_name'] ?? 'Material'),
                    'quantity' => round($quantity, 4),
                    'unit' => 'kg',
                    'lab_quantity' => $labGrams,
                    'lab_unit' => 'g',
                    'calculation' => $calculation,
                ];
            })->values();

            return ['label' => $definition['label'], 'materials' => $rows->all()];
        })->all();
    }

    protected function dyeingLiquorRatio(mixed $value): float
    {
        if (is_numeric($value) && (float) $value > 0) return (float) $value;
        if (preg_match_all('/\d+(?:\.\d+)?/', (string) $value, $matches) && $matches[0]) {
            return max(0.01, (float) end($matches[0]));
        }

        return 10.0;
    }

    /** Resolve the approved dye formula from the JO first, then its linked recipe history. */
    protected function dyeingFormulaForOrder(?ManufacturingOrder $order, $salesOrder): ?array
    {
        $recipe = $salesOrder?->recipe;
        $formula = $order?->dyeing_production_recipe
            ?: $recipe?->dyeing_formula
            ?: $recipe?->fabricSampleRequest?->formula
            ?: $recipe?->sampleRequests?->sortByDesc('id')->first()?->formula;

        if (! $formula && $salesOrder) {
            $formula = LabTrial::where('status', 'passed')
                ->whereHas('dip', fn ($query) => $query
                    ->where('sales_order_id', $salesOrder->id)
                    ->where('status', 'approved'))
                ->latest('id')->first()?->formula;
        }

        if (is_string($formula)) {
            $formula = json_decode($formula, true);
        }

        return is_array($formula) && $formula ? $formula : null;
    }

    protected function dyeingProductionWeight(?ManufacturingOrder $order, $salesOrder): float
    {
        $weight = (float) ($order?->total_quantity ?? 0);
        if ($weight > 0) return $weight;

        $weight = $order ? (float) $order->fabrics()->sum('weight') : 0;
        if ($weight > 0) return $weight;

        if ($salesOrder) {
            $weight = (float) Fabric::where('sales_order_id', $salesOrder->id)->sum('weight');
            if ($weight > 0) return $weight;
        }

        return (float) ($salesOrder?->quantity ?? 0);
    }

    protected function assignedProductionOrders(string $department): array
    {
        $user = $this->staff();
        $role = $this->workspaceRole($user);
        $stage = ManufacturingRoles::stageForRole($role);
        if (! $stage && ! $user->is_manufacturing_supervisor) {
            return [];
        }

        $processStage = [
            'dyeing_bleaching' => 'bleaching',
            'dyeing_pre_treatment' => 'pre_treatment',
            'dyeing_color' => 'color',
            'dyeing_treatment' => 'post_treatment',
        ][$stage] ?? null;

        $query = ManTaskAssignment::with([
            'manufacturingOrder.salesOrder.client',
            'manufacturingOrder.salesOrder.recipe.product',
            'manufacturingOrder.salesOrder.recipe.fabricSampleRequest',
            'manufacturingOrder.salesOrder.recipe.sampleRequests',
            'manufacturingOrder.purchaseOrder.client',
            'fabric.manufacturingOrder.salesOrder.client',
            'fabric.manufacturingOrder.salesOrder.recipe.product',
            'fabric.manufacturingOrder.salesOrder.recipe.fabricSampleRequest',
            'fabric.manufacturingOrder.salesOrder.recipe.sampleRequests',
            'fabric.salesOrder.client',
            'fabric.salesOrder.recipe.product',
            'fabric.salesOrder.recipe.fabricSampleRequest',
            'fabric.salesOrder.recipe.sampleRequests',
            'salesOrder.client',
            'salesOrder.recipe.product',
            'salesOrder.recipe.fabricSampleRequest',
            'salesOrder.recipe.sampleRequests',
        ])
            ->where('department', $department)
            ->active();
        if ($user->is_manufacturing_supervisor) {
            $staffIds = $role
                ? \App\Models\Core\User::where('role', 'MAN')->where('position', 'staff')
                    ->where('is_manufacturing_supervisor', false)->where('manufacturing_role', $role)->pluck('id')
                : collect();
            $query->whereIn('assigned_to', $staffIds);
        } else {
            $query->where('stage', $stage)
                ->where('assigned_to', $user->id);
        }

        return $query->latest()->get()
            ->map(function (ManTaskAssignment $assignment) use ($processStage, $department) {
                $order = $assignment->manufacturingOrder ?: $assignment->fabric?->manufacturingOrder;
                $salesOrder = $assignment->salesOrder ?: $order?->salesOrder ?: $assignment->fabric?->salesOrder;
                $manufacturingOrderId = $assignment->manufacturing_order_id ?: $order?->id;
                $salesOrderId = $assignment->sales_order_id ?: $salesOrder?->id;
                $fabricId = $assignment->fabric_id;

                // A Dyeing assignment remains active until a manager closes it in
                // some workflows. Do not leave a completed JO on a staff work page
                // just because that assignment row was not closed at the same time.
                if ($department === 'dyeing') {
                    if (strtolower((string) $order?->status) === 'completed'
                        || strtolower((string) $salesOrder?->status) === 'completed') {
                        return null;
                    }

                    $dyeingItems = DyeingBatchFabric::query()
                        ->whereHas('fabric', function ($fabricQuery) use ($manufacturingOrderId, $salesOrderId, $fabricId) {
                            $fabricQuery->where(function ($ids) use ($manufacturingOrderId, $salesOrderId, $fabricId) {
                                if ($manufacturingOrderId) $ids->where('manufacturing_order_id', $manufacturingOrderId);
                                if ($salesOrderId) $ids->{$manufacturingOrderId ? 'orWhere' : 'where'}('sales_order_id', $salesOrderId);
                                if ($fabricId && ! $manufacturingOrderId && ! $salesOrderId) $ids->whereKey($fabricId);
                            });
                        })->get(['status']);

                    if ($dyeingItems->isNotEmpty() && $dyeingItems->every(fn ($item) => $item->status === 'completed')) {
                        return null;
                    }
                }

                $productionWeight = $this->dyeingProductionWeight($order, $salesOrder);
                $recipe = $salesOrder?->recipe;
                $recipeMaterials = $recipe?->materials ?? [];
                $materialRows = collect($recipeMaterials)->map(function ($quantity, $materialId) {
                    return ['material_id' => $materialId, 'quantity' => $quantity];
                })->values();
                $materials = Material::whereIn('id', $materialRows->pluck('material_id')->all())->get()->keyBy('id');
                $recipeMaterialData = $materialRows->map(function ($row) use ($materials) {
                    $material = $materials->get($row['material_id']);
                    $category = strtolower(preg_replace('/[^a-z]/', '', (string) $material?->category));
                    $classification = match ($category) {
                        'dye', 'dyestuff', 'dyestuffs' => 'Dye stuff',
                        'basicchemical', 'basicchemicals' => 'Basic Chemicals',
                        'auxillaryagent', 'auxiliaryagent', 'auxiliaryagents', 'agentauxiliary' => 'Agent Auxiliary',
                        'posttreatment', 'posttreatmentagent' => 'Post Treatment',
                        default => 'Other',
                    };

                    return [
                        'material_id' => $row['material_id'],
                        'name' => $material?->display_name ?? ('Material #'.$row['material_id']),
                        'category' => $material?->category,
                        'classification' => $classification,
                        'quantity' => $row['quantity'],
                        'unit' => $material?->unit,
                    ];
                });
                $recipeGroups = collect(['Dye stuff', 'Basic Chemicals', 'Agent Auxiliary', 'Post Treatment', 'Other'])
                    ->map(fn ($label) => [
                        'label' => $label,
                        'materials' => $recipeMaterialData->where('classification', $label)->values()->all(),
                    ])->all();
                $dyeingFormula = $this->dyeingFormulaForOrder($order, $salesOrder);
                $formulaGroups = $this->dyeingFormulaGroups($dyeingFormula, $productionWeight);
                if (is_array($dyeingFormula) && $dyeingFormula) {
                    $formulaGroupCollection = collect($formulaGroups);
                    $formulaMaterialIds = $formulaGroupCollection->flatMap(fn ($group) => collect($group['materials'])
                        ->pluck('material_id'))->filter()->map(fn ($id) => (int) $id)->unique();
                    $otherMaterials = $recipeMaterialData->reject(fn ($row) => $formulaMaterialIds->contains((int) $row['material_id']))->values();
                    $recipeGroups = $formulaGroupCollection->all();
                    if ($otherMaterials->isNotEmpty()) {
                        $recipeGroups[] = ['label' => 'Other recipe materials', 'materials' => $otherMaterials->all()];
                    }
                }
                $recipeData = ($recipe || (is_array($dyeingFormula) && $dyeingFormula)) ? [
                    'id' => $recipe?->id,
                    'product' => $recipe?->product?->name,
                    'yarn_type' => $recipe?->yarn_type ?? $salesOrder?->yarn_type,
                    'dye_color' => $recipe?->dye_color ?? $salesOrder?->color,
                    'weave_design' => $recipe?->weave_design ?? $salesOrder?->design,
                    'materials' => $recipeMaterialData->values()->all(),
                    'material_groups' => $recipeGroups,
                    'formula_groups' => $formulaGroups,
                ] : null;
                $stageItems = $processStage && ($manufacturingOrderId || $salesOrderId || $fabricId)
                    ? DyeingBatchFabric::query()->where('current_stage', $processStage)
                        ->when($assignment->fabric_id, fn ($query) => $query->where('fabric_id', $assignment->fabric_id))
                        ->whereHas('fabric', function ($query) use ($manufacturingOrderId, $salesOrderId, $fabricId) {
                            $query->where(function ($ids) use ($manufacturingOrderId, $salesOrderId, $fabricId) {
                                if ($manufacturingOrderId) {
                                    $ids->where('manufacturing_order_id', $manufacturingOrderId);
                                }
                                if ($salesOrderId) {
                                    $method = $manufacturingOrderId ? 'orWhere' : 'where';
                                    $ids->{$method}('sales_order_id', $salesOrderId);
                                }
                                if ($fabricId && ! $manufacturingOrderId && ! $salesOrderId) {
                                    $ids->whereKey($fabricId);
                                }
                                if (! $manufacturingOrderId && ! $salesOrderId && ! $fabricId) {
                                    $ids->whereRaw('1 = 0');
                                }
                            });
                        })
                    : collect();
                $readyCount = $stageItems instanceof \Illuminate\Database\Eloquent\Builder
                    ? (clone $stageItems)->where('status', 'active')->count()
                    : 0;
                $reviewCount = $stageItems instanceof \Illuminate\Database\Eloquent\Builder
                    ? (clone $stageItems)->where('status', 'awaiting_review')->count()
                    : 0;
                $initialStage = $order?->dyeing_bleaching_required === true ? 'bleaching' : 'pre_treatment';
                $unbatchedCount = 0;
                if ($processStage === $initialStage && $order?->dyeing_bleaching_required !== null
                    && ($manufacturingOrderId || $salesOrderId || $fabricId)) {
                    $unbatchedQuery = Fabric::query()->whereIn('status', ['dyeing', 'bleaching'])
                        ->whereDoesntHave('dyeingBatchFabric')
                        ->when($assignment->fabric_id, fn ($query) => $query->whereKey($assignment->fabric_id))
                        ->where(function ($ids) use ($manufacturingOrderId, $salesOrderId, $fabricId) {
                            if ($manufacturingOrderId) {
                                $ids->where('manufacturing_order_id', $manufacturingOrderId);
                            }
                            if ($salesOrderId) {
                                $method = $manufacturingOrderId ? 'orWhere' : 'where';
                                $ids->{$method}('sales_order_id', $salesOrderId);
                            }
                            if ($fabricId && ! $manufacturingOrderId && ! $salesOrderId) {
                                $ids->whereKey($fabricId);
                            }
                        });
                    $unbatchedCount = $unbatchedQuery->count();
                }
                $stageState = $readyCount > 0 ? 'ready'
                    : ($reviewCount > 0 ? 'awaiting_review'
                        : ($unbatchedCount > 0 ? 'awaiting_batch' : 'waiting_previous_stage'));

                // Keep an assignment visible only while its fabric codes still
                // belong to this staff member's dyeing stage. Assignment rows
                // can remain active until the whole JO is released, even after
                // this stage has passed supervisor review.
                if ($department === 'dyeing' && $stageState === 'waiting_previous_stage') {
                    return null;
                }

                return [
                    'assignment_id' => $assignment->id,
                    'fabric_code' => $assignment->fabric?->code,
                    'fabric_weight' => $assignment->fabric ? (float) $assignment->fabric->weight : null,
                    'manufacturing_order_id' => $manufacturingOrderId,
                    'sales_order_id' => $salesOrderId,
                    'reference' => $salesOrder?->jo_number
                        ?? $order?->purchaseOrder?->po_number
                        ?? ($manufacturingOrderId ? 'MO-'.$manufacturingOrderId : ($assignment->fabric?->code ? 'Fabric '.$assignment->fabric->code : 'Unlinked Job Order')),
                    'client' => $salesOrder?->client?->company_name ?? $order?->purchaseOrder?->client?->company_name,
                    'job_order' => $salesOrder ? [
                        'control_number' => $salesOrder->control_number,
                        'color' => $salesOrder->color,
                        'yarn_type' => $salesOrder->yarn_type,
                        'design' => $salesOrder->design,
                        'quantity' => (float) $salesOrder->quantity,
                        'expected_ship_date' => $salesOrder->expected_ship_date?->toDateString(),
                        'priority' => $salesOrder->priority,
                        'status' => $salesOrder->status,
                        'recipe_id' => $salesOrder->recipe_id,
                    ] : null,
                    'manufacturing_order' => $order ? [
                        'id' => $order->id,
                        'total_quantity' => (float) $order->total_quantity,
                        'remaining_quantity' => (float) $order->remaining_quantity,
                        'status' => $order->status,
                        'notes' => $order->notes,
                    ] : null,
                    'recipe' => $recipeData,
                    'stage' => $assignment->stage,
                    'deadline' => $assignment->deadline?->toDateString(),
                    'status' => $assignment->status,
                    'notes' => $assignment->notes,
                    'ready_fabric_count' => $readyCount,
                    'review_fabric_count' => $reviewCount,
                    'unbatched_fabric_count' => $unbatchedCount,
                    'stage_state' => $stageState,
                    'route' => $order?->dyeing_bleaching_required === null
                        ? null
                        : ($order->dyeing_bleaching_required
                            ? 'Bleaching → Pre Treatment → Color → Post Treatment'
                            : 'Pre Treatment → Color → Post Treatment'),
                ];
            })->filter()->values()->all();
    }

    /** Resolve the role workspace currently being viewed by a supervisor. */
    protected function workspaceRole($user): ?string
    {
        if ($user?->is_manufacturing_supervisor) {
            return ManufacturingPages::roleForRouteName(request()->route()?->getName());
        }

        return $user?->manufacturing_role;
    }

    /**
     * Generate a unique code for a record.
     * Format: PREFIX-YYYY-MM-XXXXX (e.g., FABRIC-2026-03-00001)
     *
     * @param  string  $prefix
     * @param  string  $modelClass  Fully qualified model class name
     * @param  string  $codeColumn  Column name where the code is stored
     * @return string
     */
    protected function generateCode($prefix, $modelClass, $codeColumn = 'code')
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $prefix = strtoupper($prefix);

        $lastCode = $modelClass::where($codeColumn, 'LIKE', "{$prefix}-{$year}-{$month}-%")
            ->orderBy($codeColumn, 'desc')
            ->first();

        if ($lastCode) {
            $parts = explode('-', $lastCode->{$codeColumn});
            $lastNumber = (int) end($parts);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%s-%05d', $prefix, $year, $month, $newNumber);
    }

    /**
     * Personal efficiency summary for the logged-in staff member.
     *
     * Access standard: every count here is scoped to operator_id = self,
     * except the read-only machine-availability context for the staff's
     * own machine type. Safe to expose on any staff dashboard.
     *
     * @param  string  $jobModel   e.g. Fabric::class (must have operator_id + date column)
     * @param  string  $dateColumn e.g. processed_at / packaged_at
     */
    protected function staffEfficiency(string $jobModel, string $dateColumn = 'processed_at'): array
    {
        $staffId = $this->staff()->id;

        return [
            'my_today' => $jobModel::where('operator_id', $staffId)
                ->whereDate($dateColumn, today())->count(),
            'my_week' => $jobModel::where('operator_id', $staffId)
                ->whereBetween($dateColumn, [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'my_total' => $jobModel::where('operator_id', $staffId)->count(),
            'my_open_reports' => MachineReport::where('reported_by', $staffId)
                ->where('status', 'pending')->count(),
            'my_resolved_reports' => MachineReport::where('reported_by', $staffId)
                ->where('status', 'resolved')->count(),
        ];
    }

    /**
     * Read-only availability of the staff's own machine type.
     * Returns null when the role has no dedicated machines (e.g. ironing/packaging).
     */
    protected function machineAvailability(?string $machineType): ?array
    {
        if (! $machineType) {
            return null;
        }

        $total = Machine::where('type', $machineType)->count();

        return [
            'type' => $machineType,
            'total' => $total,
            'available' => Machine::where('type', $machineType)->where('status', 'available')->count(),
            'down' => Machine::where('type', $machineType)->where('status', '!=', 'available')->count(),
        ];
    }

    /**
     * Shared personal work-history query (own jobs only).
     *
     * @param  string  $jobModel
     * @param  array   $with
     * @param  string  $dateColumn
     */
    protected function staffHistory(string $jobModel, array $with = [], string $dateColumn = 'processed_at')
    {
        $query = $jobModel::with($with)->where('operator_id', $this->staff()->id);

        if ($search = request('search')) {
            $query->where('code', 'like', "%{$search}%");
        }
        if ($from = request('from')) {
            $query->whereDate($dateColumn, '>=', $from);
        }
        if ($to = request('to')) {
            $query->whereDate($dateColumn, '<=', $to);
        }

        return $query->latest()->paginate(15)->withQueryString();
    }
}
