<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Logistics\Truck;
use App\Models\Logistics\Driver;
use App\Models\War\WarehousePackage;
use App\Models\Logistics\Delivery;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class LogisticsDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        // NOTE (overseer model): executive oversight runs through the CEO
        // module (reports, audit, approvals) — no direct logistics access.
        $hasAccess = \DB::table('logistics_access')->where('user_id', $user->id)->value('can_access_logistics');

        if (!$hasAccess && $user->role !== 'LOG') {
            abort(403);
        }

        $stats = [
            'pendingPackages' => WarehousePackage::where('status', 'pushed_to_logistics')->count(),
            'dispatchedDeliveries' => Delivery::where('status', 'dispatched')->count(),
            'inTransitDeliveries' => Delivery::where('status', 'in_transit')->count(),
            'availableTrucks' => Truck::where('status', 'available')->count(),
            'availableDrivers' => Driver::where('is_available', true)->count(),
        ];

        $recentDeliveries = Delivery::with(['driver.user', 'truck'])->latest()->take(5)->get();

        return Inertia::render('Dashboard/Logistics/LogDashboard', [
            'stats' => $stats,
            'recentDeliveries' => $recentDeliveries,
        ]);
    }
}