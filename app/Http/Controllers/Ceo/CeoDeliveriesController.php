<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Models\Logistics\Delivery;
use App\Models\Man\Package as ManPackage;
use App\Models\Ord\OrderStatusHistory;
use App\Models\Ord\PurchaseOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CeoDeliveriesController extends Controller
{
    /**
     * CEO Deliveries tracking — every outbound delivery with client,
     * truck + driver + conductors, route, schedule vs actuals, proof of
     * delivery and the linked sales/job order + client PO + packages.
     * Read-only aggregates.
     */
    public function index(Request $request)
    {
        $status = (string) $request->get('status', '');
        $q = trim((string) $request->get('q', ''));
        $from = $request->get('from');
        $to = $request->get('to');

        $deliveries = Delivery::with([
                'truck',
                'driver.user',
                'conductor1.user',
                'conductor2.user',
                'route.client',
                'proofOfDelivery',
                'conductorReports',
                'packages.product',
            ])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('delivery_number', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%")
                        ->orWhereHas('truck', fn ($t) => $t
                            ->where('truck_number', 'like', "%{$q}%")
                            ->orWhere('plate_number', 'like', "%{$q}%"))
                        ->orWhereHas('driver.user', fn ($u) => $u->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('route', fn ($r) => $r->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('route.client', fn ($c) => $c->where('company_name', 'like', "%{$q}%"));
                });
            })
            ->when($from, fn ($query) => $query->whereDate('scheduled_departure', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('scheduled_departure', '<=', $to))
            ->latest('scheduled_departure')
            ->take(300)
            ->get();

        // ── Resolve the order genealogy ───────────────────────────────────
        // warehouse_packages carry the printed package number, which matches
        // man `packages.code`; that row links to a fabric, which links to the
        // sales order (job order) and its client. manufacturing_order_id is
        // historically nullable, so we bridge on the package number instead.
        $packageNumbers = $deliveries
            ->flatMap(fn ($d) => $d->packages->pluck('package_number'))
            ->filter()->unique()->values();

        $manPackages = $packageNumbers->isNotEmpty()
            ? ManPackage::with([
                    'fabric.salesOrder.client',
                    'fabric.salesOrder.recipe.product',
                ])
                ->whereIn('code', $packageNumbers)
                ->get()
                ->keyBy('code')
            : collect();

        $salesOrders = $manPackages
            ->map(fn ($p) => $p->fabric?->salesOrder)
            ->filter()
            ->unique('id')
            ->keyBy('id');

        // Client POs are referenced by po_number string (not id) — batch map.
        $poNumbers = $salesOrders
            ->map(fn ($so) => $so->purchase_order_id)
            ->filter()->unique()->values();
        $pos = $poNumbers->isNotEmpty()
            ? PurchaseOrder::whereIn('po_number', $poNumbers)->get()->keyBy('po_number')
            : collect();

        // Status-flip audit trail for every linked sales order (timeline).
        $soIds = $salesOrders->keys()->all();
        $history = $soIds
            ? OrderStatusHistory::where('orderable_type', \App\Models\Ord\SalesOrder::class)
                ->whereIn('orderable_id', $soIds)
                ->orderBy('created_at')
                ->get()
                ->groupBy('orderable_id')
            : collect();

        $deliveries = $deliveries->map(function ($d) use ($manPackages, $salesOrders, $pos, $history) {
            // Primary linked order = first package that resolves to a sales order.
            $linkedSo = null;
            foreach ($d->packages as $p) {
                $so = $manPackages->get($p->package_number)?->fabric?->salesOrder;
                if ($so) {
                    $linkedSo = $so;
                    break;
                }
            }

            $client = $d->route?->client ?? $linkedSo?->client;
            $po = $linkedSo?->purchase_order_id ? ($pos[$linkedSo->purchase_order_id] ?? null) : null;

            $route = $d->route;
            $truck = $d->truck;
            $driver = $d->driver;

            return [
                'id' => $d->id,
                'delivery_number' => $d->delivery_number,
                'status' => $d->status,
                'notes' => $d->notes,
                'client_confirmed' => str_contains((string) $d->notes, 'Client confirmed receipt'),

                'client' => $client ? [
                    'id' => $client->id,
                    'company_name' => $client->company_name,
                    'contact_person' => $client->contact_person,
                    'phone' => $client->phone,
                    'email' => $client->email,
                    'address' => trim(collect([$client->company_address, $client->city, $client->province])->filter()->implode(', ')),
                ] : null,

                'order' => $linkedSo ? [
                    'jo_number' => $linkedSo->jo_number,
                    'control_number' => $linkedSo->control_number,
                    'po_number' => $linkedSo->purchase_order_id,
                    'po_payment' => $po?->payment_status,
                    'po_delivery_date' => $po?->delivery_date,
                    'status' => $linkedSo->status,
                    'color' => $linkedSo->color,
                    'design' => $linkedSo->design,
                    'quantity' => $linkedSo->quantity,
                    'yarn_type' => $linkedSo->yarn_type,
                    'expected_ship_date' => $linkedSo->expected_ship_date,
                    'confirmed_at' => $linkedSo->confirmed_at,
                ] : null,

                'route' => $route ? [
                    'name' => $route->name,
                    'origin' => $route->origin,
                    'destination' => $route->destination,
                    'distance_km' => $route->distance_km,
                    'estimated_minutes' => $route->estimated_minutes,
                    'waypoints_count' => is_array($route->waypoints) ? count($route->waypoints) : 0,
                ] : null,

                'truck' => $truck ? [
                    'truck_number' => $truck->truck_number,
                    'plate_number' => $truck->plate_number,
                    'model' => $truck->model,
                    'year' => $truck->year,
                    'mileage' => $truck->mileage,
                    'status' => $truck->status,
                ] : null,

                'driver' => $driver ? [
                    'name' => $driver->user?->name,
                    'license_number' => $driver->license_number,
                    'rating' => $driver->rating,
                    'phone' => $driver->user?->telephone ?? null,
                ] : null,

                'conductors' => collect([$d->conductor1, $d->conductor2])
                    ->filter()
                    ->map(fn ($c) => $c->user?->name)
                    ->filter()->values(),

                'conductor_reports' => $d->conductorReports->map(fn ($r) => [
                    'report_text' => $r->report_text,
                    'created_at' => $r->created_at,
                ])->values(),

                'scheduled_departure' => $d->scheduled_departure,
                'actual_departure' => $d->actual_departure,
                'arrival_time' => $d->arrival_time,
                'pod_at' => $d->proofOfDelivery?->delivered_at,
                'pod_notes' => $d->proofOfDelivery?->notes,
                'pod_image' => $d->proofOfDelivery?->image_path,

                'timeline' => $linkedSo
                    ? ($history[$linkedSo->id] ?? collect())->map(fn ($h) => [
                        'from' => $h->from_status,
                        'to' => $h->to_status,
                        'notes' => $h->notes,
                        'at' => $h->created_at,
                    ])->values()
                    : collect(),

                'packages_count' => $d->packages->count(),
                'packages' => $d->packages->map(function ($p) use ($manPackages) {
                    $so = $manPackages->get($p->package_number)?->fabric?->salesOrder;

                    return [
                        'package_number' => $p->package_number,
                        'quantity' => $p->quantity,
                        'status' => $p->status,
                        'product_name' => $p->product?->name,
                        'product_sku' => $p->product?->sku,
                        'jo_number' => $so?->jo_number,
                    ];
                })->values(),
            ];
        });

        $all = $deliveries;

        return Inertia::render('Dashboard/CEO/Deliveries', [
            'filters' => ['status' => $status, 'q' => $q, 'from' => $from, 'to' => $to],
            'summary' => [
                'total' => $all->count(),
                'pending' => $all->where('status', 'pending')->count(),
                'inTransit' => $all->whereIn('status', ['dispatched', 'in_transit'])->count(),
                'delivered' => $all->where('status', 'delivered')->count(),
                'confirmed' => $all->where('client_confirmed', true)->count(),
                'packages' => $all->sum('packages_count'),
                'withProof' => $all->whereNotNull('pod_image')->count(),
            ],
            'deliveries' => $all->values(),
        ]);
    }
}
