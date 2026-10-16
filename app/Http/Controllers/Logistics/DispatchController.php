<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Logistics\Delivery;
use App\Models\Logistics\Truck;
use App\Models\Logistics\Driver;
use App\Models\Logistics\Conductor;
use App\Models\Logistics\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DispatchController extends Controller
{
    public function index()
    {
        $deliveries = Delivery::with(['packages', 'truck', 'driver.user', 'conductor1.user', 'conductor2.user', 'route'])
            ->where('status', 'pending')
            ->get();

        $trucks = Truck::where('status', 'available')->get();
        $drivers = Driver::where('is_available', true)->with('user')->get();
        $conductors = Conductor::where('is_available', true)->with('user')->get();
        $routes = Route::all();

        return inertia('Dashboard/Logistics/Dispatch', [
            'deliveries' => $deliveries,
            'trucks' => $trucks,
            'drivers' => $drivers,
            'conductors' => $conductors,
            'routes' => $routes,
        ]);
    }

    public function assignAndDispatch(Request $request, Delivery $delivery)
    {
        $request->validate([
            'truck_id' => 'required|exists:trucks,id',
            'driver_id' => 'required|exists:drivers,id',
            'conductor1_id' => 'nullable|exists:conductors,id',
            'conductor2_id' => 'nullable|exists:conductors,id',
            'route_id' => 'required|exists:routes,id',
            'scheduled_departure' => 'required|date',
        ]);

        DB::transaction(function () use ($request, $delivery) {
            $delivery->update([
                'truck_id' => $request->truck_id,
                'driver_id' => $request->driver_id,
                'conductor1_id' => $request->conductor1_id,
                'conductor2_id' => $request->conductor2_id,
                'route_id' => $request->route_id,
                'scheduled_departure' => $request->scheduled_departure,
                'status' => 'dispatched',
            ]);

            // Update truck status
            Truck::where('id', $request->truck_id)->update(['status' => 'in_use']);
            // Update driver availability
            Driver::where('id', $request->driver_id)->update(['is_available' => false]);
            if ($request->conductor1_id) Conductor::where('id', $request->conductor1_id)->update(['is_available' => false]);
            if ($request->conductor2_id) Conductor::where('id', $request->conductor2_id)->update(['is_available' => false]);
        });

        return redirect()->route('logistics.dispatch.index')->with('success', 'Delivery dispatched.');
    }
}