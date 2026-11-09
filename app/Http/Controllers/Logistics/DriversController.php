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
        $drivers = Driver::with('user')
            ->withCount('deliveries')
            ->withCount(['deliveries as rating_count' => fn ($query) => $query->whereNotNull('rating')])
            ->withAvg('deliveries', 'rating')
            ->get()
            ->map(function (Driver $driver) {
            $photoPath = $driver->user?->profile_photo_path;
            // The stored driver rating is only a cache. Customer delivery
            // ratings are the source of truth; unrated drivers start at zero.
            $driver->setAttribute('rating', round((float) ($driver->deliveries_avg_rating ?? 0), 1));
            $driver->setAttribute('photo_url', $photoPath
                ? (str_starts_with($photoPath, 'http://') || str_starts_with($photoPath, 'https://')
                    ? $photoPath
                    : Storage::disk('public')->url(ltrim(str_replace('/storage/', '', $photoPath), '/')))
                : null);

            return $driver;
        });

        $ratingReports = Delivery::with(['driver.user', 'ratedByClient', 'route'])
            ->whereNotNull('rating')
            ->latest('rated_at')
            ->get()
            ->map(fn (Delivery $delivery) => [
                'id' => $delivery->id,
                'delivery_number' => $delivery->delivery_number,
                'driver_id' => $delivery->driver_id,
                'driver_name' => $delivery->driver?->user?->name ?? 'Unknown driver',
                'client_name' => $delivery->ratedByClient?->company_name ?? 'Client',
                'route_name' => $delivery->route?->name,
                'rating' => (int) $delivery->rating,
                'comment' => $delivery->rating_comment,
                'rated_at' => $delivery->rated_at?->format('Y-m-d H:i'),
            ]);

        return inertia('Dashboard/Logistics/Drivers', [
            'drivers' => $drivers,
            'ratingReports' => $ratingReports,
        ]);
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
            'log_role' => 'driver',
        ]);

        $driver = new Driver([
            'license_number' => $request->license_number,
            'rating' => 0,
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
        $deliveries = $driver->deliveries()
            ->with(['packages', 'route'])
            ->latest('created_at')
            ->get();
        $driver->load('user')->setRelation('deliveries', $deliveries);
        $driver->setAttribute('rating_count', $driver->deliveries()->whereNotNull('rating')->count());
        $driver->setAttribute('rating', round((float) ($driver->deliveries()->whereNotNull('rating')->avg('rating') ?? 0), 1));

        return response()->json([
            'driver' => $driver,
            'deliveries' => $deliveries,
        ]);
    }
}
