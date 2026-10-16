<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\War\WarehousePackage;
use App\Models\Logistics\Delivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoadController extends Controller
{
    public function index()
    {
        $packages = WarehousePackage::where('status', 'pushed_to_logistics')
            ->with(['product', 'manufacturingOrder'])
            ->get();

        return inertia('Dashboard/Logistics/Load', ['packages' => $packages]);
    }

    public function passToDispatch(Request $request)
    {
        $request->validate([
            'package_ids' => 'required|array',
            'package_ids.*' => 'exists:warehouse_packages,id',
        ]);

        DB::transaction(function () use ($request) {
            $packages = WarehousePackage::whereIn('id', $request->package_ids)->get();

            foreach ($packages as $package) {
                $package->update(['status' => 'dispatched']);

                $delivery = Delivery::create([
                    'delivery_number' => 'DLV-' . strtoupper(uniqid()),
                    'status' => 'pending',
                ]);

                $delivery->packages()->attach($package->id);
            }
        });

        return redirect()->route('logistics.dispatch.index')->with('success', 'Packages passed to dispatch.');
    }
}