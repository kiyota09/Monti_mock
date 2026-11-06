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
                'weight_kg' => $pkg->weight_kg === null ? null : (float) $pkg->weight_kg,
                'client_name' => $pkg->destinationClient()?->company_name,
            ]);

        // Packages already staged (awaiting assignment to a truck load).
        $staged = WarehousePackage::where('status', 'dispatched')
            ->whereDoesntHave('deliveries')
            ->with(['product', 'manufacturingOrder.salesOrder', 'manufacturingOrder.purchaseOrder'])
            ->get()
            ->map(fn ($pkg) => [
                ...$pkg->toArray(),
                'weight_kg' => $pkg->weight_kg === null ? null : (float) $pkg->weight_kg,
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
            'package_ids.*' => 'required|distinct|exists:warehouse_packages,id',
        ]);

        DB::transaction(function () use ($request) {
            $packages=WarehousePackage::whereIn('id',$request->package_ids)->where('status','pushed_to_logistics')->lockForUpdate()->get();
            if($packages->count()!==count($request->package_ids)){
                throw \Illuminate\Validation\ValidationException::withMessages(['package_ids'=>'Only packages passed from Finishing can be staged for Dispatch.']);
            }
            if($packages->contains(fn($package)=>$package->weight_kg===null||(float)$package->weight_kg<=0)){
                throw \Illuminate\Validation\ValidationException::withMessages(['package_ids'=>'Record a package weight in kilograms before staging it for a truck.']);
            }
            $packages->each->update(['status'=>'dispatched']);
        });

        return redirect()->route('logistics.dispatch.index')
            ->with('success', 'Packages staged for dispatch.');
    }
}
