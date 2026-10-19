<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\War\WarehousePackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoadController extends Controller
{
    public function index()
    {
        // Packages waiting to be staged into the dispatch pool.
        $packages = WarehousePackage::where('status', 'pushed_to_logistics')
            ->with(['product', 'manufacturingOrder.salesOrder', 'manufacturingOrder.purchaseOrder'])
            ->get()
            ->map(fn ($pkg) => [
                ...$pkg->toArray(),
                'client_name' => $pkg->destinationClient()?->company_name,
            ]);

        // Packages already staged (awaiting assignment to a truck load).
        $staged = WarehousePackage::where('status', 'dispatched')
            ->whereDoesntHave('deliveries')
            ->with(['product', 'manufacturingOrder.salesOrder', 'manufacturingOrder.purchaseOrder'])
            ->get()
            ->map(fn ($pkg) => [
                ...$pkg->toArray(),
                'client_name' => $pkg->destinationClient()?->company_name,
            ]);

        return inertia('Dashboard/Logistics/Load', [
            'packages' => $packages,
            'staged'   => $staged,
        ]);
    }

    /**
     * Stage the selected packages into the dispatch pool. Loads are NOT
     * created here — the Logistics Manager builds each truck load (grouping
     * many packages, possibly for several clients) from the Dispatch Center.
     */
    public function passToDispatch(Request $request)
    {
        $request->validate([
            'package_ids'   => 'required|array',
            'package_ids.*' => 'exists:warehouse_packages,id',
        ]);

        DB::transaction(function () use ($request) {
            WarehousePackage::whereIn('id', $request->package_ids)
                ->update(['status' => 'dispatched']);
        });

        return redirect()->route('logistics.dispatch.index')
            ->with('success', 'Packages staged for dispatch.');
    }
}
