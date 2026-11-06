<?php

namespace App\Services\Man;

use App\Models\Man\DyeingBatch;
use App\Models\Man\DyeingBatchFabric;
use App\Models\Man\Fabric;
use App\Models\Man\KnittingMachineStaffAssignment;
use App\Models\Man\ManTaskAssignment;
use App\Models\Man\ManufacturingOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DyeingInitialBatchService
{
    /** Create the work items required by the first assigned dyeing stage. */
    public function prepare(ManufacturingOrder $order, string $stage, int $staffId, ?int $assignedBy = null, ?array $fabricIds = null): bool
    {
        $expectedStage = $order->dyeing_bleaching_required ? 'dyeing_bleaching' : 'dyeing_pre_treatment';
        if ($stage !== $expectedStage || $order->dyeing_bleaching_required === null) {
            return false;
        }

        $assignment = ManTaskAssignment::where('manufacturing_order_id', $order->id)
            ->where('department', 'dyeing')->where('stage', $stage)
            ->where('assigned_to', $staffId)->active()
            ->when($fabricIds, fn ($query) => $query->whereIn('fabric_id', $fabricIds))
            ->exists();
        if (! $assignment) return false;

        $fabrics = Fabric::where(function ($query) use ($order) {
                $query->where('manufacturing_order_id', $order->id);
                if ($order->sales_order_id) $query->orWhere('sales_order_id', $order->sales_order_id);
            })
            ->whereIn('status', ['dyeing', 'bleaching'])
            ->when($fabricIds, fn ($query) => $query->whereIn('id', $fabricIds))
            ->whereDoesntHave('dyeingBatchFabric')->orderBy('id')->get();
        if ($fabrics->isEmpty()) return false;

        $machines = KnittingMachineStaffAssignment::with('machine')
            ->where('staff_id', $staffId)->get()->pluck('machine')
            ->filter(fn ($machine) => $machine && $machine->type === 'dyeing'
                && $machine->status === 'available')
            ->unique('id')->values();
        // Dyeing machines may be assigned to the department rather than linked
        // directly to an operator. Let the first available dyeing machine run
        // this batch in that case; machine capacity is informational and must
        // not prevent a JO from reaching its first treatment stage.
        if ($machines->isEmpty()) {
            $machine = \App\Models\Man\Machine::where('type', 'dyeing')
                ->where('status', 'available')->orderBy('machine_no')->first();
            if ($machine) $machines = collect([$machine]);
        }
        if ($machines->isEmpty()) return false;

        // Do not strand otherwise valid JO fabric because a theoretical
        // machine load limit is lower than the assigned fabric quantity.
        $machine = $machines->first();
        $groups = [[
            $machine,
            $fabrics,
            (float) $fabrics->sum(fn ($fabric) => (float) $fabric->weight),
        ]];

        $routeStage = $order->dyeing_bleaching_required ? 'bleaching' : 'pre_treatment';
        $color = $order->salesOrder?->recipe?->dye_color ?: $order->salesOrder?->color ?: 'Unspecified';
        DB::transaction(function () use ($groups, $order, $staffId, $assignedBy, $routeStage, $color) {
            foreach ($groups as [$machine, $group, $weight]) {
                $batch = DyeingBatch::create([
                    'code' => 'DB-'.now()->format('ymdHis').'-'.Str::upper(Str::random(5)),
                    'manufacturing_order_id' => $order->id,
                    'sales_order_id' => $order->sales_order_id,
                    'machine_id' => $machine->id,
                    'assigned_to' => $staffId,
                    'assigned_by' => $assignedBy,
                    'color' => Str::limit($color, 120, ''),
                    'load_capacity_kg' => max((float) ($machine->load_capacity_kg ?? 0), $weight, 0.01),
                    'total_weight_kg' => $weight,
                    'status' => 'queued',
                ]);
                foreach ($group as $fabric) {
                    DyeingBatchFabric::create([
                        'dyeing_batch_id' => $batch->id,
                        'fabric_id' => $fabric->id,
                        'requires_bleaching' => (bool) $order->dyeing_bleaching_required,
                        'current_stage' => $routeStage,
                        'status' => 'active',
                    ]);
                    // `pre_treatment` is represented by the batch item stage;
                    // fabrics.status only accepts the legacy dyeing/bleaching states.
                    $fabric->update([
                        'status' => $routeStage === 'bleaching' ? 'bleaching' : 'dyeing',
                        'machine_id' => $machine->id,
                    ]);
                }
            }
        });

        return true;
    }
}
