<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Logistics\Truck;
use Illuminate\Http\Request;

class FleetController extends Controller
{
    public function index()
    {
        $trucks = Truck::all();
        return inertia('Dashboard/Logistics/Fleet', ['trucks' => $trucks]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'truck_number' => 'required|unique:trucks',
            'plate_number' => 'required|unique:trucks',
            'model' => 'required',
            'year' => 'required|integer|min:1990|max:' . date('Y'),
            'mileage' => 'nullable|numeric',
            'status' => 'required|in:available,in_use,under_maintenance,retired',
            'remarks' => 'nullable|string',
        ]);
        Truck::create($validated);
        return back()->with('success', 'Truck added.');
    }

    public function update(Request $request, Truck $truck)
    {
        $validated = $request->validate([
            'status' => 'required|in:available,in_use,under_maintenance,retired',
            'mileage' => 'nullable|numeric',
            'remarks' => 'nullable|string',
        ]);
        $truck->update($validated);
        return back()->with('success', 'Truck updated.');
    }

    public function destroy(Truck $truck)
    {
        $truck->delete();
        return back()->with('success', 'Truck removed.');
    }
}