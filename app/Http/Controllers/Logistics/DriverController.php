<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Logistics\Delivery;
use App\Models\Logistics\Driver;
use App\Models\Ceo\CeoLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class DriverController extends Controller
{
    /**
     * Get the authenticated driver (with fallback).
     */
    private function getDriver()
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        // Try relationship first
        $driver = $user->driver;

        if (!$driver) {
            // Fallback: query directly
            $driver = Driver::where('user_id', $user->id)->first();
            if ($driver) {
                $user->setRelation('driver', $driver);
            }
        }

        // Auto-create if user has driver role but no record
        if (!$driver && ($user->log_role === 'driver' || $user->role === 'LOG')) {
            $driver = Driver::create([
                'user_id'        => $user->id,
                'license_number' => 'DRV-' . strtoupper(uniqid()),
                'is_available'   => true,
            ]);
            $user->setRelation('driver', $driver);
            if ($user->log_role !== 'driver') {
                $user->log_role = 'driver';
                $user->save();
            }
        }

        if (!$driver) {
            abort(403, 'You are not a registered driver.');
        }

        return $driver;
    }

    public function index()
    {
        $user = Auth::user();
        $driver = $this->getDriver();

        $deliveries = Delivery::where('driver_id', $driver->id)
            ->whereIn('status', ['dispatched', 'in_transit'])
            ->with(['packages', 'route.client', 'conductor1.user', 'conductor2.user'])
            ->get();

        $montiLocation = CeoLocation::latest()->first();

        return inertia('Dashboard/Logistics/DriverPortal', [
            'deliveries'    => $deliveries,
            'driver'        => $driver->load('user'),
            'user'          => $user->only('id', 'name', 'email', 'profile_photo_path'),
            'montiLocation' => $montiLocation,
            'montiLocations' => CeoLocation::where('is_active', true)->latest()->get(),
        ]);
    }

    public function markInTransit(Delivery $delivery)
    {
        $driver = $this->getDriver();

        // Ensure the delivery belongs to this driver
        if ($delivery->driver_id !== $driver->id) {
            abort(403, 'This delivery is not assigned to you.');
        }

        $delivery->update([
            'status'           => 'in_transit',
            'actual_departure' => now(),
        ]);

        return back()->with('success', 'Trip started.');
    }

    public function uploadProof(Request $request, Delivery $delivery)
    {
        $driver = $this->getDriver();

        if ($delivery->driver_id !== $driver->id) {
            abort(403, 'This delivery is not assigned to you.');
        }

        $request->validate([
            'image' => 'required|image|max:5120',
            'notes' => 'nullable|string',
        ]);

        $path = $request->file('image')->store('proof_of_delivery', 'public');

        \App\Models\Logistics\ProofOfDelivery::create([
            'delivery_id' => $delivery->id,
            'image_path'  => $path,
            'notes'       => $request->notes,
            'delivered_at'=> now(),
        ]);

        $delivery->update(['status' => 'delivered', 'arrival_time' => now()]);

        foreach ($delivery->packages as $package) {
            $package->update(['status' => 'delivered']);
        }

        // Free resources
        if ($delivery->truck) $delivery->truck->update(['status' => 'available']);
        if ($delivery->driver) $delivery->driver->update(['is_available' => true]);
        if ($delivery->conductor1) $delivery->conductor1->update(['is_available' => true]);
        if ($delivery->conductor2) $delivery->conductor2->update(['is_available' => true]);

        return back()->with('success', 'Delivery completed and proof uploaded.');
    }

    public function testDelivered(Delivery $delivery)
    {
        $driver = $this->getDriver();

        if ($delivery->driver_id !== $driver->id) {
            abort(403, 'This delivery is not assigned to you.');
        }

        $delivery->update(['status' => 'delivered', 'arrival_time' => now()]);

        foreach ($delivery->packages as $package) {
            $package->update(['status' => 'delivered']);
        }

        if ($delivery->truck) $delivery->truck->update(['status' => 'available']);
        if ($delivery->driver) $delivery->driver->update(['is_available' => true]);
        if ($delivery->conductor1) $delivery->conductor1->update(['is_available' => true]);
        if ($delivery->conductor2) $delivery->conductor2->update(['is_available' => true]);

        return back()->with('success', 'Delivery marked as delivered (test mode).');
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email,' . $user->id,
            'password'      => ['nullable', 'confirmed', Password::defaults()],
            'profile_photo' => 'nullable|image|max:2048',
        ]);

        $user->name  = $validated['name'];
        $user->email = $validated['email'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $user->profile_photo_path = $path;
        }

        $user->save();

        return back()->with('success', 'Profile updated successfully.');
    }
}