<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Logistics\Delivery;
use App\Models\Logistics\DeliveryStop;
use App\Models\Ord\OrderReturn;
use App\Models\Ord\SalesOrder;
use App\Models\War\WarehousePackage;
use App\Services\Ord\OrderLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ClientReceivingController extends Controller
{
    public function index()
    {
        $clientId = Auth::guard('client')->id();

        // A delivery belongs to this client when one of its stops (or its
        // single-client route) points at the client — multi-client loads included.
        $deliveries = Delivery::with(['packages.product', 'packages.manufacturingOrder.salesOrder', 'driver.user', 'truck', 'route', 'stops'])
            ->where(function ($q) use ($clientId) {
                $q->whereHas('stops', fn ($s) => $s->where('client_id', $clientId))
                  ->orWhereHas('route', fn ($r) => $r->where('client_id', $clientId));
            })
            ->whereIn('status', ['dispatched', 'in_transit', 'delivered'])
            ->latest('scheduled_departure')
            ->get()
            ->map(function ($delivery) use ($clientId) {
                $myStop = $delivery->stops->firstWhere('client_id', $clientId);

                return [
                    'id'               => $delivery->id,
                    'delivery_number'  => $delivery->delivery_number,
                    'status'           => $delivery->status,
                    'scheduled'        => $delivery->scheduled_departure,
                    'actual_departure' => $delivery->actual_departure,
                    'arrival_time'     => $delivery->arrival_time,
                    'driver_name'      => $delivery->driver?->user?->name,
                    'truck_number'     => $delivery->truck?->truck_number,
                    'route_name'       => $delivery->route?->name,
                    'origin'           => $delivery->origin_label ?? $delivery->route?->origin,
                    'destination'      => $delivery->route?->destination ?? $myStop?->address,
                    'stop_status'      => $myStop?->status,
                    'stop_outcome'     => $myStop?->outcome,
                    'received_by'      => in_array($myStop?->outcome, ['delivered', 'partial'], true)
                        ? $myStop?->recipient_name
                        : null,
                    'stop_completed_at' => $myStop?->completed_at,
                    'packages'         => $delivery->packages
                        ->filter(fn ($pkg) => (int) ($pkg->destinationClientId() ?? 0) === (int) $clientId)
                        ->map(fn ($pkg) => [
                        'id'             => $pkg->id,
                        'package_number' => $pkg->package_number,
                        'product_name'   => $pkg->product?->name,
                        'quantity'       => $pkg->quantity,
                        'weight_kg'      => $pkg->weight_kg,
                        'sales_order_id' => $pkg->manufacturingOrder?->sales_order_id,
                        'status'         => $pkg->status,
                    ]),
                    'proof_notes'      => $delivery->proofOfDelivery?->notes,
                    'proof_image'      => $delivery->proofOfDelivery?->image_path,
                    'rating'           => $delivery->rating,
                    'rating_comment'   => $delivery->rating_comment,
                    'rated_at'         => $delivery->rated_at,
                ];
            });

        return Inertia::render('Client/Receiving', [
            'deliveries' => $deliveries,
        ]);
    }

    public function markReceived(Request $request, Delivery $delivery)
    {
        $clientId = Auth::guard('client')->id();

        if (! $this->belongsToClient($delivery, $clientId)) {
            abort(403, 'This delivery is not for your company.');
        }

        if (! in_array($delivery->status, ['in_transit', 'delivered'], true)) {
            return back()->with('error', 'This delivery cannot be confirmed yet.');
        }

        DB::transaction(function () use ($delivery, $clientId) {
            // Mark this client's stop(s) received.
            $stops = $delivery->stops()->where('client_id', $clientId)->get();
            $stopIds = $stops->pluck('id');

            if ($stopIds->isNotEmpty()) {
                DeliveryStop::whereIn('id', $stopIds)->update([
                    'status'     => 'delivered',
                    'arrived_at' => now(),
                ]);

                // Mark only this client's packages delivered.
                $packageIds = DB::table('delivery_packages')
                    ->where('delivery_id', $delivery->id)
                    ->whereIn('delivery_stop_id', $stopIds)
                    ->pluck('warehouse_package_id');

                \App\Models\War\WarehousePackage::whereIn('id', $packageIds)
                    ->update(['status' => 'delivered']);
            }

            // Delivery is complete only when every stop is delivered.
            $allDone = ! $delivery->stops()->where('status', '!=', 'delivered')->exists();

            if ($allDone) {
                $delivery->update([
                    'status'       => 'delivered',
                    'arrival_time' => now(),
                ]);

                if ($delivery->truck) {
                    $delivery->truck->update(['status' => 'available']);
                }
                if ($delivery->driver) {
                    $delivery->driver->update(['is_available' => true]);
                }
                if ($delivery->conductor1) {
                    $delivery->conductor1->update(['is_available' => true]);
                }
                if ($delivery->conductor2) {
                    $delivery->conductor2->update(['is_available' => true]);
                }
            }

            $delivery->notes = trim($delivery->notes . "\nClient confirmed receipt on " . now()->toDateTimeString());
            $delivery->save();
        });

        return back()->with('success', 'Delivery confirmed. Thank you!');
    }

    public function requestReturn(Request $request, Delivery $delivery, OrderLifecycleService $lifecycle)
    {
        $clientId = (int) Auth::guard('client')->id();
        abort_unless($this->belongsToClient($delivery, $clientId), 403);

        $data = $request->validate([
            'package_ids' => 'required|array|min:1',
            'package_ids.*' => 'required|integer|distinct|exists:warehouse_packages,id',
            'request_type' => 'required|in:replacement,price_adjustment,cancelled',
            'reason' => 'required|string|max:2000',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $packages = WarehousePackage::with('manufacturingOrder.salesOrder')
            ->whereIn('id', $data['package_ids'])->get();
        if ($packages->count() !== count(array_unique($data['package_ids']))) {
            throw ValidationException::withMessages(['package_ids' => 'One or more selected packages are unavailable.']);
        }

        $deliveryPackageIds = $delivery->packages()->pluck('warehouse_packages.id')->map(fn ($id) => (int) $id)->all();
        foreach ($packages as $package) {
            if (! in_array((int) $package->id, $deliveryPackageIds, true)) {
                throw ValidationException::withMessages(['package_ids' => 'A selected package is not part of this delivery.']);
            }
            abort_unless((int) ($package->destinationClientId() ?? 0) === $clientId, 403, 'A selected package belongs to another business client.');
            if ($package->status !== 'delivered') {
                throw ValidationException::withMessages(['package_ids' => 'Only delivered packages can be returned.']);
            }
        }

        $packagesBySalesOrder = $packages->groupBy(fn ($package) => $package->manufacturingOrder?->sales_order_id);
        if ($packagesBySalesOrder->has('')) {
            throw ValidationException::withMessages(['package_ids' => 'A selected package is not linked to a job order.']);
        }
        $imagePath = $request->file('image')?->store('client_returns', 'public');

        $returnCount = DB::transaction(function () use ($packagesBySalesOrder, $clientId, $data, $imagePath, $lifecycle) {
            foreach ($packagesBySalesOrder as $salesOrderId => $orderPackages) {
                $salesOrder = SalesOrder::whereKey($salesOrderId)->where('client_id', $clientId)->firstOrFail();
                OrderReturn::create([
                    'return_number' => $lifecycle->nextReturnNumber(),
                    'sales_order_id' => $salesOrder->id,
                    'client_id' => $clientId,
                    'type' => 'client_return',
                    'request_type' => $data['request_type'],
                    'package_ids' => $orderPackages->modelKeys(),
                    'quantity' => round((float) $orderPackages->sum('weight_kg'), 2),
                    'reason' => $data['reason'],
                    'image_path' => $imagePath,
                    'status' => 'pending',
                ]);
            }
            return $packagesBySalesOrder->count();
        });

        return back()->with('success', "Return request submitted for ECO review across {$returnCount} job order(s).");
    }

    /**
     * Rate a delivered shipment (overall stars + optional comment).
     */
    public function rate(Request $request, Delivery $delivery)
    {
        $clientId = Auth::guard('client')->id();

        if (! $this->belongsToClient($delivery, $clientId)) {
            abort(403, 'This delivery is not for your company.');
        }

        if ($delivery->status !== 'delivered') {
            return back()->with('error', 'You can rate the delivery after your package has been received.');
        }

        $validated = $request->validate([
            'rating'          => 'required|integer|min:1|max:5',
            'rating_comment'  => 'nullable|string|max:1000',
        ]);

        $delivery->update([
            'rating'             => $validated['rating'],
            'rating_comment'     => $validated['rating_comment'] ?? null,
            'rated_at'           => now(),
            'rated_by_client_id' => $clientId,
        ]);

        // Roll up a running average onto the driver's profile.
        if ($delivery->driver_id) {
            $avg = Delivery::where('driver_id', $delivery->driver_id)
                ->whereNotNull('rating')
                ->avg('rating');

            $delivery->driver?->update(['rating' => round((float) $avg, 1)]);
        }

        return back()->with('success', 'Thank you for rating this delivery.');
    }

    private function belongsToClient(Delivery $delivery, ?int $clientId): bool
    {
        if (! $clientId) {
            return false;
        }

        if ($delivery->stops()->where('client_id', $clientId)->exists()) {
            return true;
        }

        return $delivery->route && (int) $delivery->route->client_id === (int) $clientId;
    }
}
