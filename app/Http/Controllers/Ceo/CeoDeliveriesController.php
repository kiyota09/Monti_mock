<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Models\Logistics\Delivery;
use App\Models\Ord\PurchaseOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CeoDeliveriesController extends Controller
{
    /**
     * CEO Deliveries tracking — every outbound delivery with client,
     * truck + driver, schedule vs actuals, proof of delivery, linked
     * sales/job order + client PO, client confirmation and PO payment.
     * Read-only aggregates.
     */
    public function index(Request $request)
    {
        $status = $request->get('status', '');
        $q = trim((string) $request->get('q', ''));

        $deliveries = Delivery::with([
                'truck', 'driver.user', 'route.client', 'proofOfDelivery',
                'packages.manufacturingOrder.salesOrder.client',
                'packages.manufacturingOrder.salesOrder',
            ])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($q !== '', fn ($query) => $query
                ->where('delivery_number', 'like', "%{$q}%")
                ->orWhereHas('truck', fn ($t) => $t->where('truck_number', 'like', "%{$q}%")->orWhere('plate_number', 'like', "%{$q}%"))
                ->orWhereHas('route.client', fn ($c) => $c->where('company_name', 'like', "%{$q}%")))
            ->latest('scheduled_departure')
            ->take(200)
            ->get();

        // Client POs are referenced by po_number string (not id) — batch map.
        $poNumbers = $deliveries
            ->flatMap(fn ($d) => $d->packages->map(fn ($p) => $p->manufacturingOrder?->salesOrder?->purchase_order_id))
            ->filter()->unique()->values();
        $pos = PurchaseOrder::whereIn('po_number', $poNumbers)->get()->keyBy('po_number');

        $deliveries = $deliveries->map(function ($d) use ($pos) {
                // Linked order: first package → manufacturing order → sales order.
                $mo = $d->packages->first()?->manufacturingOrder;
                $so = $mo?->salesOrder;
                $client = $d->route?->client ?? $so?->client;
                $po = $so?->purchase_order_id ? ($pos[$so->purchase_order_id] ?? null) : null;

                return [
                    'id' => $d->id,
                    'delivery_number' => $d->delivery_number,
                    'status' => $d->status,
                    'client_name' => $client?->company_name ?? '—',
                    'jo_number' => $so?->jo_number,
                    'po_number' => $so?->purchase_order_id,
                    'po_payment' => $po?->payment_status,
                    'truck' => $d->truck ? trim(($d->truck->truck_number ?? '').' '.($d->truck->plate_number ?? '')) : '—',
                    'driver' => $d->driver?->user?->name ?? '—',
                    'route' => $d->route?->name ?? trim(($d->route?->origin ?? '').' → '.($d->route?->destination ?? '')) ?: '—',
                    'scheduled_departure' => $d->scheduled_departure,
                    'actual_departure' => $d->actual_departure,
                    'arrival_time' => $d->arrival_time,
                    'pod_at' => $d->proofOfDelivery?->delivered_at,
                    'pod_notes' => $d->proofOfDelivery?->notes,
                    'client_confirmed' => str_contains((string) $d->notes, 'Client confirmed receipt'),
                    'packages_count' => $d->packages->count(),
                    'packages' => $d->packages->map(fn ($p) => [
                        'package_number' => $p->package_number,
                        'quantity' => $p->quantity,
                        'status' => $p->status,
                    ])->values(),
                ];
            });

        return Inertia::render('Dashboard/CEO/Deliveries', [
            'filters' => ['status' => $status, 'q' => $q],
            'summary' => [
                'total' => $deliveries->count(),
                'inTransit' => $deliveries->whereIn('status', ['dispatched', 'in_transit'])->count(),
                'delivered' => $deliveries->where('status', 'delivered')->count(),
                'confirmed' => $deliveries->where('client_confirmed', true)->count(),
            ],
            'deliveries' => $deliveries->values(),
        ]);
    }
}
