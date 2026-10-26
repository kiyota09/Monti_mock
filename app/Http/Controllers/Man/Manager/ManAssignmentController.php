<?php

namespace App\Http\Controllers\Man\Manager;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Man\Fabric;
use App\Models\Man\ManTaskAssignment;
use App\Models\Man\ManufacturingOrder;
use App\Models\Man\Package;
use App\Support\ManufacturingRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Supervisor → staff Job Order assignment board.
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
     * Packages belonging to a Job Order — linked directly by
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
     * department is strictly sequential (colour → softener → squeezer →
     * ironing), so a stage opens only once a fabric for the JO has reached the
     * required status — e.g. the softener staff cannot be assigned before the
     * colour process is done. The finishing department likewise cannot hand a
     * JO to the quality checker before the finishing packaging process has
     * produced a package (packaging → quality check).
     *
     * @return array<int, string>
     */
    protected function availableStagesForOrder(ManufacturingOrder $order, string $department): array
    {
        $allStages = array_keys(ManufacturingRoles::stagesForDepartment($department));

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
     *   knitting  → no fabric produced yet, or fabrics still at the knitting gate
     *   dyeing    → fabrics that have entered colour/softener/squeezer/ironing
     *   finishing → packed fabrics awaiting packaging, or open packages
     */
    protected function departmentHasWork(ManufacturingOrder $order, string $department): bool
    {
        $fabricQuery = fn () => $this->fabricsForOrder($order);

        return match ($department) {
            'knitting' => ($fabricQuery())->where('status', 'pending')->exists()
                || ! ($fabricQuery())->exists(),

            'dyeing' => ($fabricQuery())
                ->whereIn('status', ['dyeing', 'softener', 'squeezer', 'iron'])
                ->exists(),

            'finishing' => ($fabricQuery())
                ->where('status', 'packed')
                ->whereDoesntHave('packages')
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
        // ?department=… and otherwise lands on knitting.
        $department = $this->resolveDepartment($request)
            ?? $request->query('department')
            ?? 'knitting';

        if (! in_array($department, ManufacturingRoles::PRODUCTION_DEPARTMENTS, true)) {
            $department = 'knitting';
        }

        $stages = ManufacturingRoles::stagesForDepartment($department);
        $stageKeys = array_keys($stages);

        $assignments = ManTaskAssignment::with(['assignee:id,name', 'assigner:id,name'])
            ->where('department', $department)
            ->latest()
            ->get();

        // Which stages are already assigned, per Job Order. A JO stays on the
        // board until EVERY stage in this department has an active assignment
        // (e.g. dyeing needs colour → softener → squeezer → ironing).
        $assignedByOrder = ManTaskAssignment::where('department', $department)
            ->active()
            ->whereNotNull('manufacturing_order_id')
            ->get(['manufacturing_order_id', 'stage'])
            ->groupBy('manufacturing_order_id');

        $orders = ManufacturingOrder::with(['purchaseOrder.client', 'purchaseOrder.items', 'salesOrder.client'])
            ->whereIn('status', ['pending', 'in_progress'])
            ->get()
            // A department only sees a JO once work has actually reached it —
            // knitting is the entry point; dyeing appears only after a fabric is
            // passed from knitting; finishing only after a fabric is packed.
            ->filter(fn ($order) => $this->departmentHasWork($order, $department))
            ->map(function ($order) use ($assignedByOrder, $stageKeys, $department) {
                $assignedStages = $assignedByOrder->get($order->id, collect())
                    ->pluck('stage')->unique()->values()->all();
                // Only stages whose prerequisite work is physically done can be
                // assigned (dyeing: colour before softener/squeezer/ironing).
                $assignable = $this->availableStagesForOrder($order, $department);
                $remaining = array_values(array_intersect(
                    array_diff($stageKeys, $assignedStages),
                    $assignable
                ));

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
                    'unit_price' => $salesOrder?->unit_price,
                    'total_amount' => $salesOrder?->total_amount ?? $purchaseOrder?->total_amount,
                ];
            })
            // Drop JOs whose whole department flow is already assigned.
            ->filter(fn ($order) => count($order['remaining_stages']) > 0)
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
        ]);

        $stages = ManufacturingRoles::stagesForDepartment($department);
        if (! array_key_exists($validated['stage'], $stages)) {
            return back()->with('error', 'That stage does not belong to the selected department.');
        }

        $assignedRole = $stages[$validated['stage']];

        $assignee = User::where('id', $validated['assigned_to'])
            ->where('manufacturing_role', $assignedRole)
            ->first();

        if (! $assignee) {
            return back()->with('error', 'The selected staff member cannot perform that stage.');
        }

        $already = ManTaskAssignment::where('manufacturing_order_id', $validated['manufacturing_order_id'])
            ->where('department', $department)
            ->where('stage', $validated['stage'])
            ->active()
            ->exists();

        if ($already) {
            return back()->with('error', 'This stage is already assigned for that Job Order.');
        }

        $order = ManufacturingOrder::find($validated['manufacturing_order_id']);

        // Enforce the physical sequence before assigning downstream work: the
        // stage must be currently available for the JO (dyeing: colour before
        // softener/squeezer/ironing; finishing: packaging before quality check).
        if (! $order || ! in_array($validated['stage'], $this->availableStagesForOrder($order, $department), true)) {
            return back()->with('error', 'This Job Order cannot be assigned to that stage yet — the previous process step has not been completed.');
        }

        $sequence = array_search($validated['stage'], array_keys($stages), true);

        ManTaskAssignment::create([
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
        ]);

        return back()->with('message', 'Job Order assigned to '.$assignee->name.'.');
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
