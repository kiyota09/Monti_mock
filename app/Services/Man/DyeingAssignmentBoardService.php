<?php

namespace App\Services\Man;

use App\Models\Core\User;
use App\Models\Man\DyeingBatchFabric;
use App\Models\Man\DyeingMachineStaffOrder;
use App\Models\Man\Fabric;
use App\Models\Man\Machine;
use App\Models\Man\ManTaskAssignment;
use App\Models\Man\ManufacturingOrder;
use App\Models\Man\PantoneReference;
use App\Support\ManufacturingRoles;
use Illuminate\Support\Collection;

class DyeingAssignmentBoardService
{
    public const STAGE_LABELS = [
        'dyeing_bleaching' => 'Bleaching',
        'dyeing_pre_treatment' => 'Pre Treatment',
        'dyeing_color' => 'Dyeing',
        'dyeing_treatment' => 'Post Treatment',
    ];

    public const STAGE_TO_BATCH_STAGE = [
        'dyeing_bleaching' => 'bleaching',
        'dyeing_pre_treatment' => 'pre_treatment',
        'dyeing_color' => 'color',
        'dyeing_treatment' => 'post_treatment',
    ];

    public const BATCH_STAGE_TO_ASSIGNMENT = [
        'bleaching' => 'dyeing_bleaching',
        'pre_treatment' => 'dyeing_pre_treatment',
        'color' => 'dyeing_color',
        'post_treatment' => 'dyeing_treatment',
    ];

    public const COLOR_GROUPS = [
        'white' => 'White',
        'light' => 'Light Colors',
        'dark' => 'Dark Colors',
    ];

    public const STAGE_ROLES = [
        'dyeing_bleaching' => 'dyeing_bleaching',
        'dyeing_pre_treatment' => 'dyeing_pre_treatment',
        'dyeing_color' => 'dyeing_color',
        'dyeing_treatment' => 'dyeing_treatment',
    ];

    public function build(): array
    {
        $pantones = PantoneReference::query()
            ->get(['name', 'color_category'])
            ->keyBy(fn (PantoneReference $pantone) => mb_strtolower(trim((string) $pantone->name)));

        $orders = ManufacturingOrder::with([
            'salesOrder.client',
            'salesOrder.recipe',
            'purchaseOrder.client',
            'fabrics.dyeingBatchFabric.batch.machine',
        ])
            ->whereIn('status', ['pending', 'in_progress'])
            ->latest()
            ->get()
            ->filter(fn (ManufacturingOrder $order) => $this->fabricsForOrder($order)
                ->whereIn('status', ['dyeing', 'bleaching'])
                ->exists())
            ->values();

        $assignments = ManTaskAssignment::with(['assignee:id,name'])
            ->where('department', 'dyeing')
            ->active()
            ->get();

        $machines = Machine::query()
            ->where('type', 'dyeing')
            ->where('status', 'available')
            ->orderBy('machine_no')
            ->get(['id', 'machine_no', 'power', 'temperature', 'load_capacity_kg', 'qualification']);

        $staff = User::query()
            ->where('role', 'MAN')
            ->whereIn('manufacturing_role', array_values(self::STAGE_ROLES))
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'manufacturing_role', 'profile_photo_path'])
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'manufacturing_role' => $member->manufacturing_role,
                'role_label' => ManufacturingRoles::label($member->manufacturing_role),
                'photo' => $member->profile_photo_path
                    ? '/storage/'.ltrim($member->profile_photo_path, '/')
                    : null,
            ]);

        $staffOrders = DyeingMachineStaffOrder::query()
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (DyeingMachineStaffOrder $row) => $row->machine_id.'|'.$row->stage);

        $received = [];
        $cardsByStage = array_fill_keys(array_keys(self::STAGE_LABELS), []);

        foreach ($orders as $order) {
            $color = $this->resolveColorGroup($order, $pantones);
            $fabrics = $this->fabricsForOrder($order)->with('dyeingBatchFabric.batch.machine')->get();

            if ($order->dyeing_bleaching_required === null) {
                $received[] = $this->receivedOrderPayload($order, $color, $fabrics);

                continue;
            }

            $stage = $this->currentStage($order, $fabrics);
            if (! $stage) {
                continue;
            }

            $card = $this->cardPayload($order, $stage, $color, $fabrics, $assignments);
            if ($card) {
                $cardsByStage[$stage][] = $card;
            }
        }

        $tabs = [];
        foreach (self::STAGE_LABELS as $stage => $label) {
            $hotOnly = $stage === 'dyeing_treatment';
            $groups = [];
            foreach (self::COLOR_GROUPS as $groupKey => $groupLabel) {
                $hot = collect($cardsByStage[$stage])->filter(fn ($card) => $card['color_group'] === $groupKey && $card['process'] === 'Hot')->values()->all();
                $cold = $hotOnly
                    ? []
                    : collect($cardsByStage[$stage])->filter(fn ($card) => $card['color_group'] === $groupKey && $card['process'] === 'Cold')->values()->all();

                $groups[] = [
                    'key' => $groupKey,
                    'label' => $groupLabel,
                    'hot' => $hot,
                    'cold' => $cold,
                ];
            }

            $tabs[] = [
                'key' => $stage,
                'label' => $label,
                'role' => self::STAGE_ROLES[$stage],
                'hot_only' => $hotOnly,
                'groups' => $groups,
                'machines' => $this->machinePayload($stage, $machines, $cardsByStage[$stage], $staff, $staffOrders),
            ];
        }

        $unclassified = collect($cardsByStage)->flatten(1)
            ->filter(fn ($card) => ! $card['color_group'])
            ->values()
            ->all();

        return [
            'received' => $received,
            'tabs' => $tabs,
            'unclassified' => $unclassified,
            'machines' => $machines->map(fn (Machine $machine) => [
                'id' => $machine->id,
                'machine_no' => $machine->machine_no,
                'power' => $machine->power,
                'temperature' => $machine->temperature,
                'load_capacity_kg' => $machine->load_capacity_kg,
                'qualification' => $machine->qualification,
            ])->values()->all(),
            'staff' => $staff->values()->all(),
            'colorGroups' => collect(self::COLOR_GROUPS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
        ];
    }

    public function detectColorGroup(ManufacturingOrder $order, ?Collection $pantones = null): ?string
    {
        $pantones ??= PantoneReference::query()->get(['name', 'color_category'])
            ->keyBy(fn (PantoneReference $pantone) => mb_strtolower(trim((string) $pantone->name)));

        $candidates = [
            $order->salesOrder?->color,
            $order->salesOrder?->recipe?->dye_color,
            $order->purchaseOrder?->items?->pluck('color')->first(),
        ];

        foreach ($candidates as $candidate) {
            $name = mb_strtolower(trim((string) $candidate));
            if ($name === '') {
                continue;
            }

            $category = $pantones->get($name)?->color_category;
            if (in_array($category, array_keys(self::COLOR_GROUPS), true)) {
                return $category;
            }
        }

        return null;
    }

    public function fabricsForOrder(ManufacturingOrder $order)
    {
        $moId = $order->id;
        $salesOrderId = $order->sales_order_id;

        return Fabric::query()->where(function ($query) use ($moId, $salesOrderId) {
            if ($moId) {
                $query->where('manufacturing_order_id', $moId);
                if ($salesOrderId) {
                    $query->orWhere('sales_order_id', $salesOrderId);
                }
            } elseif ($salesOrderId) {
                $query->where('sales_order_id', $salesOrderId);
            } else {
                $query->whereRaw('1 = 0');
            }
        });
    }

    public function readyFabricIds(ManufacturingOrder $order, string $stage): array
    {
        $fabrics = $this->fabricsForOrder($order)->with('dyeingBatchFabric')->get();
        $batchStage = self::STAGE_TO_BATCH_STAGE[$stage] ?? null;
        $ids = [];

        foreach ($fabrics as $fabric) {
            $batchFabric = $fabric->dyeingBatchFabric;

            if ($batchFabric) {
                if ($batchFabric->current_stage === $batchStage && $batchFabric->status === 'active') {
                    $ids[] = (int) $fabric->id;
                }

                continue;
            }

            if ($this->isFirstStage($order, $stage) && in_array($fabric->status, ['dyeing', 'bleaching'], true)) {
                $ids[] = (int) $fabric->id;
            }
        }

        return $ids;
    }

    private function receivedOrderPayload(ManufacturingOrder $order, array $color, Collection $fabrics): array
    {
        return [
            'order_id' => $order->id,
            'reference' => $this->orderReference($order),
            'client' => $this->orderClient($order),
            'color' => $order->salesOrder?->color ?? $order->salesOrder?->recipe?->dye_color,
            'color_group' => $color['value'],
            'color_group_label' => $color['value'] ? self::COLOR_GROUPS[$color['value']] : null,
            'color_group_source' => $color['source'],
            'weight' => round((float) $fabrics->whereIn('status', ['dyeing', 'bleaching'])->sum('weight'), 2),
            'fabric_count' => $fabrics->whereIn('status', ['dyeing', 'bleaching'])->count(),
            'route' => null,
            'expected_ship_date' => $order->salesOrder?->expected_ship_date?->toDateString(),
        ];
    }

    private function cardPayload(ManufacturingOrder $order, string $stage, array $color, Collection $fabrics, Collection $assignments): ?array
    {
        $batchStage = self::STAGE_TO_BATCH_STAGE[$stage];
        $readyIds = $this->readyFabricIds($order, $stage);
        $stageAssignments = $assignments->where('manufacturing_order_id', $order->id)->where('stage', $stage);
        $primaryAssignment = $stageAssignments->first();
        $assignedFabricIds = $stageAssignments->pluck('fabric_id')->filter()->map(fn ($id) => (int) $id)->unique()->all();
        $assignableIds = array_values(array_diff($readyIds, $assignedFabricIds));

        $stageFabrics = $fabrics->filter(function (Fabric $fabric) use ($batchStage, $stage, $order) {
            $batchFabric = $fabric->dyeingBatchFabric;
            if ($batchFabric) {
                return $batchFabric->current_stage === $batchStage;
            }

            return $this->isFirstStage($order, $stage) && in_array($fabric->status, ['dyeing', 'bleaching'], true);
        })->values();

        if ($stageFabrics->isEmpty()) {
            return null;
        }

        $processes = $order->dyeing_stage_processes ?? [];
        $process = $stage === 'dyeing_treatment'
            ? 'Hot'
            : (in_array($processes[$stage] ?? null, ['Hot', 'Cold'], true) ? $processes[$stage] : 'Hot');

        $machineId = $primaryAssignment ? (int) (collect($primaryAssignment->machine_ids ?? [])->first() ?? 0) : null;
        $machine = $machineId ? Machine::query()->find($machineId, ['id', 'machine_no']) : null;

        return [
            'order_id' => $order->id,
            'reference' => $this->orderReference($order),
            'client' => $this->orderClient($order),
            'color' => $order->salesOrder?->color ?? $order->salesOrder?->recipe?->dye_color,
            'color_group' => $color['value'],
            'color_group_label' => $color['value'] ? self::COLOR_GROUPS[$color['value']] : null,
            'color_group_source' => $color['source'],
            'stage' => $stage,
            'stage_label' => self::STAGE_LABELS[$stage],
            'process' => $process,
            'weight' => round((float) $stageFabrics->sum('weight'), 2),
            'fabric_count' => $stageFabrics->count(),
            'fabrics' => $stageFabrics->map(fn (Fabric $fabric) => [
                'id' => $fabric->id,
                'code' => $fabric->code,
                'weight' => (float) $fabric->weight,
                'ready' => in_array((int) $fabric->id, $readyIds, true),
                'awaiting_review' => $fabric->dyeingBatchFabric?->status === 'awaiting_review',
            ])->values()->all(),
            'ready_fabric_ids' => $readyIds,
            'assignable_fabric_ids' => $assignableIds,
            'can_assign' => count($assignableIds) > 0,
            'assigned' => $primaryAssignment !== null && count($assignableIds) === 0,
            'has_assignment' => $primaryAssignment !== null,
            'assigned_fabric_count' => count(array_intersect($readyIds, $assignedFabricIds)),
            'assignment' => $primaryAssignment ? [
                'machine_id' => $machineId,
                'machine_no' => $machine?->machine_no,
                'assigned_to' => $primaryAssignment->assigned_to,
                'staff_name' => $primaryAssignment->assignee?->name,
                'queue_position' => $primaryAssignment->queue_position,
                'created_at' => $primaryAssignment->created_at?->toISOString(),
            ] : null,
            'expected_ship_date' => $order->salesOrder?->expected_ship_date?->toDateString(),
        ];
    }

    private function machinePayload(string $stage, Collection $machines, array $cards, Collection $staff, Collection $staffOrders): array
    {
        $role = self::STAGE_ROLES[$stage];
        $roleStaff = $staff->where('manufacturing_role', $role)->values();

        return $machines->map(function (Machine $machine) use ($stage, $cards, $roleStaff, $staffOrders) {
            $queue = collect($cards)
                ->filter(fn ($card) => $card['has_assignment']
                    && (int) ($card['assignment']['machine_id'] ?? 0) === (int) $machine->id)
                ->sortBy(fn ($card) => $card['assignment']['created_at'] ?? '')
                ->map(fn ($card) => [
                    'order_id' => $card['order_id'],
                    'reference' => $card['reference'],
                    'color_group' => $card['color_group'],
                    'color_group_label' => $card['color_group_label'],
                    'process' => $card['process'],
                    'queue_position' => $card['assignment']['queue_position'],
                    'created_at' => $card['assignment']['created_at'],
                    'staff_name' => $card['assignment']['staff_name'],
                ])->values()->all();

            $saved = $staffOrders->get($machine->id.'|'.$stage, collect());
            $positioned = $saved->keyBy('staff_id');
            $orderedStaff = $roleStaff->sortBy(function ($member) use ($positioned) {
                $row = $positioned->get($member['id']);

                return $row ? $row->position : PHP_INT_MAX;
            })->values()->map(fn ($member) => [
                'id' => $member['id'],
                'name' => $member['name'],
                'role_label' => $member['role_label'],
            ])->all();

            return [
                'id' => $machine->id,
                'machine_no' => $machine->machine_no,
                'power' => $machine->power,
                'temperature' => $machine->temperature,
                'load_capacity_kg' => $machine->load_capacity_kg,
                'qualification' => $machine->qualification,
                'queue' => $queue,
                'staff_order' => $orderedStaff,
            ];
        })->values()->all();
    }

    private function resolveColorGroup(ManufacturingOrder $order, Collection $pantones): array
    {
        if (in_array($order->dyeing_color_group, array_keys(self::COLOR_GROUPS), true)) {
            return ['value' => $order->dyeing_color_group, 'source' => 'manual'];
        }

        $detected = $this->detectColorGroup($order, $pantones);

        return ['value' => $detected, 'source' => $detected ? 'auto' : null];
    }

    private function currentStage(ManufacturingOrder $order, Collection $fabrics): ?string
    {
        $orderStages = ['bleaching', 'pre_treatment', 'color', 'post_treatment'];
        $activeStages = [];

        foreach ($fabrics as $fabric) {
            $batchFabric = $fabric->dyeingBatchFabric;
            if (! $batchFabric) {
                continue;
            }

            if (in_array($batchFabric->status, ['active', 'awaiting_review'], true)
                && in_array($batchFabric->current_stage, $orderStages, true)) {
                $activeStages[] = $batchFabric->current_stage;
            }
        }

        if ($activeStages) {
            usort($activeStages, fn ($a, $b) => array_search($a, $orderStages, true) <=> array_search($b, $orderStages, true));

            return self::BATCH_STAGE_TO_ASSIGNMENT[$activeStages[0]] ?? null;
        }

        $hasUnbatched = $fabrics->whereIn('status', ['dyeing', 'bleaching'])
            ->contains(fn (Fabric $fabric) => $fabric->dyeingBatchFabric === null);
        if (! $hasUnbatched) {
            return null;
        }

        return $order->dyeing_bleaching_required ? 'dyeing_bleaching' : 'dyeing_pre_treatment';
    }

    private function isFirstStage(ManufacturingOrder $order, string $stage): bool
    {
        return $stage === ($order->dyeing_bleaching_required ? 'dyeing_bleaching' : 'dyeing_pre_treatment');
    }

    private function orderReference(ManufacturingOrder $order): string
    {
        return $order->salesOrder?->jo_number
            ?? $order->purchaseOrder?->po_number
            ?? ('MO-'.$order->id);
    }

    private function orderClient(ManufacturingOrder $order): string
    {
        return $order->salesOrder?->client?->company_name
            ?? $order->purchaseOrder?->client?->company_name
            ?? 'N/A';
    }
}
