<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Ceo\CeoLocation;
use App\Models\Core\AppSetting;
use App\Models\Logistics\Delivery;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrackingController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');

        $query = Delivery::with([
            'truck',
            'driver.user',
            'conductor1.user',
            'conductor2.user',
            'route.client',
            'stops.client',
            'packages.product',
        ]);

        if ($filter === 'scheduled') {
            $query->where('status', 'dispatched');
        } elseif ($filter === 'active') {
            $query->where('status', 'in_transit');
        } elseif ($filter === 'delivered') {
            $query->where('status', 'delivered');
        }

        $deliveries = $query->latest('scheduled_departure')->get();

        return Inertia::render('Dashboard/Logistics/Tracking', [
            'deliveries'    => $deliveries,
            'filter'        => $filter,
            'montiLocation' => CeoLocation::latest()->first(),
            'montiLocations' => CeoLocation::where('is_active', true)->latest()->get(),
            'deliverySimulation' => AppSetting::getBool(AppSetting::LOGISTICS_DELIVERY_SIMULATION),
        ]);
    }

    /**
     * Toggle the driver-portal delivery simulation used for testing the
     * live trip map (moves the truck along the route instead of relying on
     * the driver's device GPS).
     */
    public function updateDeliverySimulation(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        AppSetting::setBool(AppSetting::LOGISTICS_DELIVERY_SIMULATION, (bool) $validated['enabled']);

        return back()->with(
            'success',
            $validated['enabled']
                ? 'Delivery simulation enabled. Drivers can now simulate trips.'
                : 'Delivery simulation disabled. Real-time GPS tracking is active.'
        );
    }
}
