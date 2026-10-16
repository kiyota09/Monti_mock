<?php

namespace App\Http\Controllers\Ord;

use App\Http\Controllers\Controller;
use App\Models\Man\ManufacturingOrder;
use App\Models\Ord\OrderQueue;
use App\Models\Ord\SalesOrder;
use App\Services\Ord\OrderLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OrdProductionsController extends Controller
{
    public function __construct(private OrderLifecycleService $lifecycle) {}

    public function index()
    {
        $manufacturingOrders = ManufacturingOrder::with([
                'salesOrder.bomRecord.client',
                'salesOrder.client',
                'purchaseOrder.client',
            ])
            ->where('status', '!=', 'completed')
            ->latest()
            ->get()
            ->map(function ($mo) {
                $totalQty = $mo->total_quantity;
                $completedQty = $totalQty - $mo->remaining_quantity;
                $progress = $totalQty > 0 ? round(($completedQty / $totalQty) * 100) : 0;

                $orderNumber = $mo->salesOrder
                    ? $mo->salesOrder->jo_number
                    : ($mo->purchaseOrder ? $mo->purchaseOrder->po_number : 'N/A');

                $clientName = $mo->salesOrder
                    ? ($mo->salesOrder->client?->company_name ?? $mo->salesOrder->bomRecord?->client?->company_name ?? 'N/A')
                    : ($mo->purchaseOrder->client->company_name ?? 'N/A');

                $productName = $mo->salesOrder
                    ? ($mo->salesOrder->bomRecord->product->name ?? $mo->salesOrder->design ?? 'N/A')
                    : 'N/A';

                return [
                    'id' => $mo->id,
                    'order_number' => $orderNumber,
                    'client_name' => $clientName,
                    'product_name' => $productName,
                    'total_quantity' => $totalQty,
                    'completed_quantity' => $completedQty,
                    'progress' => $progress,
                    'status' => $mo->status,
                    'notes' => $mo->notes,
                    'created_at' => $mo->created_at->format('Y-m-d H:i'),
                ];
            });

        // Job orders cleared for release: confirmed/planned with no active MO.
        $releasable = SalesOrder::with(['client', 'bomRecord.product'])
            ->whereIn('status', ['confirmed', 'in_planning'])
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
     * Release a confirmed job order to the plant floor.
     *
     * Mirrors MAN's forwardToChecker so ORD can initiate production, then
     * advances the SO to in_production, stamps the queue, and writes history
     * — atomically.
     */
    public function release(Request $request, int $id)
    {
        $so = SalesOrder::findOrFail($id);

        if (! in_array(OrderLifecycleService::canonicalSoStatus($so->status), ['confirmed', 'in_planning'], true)) {
            return back()->withErrors(['error' => 'Only confirmed job orders can be released to production.']);
        }

        $exists = ManufacturingOrder::where('sales_order_id', $so->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->exists();
        if ($exists) {
            return back()->withErrors(['error' => 'This job order already has an active manufacturing order.']);
        }

        DB::transaction(function () use ($so) {
            ManufacturingOrder::create([
                'sales_order_id' => $so->id,
                'total_quantity' => (int) $so->quantity,
                'remaining_quantity' => (int) $so->quantity,
                'status' => 'pending',
                'notes' => 'Released to production by '.Auth::user()->name.' (ORD)',
            ]);

            $from = $so->status;
            $so->update(['status' => 'in_production', 'production_started_at' => now()]);
            $this->lifecycle->recordHistory($so, 'SO', $from, 'in_production', Auth::id(), 'Released to production (ORD)');

            OrderQueue::updateOrCreate(
                ['sales_order_id' => $so->id],
                ['stage' => 'man_production', 'man_started_at' => now()]
            );
        });

        return back()->with('success', "Job order {$so->jo_number} released to production.");
    }
}
