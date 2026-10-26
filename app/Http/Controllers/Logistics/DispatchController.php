<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Ceo\CeoLocation;
use App\Models\Crm\ClientNotification;
use App\Models\Logistics\Conductor;
use App\Models\Logistics\Delivery;
use App\Models\Logistics\DeliveryStop;
use App\Models\Logistics\Driver;
use App\Models\Logistics\Route;
use App\Models\Logistics\Truck;
use App\Models\War\WarehousePackage;
use App\Services\Logistics\RouteOptimizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DispatchController extends Controller
{
    public function __construct(private RouteOptimizerService $optimizer)
    {
    }

    public function index()
    {
        // Pool of staged packages not yet placed on any load.
        $pool = WarehousePackage::where('status', 'dispatched')
            ->whereDoesntHave('deliveries')
            ->with(['product', 'manufacturingOrder.salesOrder', 'manufacturingOrder.purchaseOrder'])
            ->get()
            ->map(function ($pkg) {
                $client = $pkg->destinationClient();

                return [
                    'id'             => $pkg->id,
                    'package_number' => $pkg->package_number,
                    'product_name'   => $pkg->product?->name,
                    'quantity'       => (float) $pkg->quantity,
                    'client_id'      => $client?->id,
                    'client_name'    => $client?->company_name,
                    'client_address' => $this->clientAddress($client),
                    'latitude'       => $client?->latitude,
                    'longitude'      => $client?->longitude,
                ];
            })
            ->values();

        $trucks = Truck::where('status', 'available')->get()->map(fn ($t) => [
            ...$t->toArray(),
            'loaded_count'   => $t->loadedPackagesCount(),
            'remaining_slots' => max(0, (int) $t->max_packages - $t->loadedPackagesCount()),
        ]);

        $deliveries = Delivery::with(['packages.product', 'truck', 'driver.user', 'conductor1.user', 'stops.client'])
            ->whereIn('status', ['dispatched', 'in_transit'])
            ->latest()
            ->get();

        $monti = $this->montiLocation();

        return inertia('Dashboard/Logistics/Dispatch', [
            'packages'      => $pool,
            'trucks'        => $trucks,
            'drivers'       => Driver::where('is_available', true)->with('user')->get(),
            'conductors'    => Conductor::where('is_available', true)->with('user')->get(),
            'routes'        => Route::all(),
            'deliveries'    => $deliveries,
            'montiLocation' => $monti ? [
                'lat'   => $monti->latitude,
                'lng'   => $monti->longitude,
                'label' => $monti->label ?: ($monti->place_name ?: 'Monti Textiles'),
            ] : null,
        ]);
    }

    /**
     * Resolve real road geometry for the live dispatch route preview so the
     * map traces highways instead of drawing straight point-to-point lines.
     */
    public function routePreview(Request $request)
    {
        $validated = $request->validate([
            'points'        => 'required|array|min:2|max:26',
            'points.*.lat'  => 'required|numeric|between:-90,90',
            'points.*.lng'  => 'required|numeric|between:-180,180',
        ]);

        $monti = $this->montiLocation();
        $origin = [
            'lat' => (float) ($monti?->latitude ?? $validated['points'][0]['lat']),
            'lng' => (float) ($monti?->longitude ?? $validated['points'][0]['lng']),
        ];

        $stops = collect($validated['points'])->map(fn ($p) => [
            'latitude'  => (float) $p['lat'],
            'longitude' => (float) $p['lng'],
        ])->values()->all();

        // Drop the first point when it is the origin so it is not duplicated.
        $first = $stops[0] ?? null;
        if ($first
            && abs($first['latitude'] - $origin['lat']) < 0.00001
            && abs($first['longitude'] - $origin['lng']) < 0.00001) {
            array_shift($stops);
        }

        $geometry = $this->optimizer->resolveGeometry($origin, $stops);

        if (! $geometry) {
            return response()->json(['geometry' => null]);
        }

        return response()->json([
            'geometry'          => $geometry['geometry'],
            'distance_km'       => $geometry['distance_km'],
            'estimated_minutes' => $geometry['estimated_minutes'],
        ]);
    }

    /**
     * Build a truck load: many packages (possibly for several clients) on
     * one truck, limited by the truck's package capacity, with a driver and
     * optional conductor(s) assigned. The multi-stop order is optimized
     * nearest-first from the Monti origin.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'truck_id'            => 'required|exists:trucks,id',
            'driver_id'           => 'required|exists:drivers,id',
            'conductor1_id'       => 'nullable|exists:conductors,id',
            'conductor2_id'       => 'nullable|exists:conductors,id',
            'scheduled_departure' => 'required|date',
            'package_ids'         => 'required|array|min:1',
            'package_ids.*'       => 'exists:warehouse_packages,id',
            'notes'               => 'nullable|string',
        ]);

        $truck = Truck::findOrFail($validated['truck_id']);

        $packages = WarehousePackage::whereIn('id', $validated['package_ids'])
            ->where('status', 'dispatched')
            ->whereDoesntHave('deliveries')
            ->with(['product', 'manufacturingOrder.salesOrder', 'manufacturingOrder.purchaseOrder'])
            ->get();

        if ($packages->count() !== count($validated['package_ids'])) {
            return back()->withErrors(['package_ids' => 'Some selected packages are no longer available for loading.']);
        }

        if ($truck->max_packages && $packages->count() > $truck->max_packages) {
            return back()->withErrors([
                'package_ids' => "Truck {$truck->truck_number} carries at most {$truck->max_packages} package(s).",
            ]);
        }

        $delivery = DB::transaction(function () use ($validated, $truck, $packages) {
            $delivery = Delivery::create([
                'delivery_number'     => 'DLV-' . strtoupper(Str::random(8)),
                'truck_id'            => $truck->id,
                'driver_id'           => $validated['driver_id'],
                'conductor1_id'       => $validated['conductor1_id'] ?? null,
                'conductor2_id'       => $validated['conductor2_id'] ?? null,
                'status'              => 'dispatched',
                'scheduled_departure' => $validated['scheduled_departure'],
                'notes'               => $validated['notes'] ?? null,
            ]);

            $this->buildStops($delivery, $packages);

            $truck->update(['status' => 'in_use']);
            Driver::where('id', $validated['driver_id'])->update(['is_available' => false]);
            if (! empty($validated['conductor1_id'])) {
                Conductor::where('id', $validated['conductor1_id'])->update(['is_available' => false]);
            }
            if (! empty($validated['conductor2_id'])) {
                Conductor::where('id', $validated['conductor2_id'])->update(['is_available' => false]);
            }

            return $delivery;
        });

        return redirect()->route('logistics.dispatch.index')
            ->with('success', "Load {$delivery->delivery_number} created with {$packages->count()} package(s).");
    }

    /**
     * Mark a load as out for delivery today and notify every destination client.
     */
    public function deliver(Delivery $delivery)
    {
        if ($delivery->status === 'in_transit') {
            return back()->with('error', 'This load is already out for delivery.');
        }

        if ($delivery->status !== 'dispatched') {
            return back()->withErrors(['delivery' => 'Only dispatched loads can be sent out for delivery.']);
        }

        DB::transaction(function () use ($delivery) {
            $delivery->update([
                'status'           => 'in_transit',
                'actual_departure' => now(),
            ]);

            $clientIds = $delivery->stops()->pluck('client_id')->filter()->unique();

            foreach ($clientIds as $clientId) {
                ClientNotification::notify(
                    (int) $clientId,
                    'Delivery out for delivery today',
                    "Delivery {$delivery->delivery_number} will be attempted for delivery today. Please make sure someone is available to receive your order.",
                    'client.receiving',
                    $delivery->id,
                    'logistics'
                );
            }
        });

        return back()->with('success', 'Load is out for delivery. Destination clients have been notified.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function buildStops(Delivery $delivery, $packages): void
    {
        $monti = $this->montiLocation();
        $origin = [
            'lat' => $monti?->latitude ?? 0,
            'lng' => $monti?->longitude ?? 0,
        ];

        // Group packages by destination client (a stop).
        $groups = [];
        foreach ($packages as $pkg) {
            $client = $pkg->destinationClient();
            $key = $client?->id ?? 0;
            $groups[$key] ??= ['client' => $client, 'packages' => []];
            $groups[$key]['packages'][] = $pkg;
        }

        $stopDefs = [];
        foreach ($groups as $group) {
            $client = $group['client'];
            $stopDefs[] = [
                'client_id' => $client?->id,
                'label'     => $client?->company_name ?? 'Unassigned destination',
                'address'   => $this->clientAddress($client),
                'latitude'  => $client?->latitude,
                'longitude' => $client?->longitude,
                'packages'  => $group['packages'],
            ];
        }

        $ordered = array_values($this->optimizer->orderByNearest($origin, $stopDefs));

        foreach ($ordered as $i => $stop) {
            $model = DeliveryStop::create([
                'delivery_id' => $delivery->id,
                'client_id'   => $stop['client_id'],
                'sequence'    => $i + 1,
                'label'       => $stop['label'],
                'address'     => $stop['address'],
                'latitude'    => $stop['latitude'],
                'longitude'   => $stop['longitude'],
                'status'      => 'pending',
            ]);

            $ordered[$i]['stop_id'] = $model->id;
        }

        foreach ($ordered as $stop) {
            foreach ($stop['packages'] as $pkg) {
                $delivery->packages()->attach($pkg->id, ['delivery_stop_id' => $stop['stop_id']]);
            }
        }

        $geometry = $this->optimizer->resolveGeometry($origin, $ordered);
        $straight = $this->optimizer->straightLineDistance($origin, $ordered);

        $delivery->update([
            'origin_lat'        => $origin['lat'] ?: null,
            'origin_lng'        => $origin['lng'] ?: null,
            'origin_label'      => $monti?->label ?: ($monti?->place_name ?: null),
            'route_geometry'    => $geometry['geometry'] ?? null,
            'total_distance_km' => $geometry['distance_km'] ?? $straight,
            'estimated_minutes' => $geometry['estimated_minutes'] ?? null,
        ]);

        // Link a route when the whole load serves a single client.
        $clientIds = collect($stopDefs)->pluck('client_id')->filter()->unique();
        if ($clientIds->count() === 1) {
            $route = Route::where('client_id', $clientIds->first())->latest()->first();
            if ($route) {
                $delivery->update(['route_id' => $route->id]);
            }
        }
    }

    private function montiLocation(): ?CeoLocation
    {
        return CeoLocation::where('is_active', true)->latest()->first()
            ?? CeoLocation::latest()->first();
    }

    private function clientAddress($client): ?string
    {
        if (! $client) {
            return null;
        }

        $address = trim(collect([$client->company_address, $client->city, $client->province])
            ->filter()
            ->implode(', '));

        return $address !== '' ? $address : null;
    }
}
