<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Ceo\CeoLocation;
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
            'packages.product',
        ]);

        if ($filter === 'scheduled') {
            $query->where('status', 'pending');
        } elseif ($filter === 'active') {
            $query->whereIn('status', ['dispatched', 'in_transit']);
        } elseif ($filter === 'delivered') {
            $query->where('status', 'delivered');
        }

        $deliveries = $query->latest('scheduled_departure')->get();

        return Inertia::render('Dashboard/Logistics/Tracking', [
            'deliveries'    => $deliveries,
            'filter'        => $filter,
            'montiLocation' => CeoLocation::latest()->first(),
            'montiLocations' => CeoLocation::where('is_active', true)->latest()->get(),
        ]);
    }
}