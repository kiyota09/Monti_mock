<?php

namespace App\Http\Controllers\Ord;

use App\Http\Controllers\Controller;
use App\Models\Logistics\Delivery;
use App\Models\Ord\SalesOrder;
use App\Services\Ord\OrderLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OrdDeliveryController extends Controller
{
    public function __construct(private OrderLifecycleService $lifecycle) {}

    /**
     * Outbound shipment worklist with status tabs and search.
     */
    public function index(Request $request)
    {
        $filters = [
            'status' => (string) $request->get('status', ''),
            'search' => trim((string) $request->get('search', '')),
        ];

        $statuses = ['dispatched', 'in_transit', 'delivered'];

        $all = Delivery::with([
            'driver.user',
            'truck',
            'route.client',
            'packages.product',
            'packages.manufacturingOrder.salesOrder',
            'conductor1.user',
            'conductor2.user',
            'proofOfDelivery',
        ])
            ->whereIn('status', $statuses)
            ->latest('scheduled_departure')
            ->get();

        $counts = ['all' => $all->count()];
        foreach ($all->groupBy('status') as $status => $group) {
            $counts[$status] = $group->count();
        }

        $rows = $all
            ->when($filters['status'] !== '', fn ($c) => $c->where('status', $filters['status']))
            ->when($filters['search'] !== '', function ($c) use ($filters) {
                $needle = strtolower($filters['search']);

                return $c->filter(function ($delivery) use ($needle) {
                    return str_contains(strtolower((string) $delivery->delivery_number), $needle)
                        || str_contains(strtolower((string) $delivery->route?->client?->company_name), $needle)
                        || str_contains(strtolower((string) $delivery->driver?->user?->name), $needle);
                });
            })
            ->values()
            ->map(fn ($delivery) => $this->deliveryRow($delivery));

        return Inertia::render('Dashboard/ORD/Delivery', [
            'deliveries' => $rows,
            'counts' => $counts,
            'filters' => $filters,
        ]);
    }

    /**
     * Shipment 360° view: milestones, crew, packages, linked job orders.
     */
    public function track(int $id)
    {
        $delivery = Delivery::with([
            'driver.user', 'truck', 'route.client',
            'packages.product', 'packages.manufacturingOrder.salesOrder.client',
            'conductor1.user', 'conductor2.user', 'proofOfDelivery',
        ])->findOrFail($id);

        $linkedOrders = $delivery->packages
            ->map(fn ($pkg) => $pkg->manufacturingOrder?->salesOrder)
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn ($so) => [
                'id' => $so->id,
                'jo_number' => $so->jo_number,
                'client_name' => $so->client?->company_name ?? 'N/A',
                'status' => $so->status,
                'quantity' => (float) $so->quantity,
            ]);

        return Inertia::render('Dashboard/ORD/Track', [
            'delivery' => [...$this->deliveryRow($delivery),
                'notes' => $delivery->notes,
                'proof' => $delivery->proofOfDelivery ? [
                    'received_at' => $delivery->proofOfDelivery->delivered_at?->format('Y-m-d H:i'),
                    'notes' => $delivery->proofOfDelivery->notes,
                    'image' => $delivery->proofOfDelivery->image_path,
                ] : null,
            ],
            'linkedOrders' => $linkedOrders,
            'timeline' => $this->timeline($delivery),
        ]);
    }

    /**
     * Pull a delivered + POD-confirmed shipment back into linked job orders.
     * Only forward-moving canonical steps are applied; no status is skipped.
     */
    public function syncStatus(int $id)
    {
        $delivery = Delivery::with([
            'packages.manufacturingOrder.salesOrder', 'proofOfDelivery',
        ])->findOrFail($id);

        if ($delivery->status !== 'delivered') {
            return back()->withErrors(['error' => 'Only delivered shipments can be synced to orders.']);
        }
        if (! $delivery->proofOfDelivery) {
            return back()->withErrors(['error' => 'Record a proof of delivery before syncing orders.']);
        }

        $updated = DB::transaction(function () use ($delivery) {
            $count = 0;
            $linked = $delivery->packages
                ->map(fn ($pkg) => $pkg->manufacturingOrder?->salesOrder)
                ->filter()
                ->unique('id');

            foreach ($linked as $so) {
                if (! $so instanceof SalesOrder) {
                    continue;
                }
                if (in_array($so->status, ['delivered', 'completed', 'cancelled'], true)) {
                    continue;
                }

                foreach (['production_done', 'ready_for_dispatch', 'in_transit', 'delivered'] as $step) {
                    if (in_array($step, OrderLifecycleService::allowedSoTransitions($so->status), true)) {
                        $so = $this->lifecycle->transitionSalesOrder(
                            $so, $step, auth()->user(), "Auto-sync from shipment {$delivery->delivery_number}"
                        );
                    }
                }
                if ($so->status === 'delivered') {
                    $count++;
                }
            }

            return $count;
        });

        return back()->with('success', "Synced {$updated} job order(s) to delivered from {$delivery->delivery_number}.");
    }

    private function deliveryRow($delivery): array
    {
        $linkedOrders = $delivery->packages
            ->map(fn ($pkg) => $pkg->manufacturingOrder?->salesOrder)
            ->filter()
            ->unique('id');

        $canSync = $delivery->status === 'delivered'
            && $delivery->proofOfDelivery
            && $linkedOrders->contains(
                fn ($so) => ! in_array($so->status, ['delivered', 'completed', 'cancelled'], true)
            );

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
            'client_name' => $delivery->route?->client?->company_name,
            'package_count' => $delivery->packages->count(),
            'linked_order_count' => $linkedOrders->count(),
            'has_pod' => (bool) $delivery->proofOfDelivery,
            'pod_received_at' => $delivery->proofOfDelivery?->delivered_at?->format('Y-m-d H:i'),
            'can_sync' => $canSync,
            'packages' => $delivery->packages->map(fn ($pkg) => [
                'package_number' => $pkg->package_number,
                'product_name' => $pkg->product?->name,
                'quantity' => $pkg->quantity,
            ]),
        ];
    }

    private function timeline($delivery): array
    {
        $steps = [
            ['key' => 'dispatched', 'label' => 'Dispatched', 'at' => $delivery->actual_departure ?? $delivery->scheduled_departure],
            ['key' => 'in_transit', 'label' => 'In transit', 'at' => $delivery->actual_departure],
            ['key' => 'delivered', 'label' => 'Delivered', 'at' => $delivery->arrival_time],
        ];

        $order = ['pending' => 0, 'dispatched' => 1, 'in_transit' => 2, 'delivered' => 3];
        $current = $order[$delivery->status] ?? 0;

        return collect($steps)->map(fn ($s, $i) => [
            ...$s,
            'done' => ($i + 1) <= $current,
            'current' => ($i + 1) === $current,
            'at' => $s['at']?->format('M d, Y H:i'),
        ])->toArray();
    }
}
