<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Logistics\Delivery;
use App\Models\Ord\PurchaseOrder;
use App\Models\Ord\SalesOrder;
use App\Services\Ord\OrderLifecycleService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClientTrackingController extends Controller
{
    /**
     * Order Tracking worklist for the B2B client portal.
     *
     * Mirrors the internal PRO "Orders to Suppliers" tracking UI and the
     * ORD delivery tracking UI, but scoped to the authenticated client's
     * own purchase orders, job orders and shipments:
     *
     *   PO intake -> approval -> released -> SO production pipeline
     *   -> dispatch / in-transit -> delivered / completed
     */
    public function index()
    {
        $clientId = Auth::guard('client')->id();

        $pos = PurchaseOrder::with(['items.product', 'statusHistory'])
            ->where('client_id', $clientId)
            ->latest()
            ->get();

        $sos = SalesOrder::with(['bomRecord.product', 'manufacturingOrder', 'statusHistory'])
            ->where('client_id', $clientId)
            ->latest()
            ->get();

        $deliveries = Delivery::with([
                'driver.user', 'truck', 'route',
                'packages.product', 'packages.manufacturingOrder.salesOrder',
                'proofOfDelivery',
            ])
            ->whereHas('route', fn ($q) => $q->where('client_id', $clientId))
            ->whereIn('status', ['dispatched', 'in_transit', 'delivered'])
            ->latest('scheduled_departure')
            ->get();

        $orders = $pos->map(fn ($po) => $this->poTrackRow($po, $sos, $deliveries))
            ->concat($sos->map(fn ($so) => $this->soTrackRow($so, $deliveries)))
            ->sortByDesc('updated_at')
            ->values();

        return Inertia::render('Client/Tracking', [
            'orders' => $orders,
            'stats' => [
                'total' => $orders->count(),
                'inProduction' => $orders->whereIn('group', ['confirmed', 'production', 'ready'])->count(),
                'inTransit' => $orders->where('group', 'transit')->count(),
                'delivered' => $orders->where('group', 'done')->count(),
            ],
            'deliveries' => $deliveries->map(fn ($d) => $this->deliveryRow($d)),
        ]);
    }

    /**
     * 360° tracking detail for one PO or SO — same layout language as
     * the internal ORD Track page (milestone header, lines, production,
     * shipment, history).
     */
    public function show(string $type, int $id)
    {
        $clientId = Auth::guard('client')->id();
        $type = strtoupper($type);
        abort_unless(in_array($type, ['PO', 'SO'], true), 404);

        $deliveries = Delivery::with([
                'driver.user', 'truck', 'route',
                'packages.product', 'packages.manufacturingOrder.salesOrder',
                'proofOfDelivery',
            ])
            ->whereHas('route', fn ($q) => $q->where('client_id', $clientId))
            ->whereIn('status', ['dispatched', 'in_transit', 'delivered'])
            ->latest('scheduled_departure')
            ->get();

        if ($type === 'PO') {
            $po = PurchaseOrder::with(['items.product', 'statusHistory.changedBy', 'salesOrders.manufacturingOrder'])
                ->where('client_id', $clientId)
                ->findOrFail($id);

            $linkedSos = SalesOrder::with(['bomRecord.product', 'manufacturingOrder', 'statusHistory'])
                ->where('client_id', $clientId)
                ->where('purchase_order_id', $po->po_number)
                ->get();

            $linkedDeliveries = $this->deliveriesForSos($deliveries, $linkedSos->pluck('id')->all());

            return Inertia::render('Client/TrackingShow', [
                'orderType' => 'PO',
                'order' => $this->poTrackRow($po, $linkedSos, $deliveries),
                'lines' => $po->items->map(fn ($i) => [
                    'id' => $i->id,
                    'product' => $i->product?->name ?? '—',
                    'quantity' => (float) $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                    'line_total' => (float) ($i->line_total ?? $i->quantity * $i->unit_price),
                ]),
                'linkedOrders' => $linkedSos->map(fn ($so) => $this->soTrackRow($so, $deliveries)),
                'deliveries' => $linkedDeliveries->map(fn ($d) => $this->deliveryRow($d))->values(),
                'history' => $this->historyRows($po),
            ]);
        }

        $so = SalesOrder::with([
                'bomRecord.product', 'manufacturingOrder',
                'statusHistory.changedBy', 'returns',
            ])
            ->where('client_id', $clientId)
            ->findOrFail($id);

        $linkedDeliveries = $this->deliveriesForSos($deliveries, [$so->id]);

        return Inertia::render('Client/TrackingShow', [
            'orderType' => 'SO',
            'order' => $this->soTrackRow($so, $deliveries),
            'lines' => [[
                'id' => $so->id,
                'product' => $so->bomRecord?->product?->name ?? $so->design ?? 'Custom fabric',
                'quantity' => (float) $so->quantity,
                'unit_price' => (float) $so->unit_price,
                'line_total' => (float) $so->total_amount,
            ]],
            'linkedOrders' => [],
            'deliveries' => $linkedDeliveries->map(fn ($d) => $this->deliveryRow($d))->values(),
            'history' => $this->historyRows($so),
        ]);
    }

    // ── Row builders ──────────────────────────────────────────────

    private function poTrackRow($po, $sos, $deliveries): array
    {
        $steps = $this->poSteps($po->status);
        $linkedCount = $sos instanceof \Illuminate\Support\Collection
            ? $sos->where('purchase_order_id', $po->po_number)->count()
            : 0;

        return [
            'id' => $po->id,
            'type' => 'PO',
            'number' => $po->po_number,
            'total' => (float) $po->total_amount,
            'status' => $po->status,
            'group' => OrderLifecycleService::poGroup($po->status),
            'priority' => $po->priority ?? 'normal',
            'expected_ship_date' => $po->expected_ship_date?->format('Y-m-d'),
            'delivery_date' => $po->delivery_date?->format('Y-m-d'),
            'date' => $po->created_at?->format('Y-m-d'),
            'updated_at' => $po->updated_at?->format('Y-m-d H:i'),
            'steps' => $steps,
            'progress' => $this->progress($steps),
            'linked_count' => $linkedCount,
            'item_count' => $po->items->count(),
            'items_summary' => $po->items->take(3)->map(fn ($i) => $i->product?->name ?? 'Item')->values()->all(),
        ];
    }

    private function soTrackRow($so, $deliveries): array
    {
        $steps = $this->soSteps($so->status);
        $shipment = $this->deliveriesForSos($deliveries, [$so->id])->first();

        return [
            'id' => $so->id,
            'type' => 'SO',
            'number' => $so->jo_number,
            'total' => (float) $so->total_amount,
            'status' => $so->status,
            'group' => OrderLifecycleService::soGroup($so->status),
            'priority' => $so->priority ?? 'normal',
            'product_name' => $so->bomRecord?->product?->name ?? $so->design ?? 'Custom fabric',
            'quantity' => (float) $so->quantity,
            'expected_ship_date' => $so->expected_ship_date?->format('Y-m-d'),
            'date' => $so->created_at?->format('Y-m-d'),
            'updated_at' => $so->updated_at?->format('Y-m-d H:i'),
            'steps' => $steps,
            'progress' => $this->progress($steps),
            'manufacturing' => $so->manufacturingOrder ? [
                'status' => $so->manufacturingOrder->status,
                'total' => $so->manufacturingOrder->total_quantity,
                'remaining' => $so->manufacturingOrder->remaining_quantity,
            ] : null,
            'shipment' => $shipment ? [
                'delivery_number' => $shipment->delivery_number,
                'status' => $shipment->status,
                'driver_name' => $shipment->driver?->user?->name,
                'truck_number' => $shipment->truck?->truck_number,
                'origin' => $shipment->route?->origin,
                'destination' => $shipment->route?->destination,
            ] : null,
        ];
    }

    private function deliveryRow($delivery): array
    {
        return [
            'id' => $delivery->id,
            'delivery_number' => $delivery->delivery_number,
            'status' => $delivery->status,
            'scheduled_departure' => $delivery->scheduled_departure?->format('Y-m-d H:i'),
            'actual_departure' => $delivery->actual_departure?->format('Y-m-d H:i'),
            'arrival_time' => $delivery->arrival_time?->format('Y-m-d H:i'),
            'driver_name' => $delivery->driver?->user?->name,
            'truck_number' => $delivery->truck?->truck_number,
            'route_name' => $delivery->route?->name,
            'origin' => $delivery->route?->origin,
            'destination' => $delivery->route?->destination,
            'package_count' => $delivery->packages->count(),
            'packages' => $delivery->packages->map(fn ($pkg) => [
                'package_number' => $pkg->package_number,
                'product_name' => $pkg->product?->name,
                'quantity' => $pkg->quantity,
            ]),
            'has_proof' => (bool) $delivery->proofOfDelivery,
        ];
    }

    // ── Timelines (same canonical pipeline as ORD) ────────────────

    private function poSteps(?string $status): array
    {
        $order = ['credit_review' => 1, 'tier_assignment' => 2, 'pending_client_approval' => 3, 'approved' => 4, 'released_to_production' => 5, 'production' => 5, 'completed' => 6];
        $labels = [
            ['key' => 'credit_review', 'label' => 'Credit review'],
            ['key' => 'pending_client_approval', 'label' => 'Your approval'],
            ['key' => 'approved', 'label' => 'Approved'],
            ['key' => 'released_to_production', 'label' => 'In production'],
            ['key' => 'completed', 'label' => 'Completed'],
        ];
        $current = $order[(string) $status] ?? 0;
        $terminal = in_array($status, ['cancelled', 'on_hold'], true);

        return collect($labels)->map(function ($s, $i) use ($current, $terminal) {
            $idx = $i + 1;
            // Map label index onto the 1..6 order scale.
            $scale = [1, 3, 4, 5, 6][$i];

            return [
                ...$s,
                'done' => ! $terminal && $scale <= $current,
                'current' => ! $terminal && $scale === $current,
            ];
        })->toArray();
    }

    private function soSteps(?string $status): array
    {
        $canonical = OrderLifecycleService::canonicalSoStatus($status);
        $labels = [
            ['key' => 'confirmed', 'label' => 'Confirmed'],
            ['key' => 'in_planning', 'label' => 'Planning'],
            ['key' => 'in_production', 'label' => 'Production'],
            ['key' => 'production_done', 'label' => 'Ready'],
            ['key' => 'in_transit', 'label' => 'In transit'],
            ['key' => 'delivered', 'label' => 'Delivered'],
            ['key' => 'completed', 'label' => 'Completed'],
        ];
        $flow = ['pending' => 0, 'confirmed' => 1, 'in_planning' => 2, 'in_production' => 3, 'production_done' => 4, 'ready_for_dispatch' => 4, 'in_transit' => 5, 'delivered' => 6, 'completed' => 7];
        $current = $flow[$canonical] ?? 0;
        $terminal = in_array($canonical, ['cancelled', 'on_hold', 'returned'], true);

        return collect($labels)->map(function ($s, $i) use ($current, $terminal) {
            $idx = $i + 1;

            return [
                ...$s,
                'done' => ! $terminal && $idx <= $current,
                'current' => ! $terminal && $idx === $current,
            ];
        })->toArray();
    }

    private function progress(array $steps): int
    {
        if (! count($steps)) {
            return 0;
        }
        $done = collect($steps)->where('done')->count();

        return (int) round($done / count($steps) * 100);
    }

    private function deliveriesForSos($deliveries, array $soIds)
    {
        if (! count($soIds)) {
            return collect();
        }

        return $deliveries->filter(function ($d) use ($soIds) {
            foreach ($d->packages as $pkg) {
                $soId = $pkg->manufacturingOrder?->salesOrder?->id;
                if ($soId && in_array($soId, $soIds, true)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    private function historyRows($order): array
    {
        return $order->statusHistory->map(fn ($h) => [
            'from' => $h->from_status,
            'to' => $h->to_status,
            'by' => $h->changedBy?->name ?? 'Monti Textile',
            'notes' => $h->notes,
            'at' => $h->created_at?->format('M d, Y H:i'),
        ])->toArray();
    }
}
