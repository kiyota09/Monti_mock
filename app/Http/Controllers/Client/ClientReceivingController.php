<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Logistics\Delivery;
use App\Models\Logistics\ProofOfDelivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClientReceivingController extends Controller
{
    public function index()
    {
        $clientId = Auth::guard('client')->id();

        // Fetch deliveries that are assigned to this client's route
        $deliveries = Delivery::with(['packages.product', 'driver.user', 'truck', 'route'])
            ->whereHas('route', function ($q) use ($clientId) {
                $q->where('client_id', $clientId);
            })
            ->whereIn('status', ['dispatched', 'in_transit', 'delivered'])
            ->latest('scheduled_departure')
            ->get()
            ->map(function ($delivery) {
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
                    'origin'           => $delivery->route?->origin,
                    'destination'      => $delivery->route?->destination,
                    'packages'         => $delivery->packages->map(fn($pkg) => [
                        'package_number' => $pkg->package_number,
                        'product_name'   => $pkg->product?->name,
                        'quantity'       => $pkg->quantity,
                    ]),
                    'proof_notes'      => $delivery->proofOfDelivery?->notes,
                    'proof_image'      => $delivery->proofOfDelivery?->image_path,
                ];
            });

        return Inertia::render('Client/Receiving', [
            'deliveries' => $deliveries,
        ]);
    }

    public function markReceived(Request $request, Delivery $delivery)
    {
        $clientId = Auth::guard('client')->id();

        // Verify this delivery belongs to the client's route
        if (!$delivery->route || $delivery->route->client_id !== $clientId) {
            abort(403, 'This delivery is not for your company.');
        }

        // Only allow marking as received if it's already delivered (by driver) or in_transit
        if (!in_array($delivery->status, ['in_transit', 'delivered'])) {
            return back()->with('error', 'This delivery cannot be confirmed yet.');
        }

        // If not already delivered, we can mark it as delivered (client confirmation)
        if ($delivery->status !== 'delivered') {
            $delivery->update([
                'status'       => 'delivered',
                'arrival_time' => now(),
            ]);

            // Update package statuses
            foreach ($delivery->packages as $package) {
                $package->update(['status' => 'delivered']);
            }

            // Free resources if not already done
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

        // Add client confirmation note (optional)
        $delivery->notes = trim($delivery->notes . "\nClient confirmed receipt on " . now()->toDateTimeString());
        $delivery->save();

        return back()->with('success', 'Delivery confirmed. Thank you!');
    }
}