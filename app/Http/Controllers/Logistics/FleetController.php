<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Logistics\Truck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class FleetController extends Controller
{
    public function index()
    {
        $trucks = Truck::all()->map(function (Truck $truck) {
            $truck->setAttribute('image_url', $truck->image_path ? Storage::disk('public')->url($truck->image_path) : null);
            return $truck;
        });
        return inertia('Dashboard/Logistics/Fleet', [
            'trucks' => $trucks,
            'nextTruckNumber' => $this->nextTruckNumber(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'plate_number' => 'required|unique:trucks',
            'model' => 'required',
            'year' => 'required|integer|min:1990|max:' . date('Y'),
            'mileage' => 'nullable|numeric|min:0',
            'max_load_kg' => 'required|numeric|min:0.01|max:99999999.99',
            'status' => 'required|in:available,in_use,under_maintenance,retired',
            'remarks' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $prefix = 'TRK-'.now()->format('Y').'-';
            $lastNumber = Truck::where('truck_number', 'like', $prefix.'%')
                ->orderByDesc('truck_number')->lockForUpdate()->value('truck_number');
            $sequence = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;
            $validated['truck_number'] = $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
            if ($request->hasFile('image')) {
                $validated['image_path'] = $request->file('image')->store('fleet/trucks', 'public');
            }
            unset($validated['image']);
            Truck::create($validated);
        });
        return back()->with('success', 'Truck added.');
    }

    public function update(Request $request, Truck $truck)
    {
        $validated = $request->validate([
            'plate_number' => 'required|string|max:255|unique:trucks,plate_number,'.$truck->id,
            'model' => 'required|string|max:255',
            'year' => 'required|integer|min:1990|max:'.date('Y'),
            'status' => 'required|in:available,in_use,under_maintenance,retired',
            'mileage' => 'nullable|numeric|min:0',
            'max_load_kg' => 'required|numeric|min:0.01|max:99999999.99',
            'remarks' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        if ((float)$validated['max_load_kg'] + 0.001 < $truck->loadedWeightKg()) {
            return back()->withErrors(['max_load_kg' => 'The capacity cannot be lower than the weight currently on this truck.']);
        }
        if ($request->hasFile('image')) {
            if ($truck->image_path) Storage::disk('public')->delete($truck->image_path);
            $validated['image_path'] = $request->file('image')->store('fleet/trucks', 'public');
        }
        unset($validated['image']);
        $truck->update($validated);
        return back()->with('success', 'Truck updated.');
    }

    public function destroy(Truck $truck)
    {
        if ($truck->image_path) Storage::disk('public')->delete($truck->image_path);
        $truck->delete();
        return back()->with('success', 'Truck removed.');
    }

    private function nextTruckNumber(): string
    {
        $prefix = 'TRK-'.now()->format('Y').'-';
        $lastNumber = Truck::where('truck_number', 'like', $prefix.'%')->orderByDesc('truck_number')->value('truck_number');
        $sequence = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
