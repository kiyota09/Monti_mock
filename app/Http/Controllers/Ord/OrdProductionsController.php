<?php

namespace App\Http\Controllers\Ord;

use App\Http\Controllers\Controller;
use App\Models\Man\ManufacturingOrder;
use App\Models\Ord\OrderQueue;
use App\Models\Ord\SalesOrder;
use App\Services\Ord\OrderLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrdProductionsController extends Controller
{
    public function __construct(private OrderLifecycleService $lifecycle) {}

    public function index()
    {
        $manufacturingOrders = ManufacturingOrder::with([
                'salesOrder.bomRecord.product',
                'salesOrder.bomRecord.client',
                'salesOrder.client',
                'purchaseOrder.client',
            ])
            ->where('status', '!=', 'completed')
            ->latest()
            ->get()
            ->map(function ($mo) {
                $totalQty = (float) $mo->total_quantity;
                $completedQty = max(0, $totalQty - (float) $mo->remaining_quantity);
                $progress = $totalQty > 0 ? round(($completedQty / $totalQty) * 100) : 0;

                $orderNumber = $mo->salesOrder?->jo_number
                    ?? $mo->purchaseOrder?->po_number
                    ?? 'N/A';

                $clientName = $mo->salesOrder
                    ? ($mo->salesOrder->client?->company_name ?? $mo->salesOrder->bomRecord?->client?->company_name ?? 'N/A')
                    : ($mo->purchaseOrder?->client?->company_name ?? 'N/A');

                $productName = $mo->salesOrder
                    ? ($mo->salesOrder->bomRecord?->product?->name ?? $mo->salesOrder->design ?? 'N/A')
                    : 'N/A';

                return [
                    'id' => $mo->id,
                    'sales_order_id' => $mo->sales_order_id,
                    'order_number' => $orderNumber,
                    'client_name' => $clientName,
                    'product_name' => $productName,
                    'total_quantity' => $totalQty,
                    'completed_quantity' => $completedQty,
                    'remaining_quantity' => (float) $mo->remaining_quantity,
                    'unit' => 'kg',
                    'progress' => $progress,
                    'status' => $mo->status,
                    'notes' => $mo->notes,
                    'created_at' => $mo->created_at?->format('Y-m-d H:i'),
                ];
            });

        // Job orders cleared for release: confirmed/planned with no active MO.
        $releasable = SalesOrder::with(['client', 'bomRecord.product'])
            ->whereIn('status', [
                'confirmed', 'in_planning',
                'pushed_to_ordermgmt', 'pushed_to_scm', 'inv_check', 'inv_checked',
            ])
            ->whereDoesntHave('manufacturingOrder', fn ($q) => $q->whereIn('status', ['pending', 'in_progress']))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($so) => [
                'id' => $so->id,
                'jo_number' => $so->jo_number,
                'client_name' => $so->client?->company_name ?? $so->bomRecord?->client?->company_name ?? 'N/A',
                'product_name' => $so->bomRecord?->product?->name ?? $so->design ?? 'Custom fabric',
                'quantity' => (float) $so->quantity,
                'unit' => 'kg',
                'status' => $so->status,
                'priority' => $so->priority ?? 'normal',
                'expected_ship_date' => $so->expected_ship_date?->format('Y-m-d'),
            ]);

        return Inertia::render('Dashboard/ORD/Productions', [
            'productions' => $manufacturingOrders,
            'releasable' => $releasable,
        ]);
    }

    /**
     * Release a confirmed job order to the plant floor. The status change is
     * delegated to the lifecycle service so the plant and ORD never disagree.
     */
    public function release(Request $request, int $id)
    {
        $joNumber = DB::transaction(function () use ($id, $request) {
            $so = SalesOrder::whereKey($id)->lockForUpdate()->firstOrFail();
            if (! in_array(OrderLifecycleService::canonicalSoStatus($so->status), ['confirmed', 'in_planning'], true)) {
                throw ValidationException::withMessages(['error' => 'Only confirmed or planned job orders can be released to production.']);
            }

            $exists = ManufacturingOrder::where('sales_order_id', $so->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages(['error' => 'This job order already has an active manufacturing order.']);
            }

            ManufacturingOrder::create([
                'sales_order_id' => $so->id,
                'total_quantity' => $so->quantity,
                'remaining_quantity' => $so->quantity,
                'status' => 'pending',
                'notes' => 'Released to production by '.$request->user()->name.' (ORD)',
            ]);

            if (! in_array('in_production', OrderLifecycleService::allowedSoTransitions($so->status), true)) {
                $so = $this->lifecycle->transitionSalesOrder($so, 'in_planning', $request->user(), 'Planned before production release (ORD)');
            }
            $this->lifecycle->transitionSalesOrder($so, 'in_production', $request->user(), 'Released to production (ORD)');

            OrderQueue::updateOrCreate(
                ['sales_order_id' => $so->id],
                ['stage' => 'man_production', 'man_started_at' => now()]
            );
            return $so->jo_number;
        }, 3);

        return back()->with('success', "Job order {$joNumber} released to production.");
    }
}
