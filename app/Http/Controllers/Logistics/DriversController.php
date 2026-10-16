<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Logistics\Driver;
use App\Models\Logistics\Delivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DriversController extends Controller
{
    public function index()
    {
        $drivers = Driver::with('user')->get();
        return inertia('Dashboard/Logistics/Drivers', ['drivers' => $drivers]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'phone' => 'required',
            'license_number' => 'required|unique:drivers',
            'license_image' => 'nullable|image|mimes:jpg,jpeg,png,pdf|max:2048',
            'medical_certificate' => 'nullable|image|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $tempPassword = bin2hex(random_bytes(8));
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($tempPassword),
            'role' => 'LOG',
            'position' => 'staff',
        ]);

        $driver = new Driver([
            'license_number' => $request->license_number,
            'rating' => 5.0,
            'is_available' => true,
        ]);

        if ($request->hasFile('license_image')) {
            $driver->license_image = $request->file('license_image')->store('drivers/licenses', 'public');
        }
        if ($request->hasFile('medical_certificate')) {
            $driver->medical_certificate = $request->file('medical_certificate')->store('drivers/medical', 'public');
        }

        $user->driver()->save($driver);

        return back()->with('success', 'Driver added.');
    }

    public function show(Driver $driver)
    {
        $deliveries = Delivery::where('driver_id', $driver->id)->with(['packages', 'route'])->get();
        return inertia('Dashboard/Logistics/DriverDetail', [
            'driver' => $driver->load('user'),
            'deliveries' => $deliveries,
        ]);
    }
}