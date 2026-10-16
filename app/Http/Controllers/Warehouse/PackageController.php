<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\War\WarehousePackage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PackageController extends Controller
{
    public function index()
    {
        $packages = WarehousePackage::with('product')->orderByDesc('created_at')->get();
        return Inertia::render('Dashboard/Warehouse/Package', ['packages' => $packages]);
    }

    public function pushToLogistics(WarehousePackage $package)
    {
        $package->update([
            'status' => 'pushed_to_logistics',
            'pushed_at' => now(),
            'pushed_by' => auth()->id(),
        ]);
        // Later: trigger logistics module
        return redirect()->back()->with('success', 'Package pushed to logistics.');
    }
}