<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Logistics\Delivery;
use App\Models\Logistics\DeliveryStop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ClientReceivingController extends Controller
{
    public function index()
    {
        $clientId = Auth::guard('client')->id();

        // A delivery belongs to this client when one of its stops (or its
        // single-client route) points at the client — multi-client loads included.
        $deliveries = Delivery::with(['packages.product', 'driver.user', 'truck', 'route', 'stops'])
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
                    'packages'         => $delivery->packages->map(fn ($pkg) => [
                        'package_number' => $pkg->package_number,
                        'product_name'   => $pkg->product?->name,
                        'quantity'       => $pkg->quantity,
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

    /**
     * Rate a delivered shipment (overall stars + optional comment).
     */
    public function rate(Request $request, Delivery $delivery)
    {
        $clientId = Auth::guard('client')->id();

        if (! $this->belongsToClient($delivery, $clientId)) {
            abort(403, 'This delivery is not for your company.');
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
