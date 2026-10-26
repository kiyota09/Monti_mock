<?php

namespace App\Http\Controllers\Scm;

use App\Http\Controllers\Controller;
use App\Models\Man\ManufacturingOrder;
use App\Models\Ord\OrderQueue;
use App\Models\Ord\PurchaseOrder;
use App\Models\Ord\SalesOrder;
use App\Services\Eco\StockSustainabilityService;
use App\Services\Ord\OrderLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ScmSalesOrderController extends Controller
{
    public function index()
    {
        // ─── Existing Purchase Orders (from ECO via PO) ─────────────────────
        $purchaseOrders = PurchaseOrder::with(['client', 'items.product'])
            ->whereHas('queue', function ($q) {
                $q->whereIn('stage', ['eco_approved', 'scm_received', 'inv_check']);
            })
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($order) {
                $queue = $order->queue;
                return [
                    'type' => 'purchase_order',
                    'id' => $order->id,
                    'po_number' => $order->po_number,
                    'client_name' => $order->client->company_name,
                    'total_amount' => $order->total_amount,
                    'created_at' => $order->created_at,
                    'stage' => $queue ? $queue->stage : 'eco_approved',
                    'inv_check_sufficient' => $queue ? $queue->inv_check_sufficient : null,
                    'items' => $order->items->map(fn($item) => [
                        'product_name' => $item->product->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                    ]),
                    'sales_order_id' => null,
                ];
            });

        // ─── Sales Orders pushed from ECO (status = pushed_to_scm) ──────────
        $salesOrders = SalesOrder::whereIn('status', ['pushed_to_scm', 'inv_check', 'inv_checked', 'in_production'])
            ->with(['client', 'recipe'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($order) {
                // Determine current stage based on status
                $stage = $order->status;
                $invCheckSufficient = null;
                if ($stage === 'inv_checked') {
                    $invCheckSufficient = true;
                } elseif ($stage === 'inv_check') {
                    $invCheckSufficient = false;
                }

                $clientName = $order->client->company_name ?? 'N/A';
                // Build a single "item" from sales order data
                $items = [];
                if ($order->color || $order->yarn_type) {
                    $productName = trim(($order->color ?? '') . ' ' . ($order->yarn_type ?? ''));
                    $items[] = [
                        'product_name' => $productName ?: 'Custom Product',
                        'quantity' => $order->quantity ?? 0,
                        'unit_price' => $order->total_amount && $order->quantity
                            ? $order->total_amount / $order->quantity
                            : 0,
                    ];
                }
                return [
                    'type' => 'sales_order',
                    'id' => $order->id,
                    'po_number' => $order->jo_number ?? 'JO-' . $order->id,
                    'client_name' => $clientName,
                    'total_amount' => $order->total_amount ?? 0,
                    'created_at' => $order->created_at,
                    'stage' => $stage,
                    'inv_check_sufficient' => $invCheckSufficient,
                    'items' => $items,
                    'sales_order_id' => $order->id,
                    'color' => $order->color,
                    'yarn_type' => $order->yarn_type,
                    'quantity' => $order->quantity,
                ];
            });

        // Merge both collections
        $orders = $purchaseOrders->concat($salesOrders)->sortByDesc('created_at')->values();

        return Inertia::render('Dashboard/SCM/SalesOrder', [
            'orders' => $orders,
        ]);
    }

    /**
     * Push Purchase Order to Production
     */
    public function pushToProduction(PurchaseOrder $order)
    {
        $queue = $order->queue;
        if (!$queue || $queue->stage !== 'inv_checked' || !$queue->inv_check_sufficient) {
            return redirect()->back()->withErrors(['error' => 'Order cannot be pushed to production. Inventory check must be sufficient first.']);
        }
        $queue->update(['stage' => 'man_production']);
        return redirect()->back()->with('success', 'Purchase order pushed to Manufacturing.');
    }

    /**
     * Push Sales Order to Production.
     *
     * Single-check model: the manual SCM inventory-check buttons are gone
     * (checking happens once, in the ECO Push Center). Stock is re-verified
     * LIVE here with the same DSS engine before anything moves, so SCM can
     * never push an order the warehouse cannot sustain.
     */
    public function pushToProductionSalesOrder(
        SalesOrder $salesOrder,
        StockSustainabilityService $dss,
        OrderLifecycleService $lifecycle
    )
    {
        if (!in_array($salesOrder->status, ['pushed_to_scm', 'inv_check', 'inv_checked'])) {
            return redirect()->back()->withErrors(['error' => 'Sales order cannot be pushed to production from its current stage.']);
        }

        $result = $dss->evaluate($salesOrder);

        if (($result['verdict'] ?? null) === 'insufficient') {
            $lines = collect($result['materials'] ?? [])
                ->filter(fn ($m) => ($m['shortage'] ?? 0) > 0)
                ->map(fn ($m) => "{$m['material_name']}: needs {$m['required']}{$m['unit']}, ATP {$m['atp']}{$m['unit']} (short {$m['shortage']}{$m['unit']})")
                ->implode('; ');

            return redirect()->back()->withErrors(['error' => "Push blocked: live stock cannot sustain {$result['jo_number']}. {$lines}. File procurement from the ECO Push Center DSS check."]);
        }

        DB::transaction(function () use ($salesOrder, $lifecycle) {
            $lockedOrder = SalesOrder::whereKey($salesOrder->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedOrder->status, ['pushed_to_scm', 'inv_check', 'inv_checked'], true)) {
                throw ValidationException::withMessages(['error' => 'Sales order has already moved from the inventory-checked stage.']);
            }

            $lockedOrder->update(['inv_check_sufficient' => true]);

            // Hand the Job Order to Manufacturing. The knitting supervisor's
            // "JO Assignments" board (and the knitting intake gates) read
            // ManufacturingOrders, so pushing without creating one left the JO
            // invisible to the plant floor.
            $hasActive = ManufacturingOrder::where('sales_order_id', $lockedOrder->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->exists();

            if (! $hasActive) {
                ManufacturingOrder::create([
                    'sales_order_id' => $lockedOrder->id,
                    'total_quantity' => $lockedOrder->quantity,
                    'remaining_quantity' => $lockedOrder->quantity,
                    'status' => 'pending',
                    'notes' => 'Pushed to manufacturing by SCM (stock re-verified live)',
                ]);
            }

            // SCM's legacy statuses canonicalize to confirmed. Record both
            // lifecycle milestones instead of writing in_production directly.
            if (in_array('in_planning', OrderLifecycleService::allowedSoTransitions($lockedOrder->status), true)) {
                $lockedOrder = $lifecycle->transitionSalesOrder(
                    $lockedOrder,
                    'in_planning',
                    auth()->user(),
                    'Planned after live inventory re-check (SCM)'
                );
            }
            $lifecycle->transitionSalesOrder(
                $lockedOrder,
                'in_production',
                auth()->user(),
                'Released to production after live inventory re-check (SCM)'
            );

            OrderQueue::updateOrCreate(
                ['sales_order_id' => $lockedOrder->id],
                ['stage' => 'man_production', 'man_started_at' => now()]
            );
        }, 3);

        return redirect()->back()->with('success', 'Stock re-verified live — sales order pushed to Manufacturing.');
    }
}
