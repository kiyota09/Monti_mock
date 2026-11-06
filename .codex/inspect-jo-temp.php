<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$salesOrder = App\Models\Ord\SalesOrder::where('jo_number', 'JO-20261104-X2PL')->first();
if (! $salesOrder) {
    echo json_encode(['error' => 'JO_NOT_FOUND']);
    exit;
}
$order = App\Models\Man\ManufacturingOrder::where('sales_order_id', $salesOrder->id)->first();
$orderId = $order?->id;

$payload = [
    'sales_order_id' => $salesOrder->id,
    'manufacturing_order_id' => $orderId,
    'route_requires_bleaching' => $order?->dyeing_bleaching_required,
    'sales_order_status' => $salesOrder->status,
    'assignments' => App\Models\Man\ManTaskAssignment::where('manufacturing_order_id', $orderId)
        ->where('department', 'dyeing')->get(['id', 'stage', 'assigned_to', 'status', 'sales_order_id'])
        ->map(fn ($row) => ['id' => $row->id, 'stage' => $row->stage, 'assigned_to' => $row->assigned_to, 'status' => $row->status])->all(),
    'fabrics' => App\Models\Man\Fabric::where('manufacturing_order_id', $orderId)
        ->get(['id', 'code', 'status', 'sales_order_id'])->map(fn ($row) => ['id' => $row->id, 'code' => $row->code, 'status' => $row->status])->all(),
    'batch_items' => App\Models\Man\DyeingBatchFabric::whereHas('fabric', fn ($query) => $query->where('manufacturing_order_id', $orderId))
        ->get(['id', 'fabric_id', 'current_stage', 'status', 'requires_bleaching', 'dyeing_batch_id'])
        ->map(fn ($row) => ['fabric_id' => $row->fabric_id, 'stage' => $row->current_stage, 'status' => $row->status, 'requires_bleaching' => $row->requires_bleaching, 'batch_id' => $row->dyeing_batch_id])->all(),
    'dyeing_machines' => App\Models\Man\Machine::where('type', 'dyeing')->get(['id', 'machine_no', 'power', 'load_capacity_kg', 'status'])
        ->map(fn ($row) => ['id' => $row->id, 'machine_no' => $row->machine_no, 'power' => $row->power, 'capacity' => $row->load_capacity_kg, 'status' => $row->status])->all(),
    'dyeing_machine_staff' => App\Models\Man\KnittingMachineStaffAssignment::whereHas('machine', fn ($query) => $query->where('type', 'dyeing'))
        ->with('staff:id,manufacturing_role')->get()->map(fn ($row) => ['machine_id' => $row->machine_id, 'staff_id' => $row->staff_id, 'role' => $row->staff?->manufacturing_role])->all(),
];

echo json_encode($payload, JSON_PRETTY_PRINT);
