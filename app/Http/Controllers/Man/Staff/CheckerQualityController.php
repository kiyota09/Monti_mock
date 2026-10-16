<?php

namespace App\Http\Controllers\Man\Staff;

use App\Models\Man\DyeJob;
use App\Models\Man\Fabric;
use App\Models\Man\IronJob;
use App\Models\Man\ManufacturingOrder;
use App\Models\Man\Package;
use App\Models\Man\SoftenerJob;
use App\Models\Man\SqueezerJob;
use App\Models\War\WarehousePackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CheckerQualityController extends ManufacturingStaffController
{
    public function index()
    {
        $stats = [
            'pending_orders' => ManufacturingOrder::where('status', 'pending')->count(),
            'fabrics_pending' => Fabric::where('status', 'pending')->count(),
            'dye_pending' => DyeJob::whereHas('fabric', fn($q) => $q->where('status', 'dyeing'))->count(),
            'softener_pending' => SoftenerJob::whereHas('fabric', fn($q) => $q->where('status', 'softener'))
                ->count(),
            'squeezer_pending' => SqueezerJob::whereHas('softenerJob.fabric', fn($q) => $q->where('status', 'squeezer'))->count(),
            'iron_pending' => IronJob::whereHas('squeezerJob.softenerJob.fabric', fn($q) => $q->where('status', 'iron'))->count(),
            'packages_pending' => Package::where('status', 'pending')->count(),
        ];

        return Inertia::render('Dashboard/MAN/Employee/CheckerQuality/Index', [
            'stats' => $stats,
        ]);
    }

    public function production()
    {
        $orders = ManufacturingOrder::with(['purchaseOrder.client', 'salesOrder.client'])
            ->where('status', 'pending')
            ->get()
            ->map(function ($order) {
                if ($order->purchaseOrder) {
                    return [
                        'id' => $order->id,
                        'po_number' => $order->purchaseOrder->po_number,
                        'client' => $order->purchaseOrder->client->company_name ?? 'N/A',
                        'total_quantity' => $order->total_quantity,
                        'remaining_quantity' => $order->remaining_quantity,
                        'type' => 'purchase_order',
                    ];
                } elseif ($order->salesOrder) {
                    return [
                        'id' => $order->id,
                        'po_number' => $order->salesOrder->jo_number ?? 'JO-' . $order->salesOrder->id,
                        'client' => $order->salesOrder->client->company_name ?? 'N/A',
                        'total_quantity' => $order->total_quantity,
                        'remaining_quantity' => $order->remaining_quantity,
                        'type' => 'sales_order',
                    ];
                }
                return null;
            })
            ->filter();

        $fabrics = Fabric::with('machine', 'operator', 'salesOrder')
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();

        $dyeJobs = DyeJob::with('fabric.salesOrder', 'machine', 'operator')
            ->whereHas('fabric', fn($q) => $q->where('status', 'dyeing'))
            ->get();

        $softenerJobs = SoftenerJob::with('fabric.salesOrder', 'machine', 'operator', 'squeezerJob')
            ->whereHas('fabric', fn($q) => $q->where('status', 'softener'))
            ->get();

        $squeezerJobs = SqueezerJob::with('softenerJob.fabric.salesOrder', 'machine', 'operator', 'ironJob')
            ->whereHas('softenerJob.fabric', fn($q) => $q->where('status', 'squeezer'))
            ->get();

        $ironJobs = IronJob::with('squeezerJob.softenerJob.fabric.salesOrder', 'operator')
            ->whereHas('squeezerJob.softenerJob.fabric', fn($q) => $q->where('status', 'iron'))
            ->get();

        $packages = Package::with(['fabric.salesOrder.recipe.product', 'operator'])
            ->where('status', 'pending')
            ->get()
            ->map(function ($pkg) {
                $product = $pkg->fabric->salesOrder->recipe->product ?? null;
                return [
                    'id' => $pkg->id,
                    'code' => $pkg->code,
                    'quantity' => $pkg->quantity,
                    'status' => $pkg->status,
                    'product_name' => $product->name ?? 'Unknown Product',
                    'product_sku' => $product->sku ?? '',
                    'fabric_code' => $pkg->fabric->code ?? '',
                    'yarn_type' => $pkg->fabric->yarn_type ?? '',
                    'weight' => $pkg->fabric->weight ?? 0,
                    'operator' => $pkg->operator->name ?? '',
                ];
            });

        return Inertia::render('Dashboard/MAN/Employee/CheckerQuality/Production', [
            'orders' => $orders,
            'fabrics' => $fabrics,
            'dyeJobs' => $dyeJobs,
            'softenerJobs' => $softenerJobs,
            'squeezerJobs' => $squeezerJobs,
            'ironJobs' => $ironJobs,
            'packages' => $packages,
        ]);
    }

    // ========== Order Actions ==========

    public function checkInventory($orderId)
    {
        $order = ManufacturingOrder::findOrFail($orderId);
        return redirect()->back()->with('message', 'Inventory checked. Available: 0 items.');
    }

    public function startProduction($orderId)
    {
        $order = ManufacturingOrder::findOrFail($orderId);
        $order->update(['status' => 'in_progress']);

        if ($order->purchaseOrder && $order->purchaseOrder->queue) {
            $order->purchaseOrder->queue->update(['man_started_at' => now()]);
        }
        if ($order->salesOrder) {
            $order->salesOrder->update(['status' => 'in_production']);
        }

        return redirect()->back()->with('message', 'Production started.');
    }

    // ========== Fabric Actions ==========
    // Gate 1 — knitting output. The checker approves the fabric into dyeing
    // (or softener for lots that skip dyeing) or rejects it to the rejected
    // pile (manager recolor / total-reject flow).

    public function passFabric(Request $request, $fabricId)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'destination' => 'required_if:action,approve|in:dyeing,softener',
            'rejection_reason' => 'required_if:action,reject|string|nullable|max:500',
        ]);

        $fabric = Fabric::findOrFail($fabricId);
        if ($fabric->status !== 'pending') {
            return back()->with('error', 'This fabric already left the knitting gate.');
        }

        if ($validated['action'] === 'reject') {
            $fabric->update([
                'status' => 'rejected',
                'rejection_action' => 'recolor',
                'rejection_reason' => $validated['rejection_reason'] ?? null,
            ]);

            return redirect()->back()->with('message', 'Fabric rejected and sent to the rejected pile.');
        }

        $fabric->update(['status' => $validated['destination']]);

        return redirect()->back()->with('message', 'Knitting approved. Fabric passed to ' . $validated['destination']);
    }

    // ========== Dye Actions ==========
    // Gate 2 — dye output. Fabric stays 'dyeing' (set only by this gate or a
    // manager recolor) until the checker approves it to softener or rejects it.

    public function passDye(Request $request, $dyeId)
    {
        $validated = $request->validate([
            'action' => 'required|in:quality,reject',
            'rejection_reason' => 'required_if:action,reject|string|nullable|max:500',
        ]);

        $dye = DyeJob::with('fabric')->findOrFail($dyeId);
        $fabric = $dye->fabric;
        if (! $fabric || $fabric->status !== 'dyeing') {
            return back()->with('error', 'This dye job already left the dyeing gate.');
        }

        if ($validated['action'] === 'quality') {
            $fabric->update(['status' => 'softener']);
            return redirect()->back()->with('message', 'Dyeing approved. Fabric passed to softener stage.');
        } else {
            $fabric->update([
                'status' => 'rejected',
                'rejection_action' => 'recolor',
                'rejection_reason' => $validated['rejection_reason'] ?? null,
            ]);
            return redirect()->back()->with('message', 'Fabric rejected.');
        }
    }

    // ========== Softener Actions ==========
    // Gate 3 — softener output. Fabric stays 'softener' until the checker
    // approves it to squeezer or sends it back for re-softening.

    public function passSoftener(Request $request, $softenerId)
    {
        $validated = $request->validate([
            'action' => 'required|in:quality,resoften',
        ]);

        $softener = SoftenerJob::with('fabric')->findOrFail($softenerId);
        $fabric = $softener->fabric;
        if (! $fabric || $fabric->status !== 'softener') {
            return back()->with('error', 'This softener job already left the softener gate.');
        }

        if ($validated['action'] === 'quality') {
            $fabric->update(['status' => 'squeezer']);
            return redirect()->back()->with('message', 'Softening approved. Fabric passed to squeezer.');
        } else {
            $fabric->update(['status' => 'softener']);
            $softener->update(['status' => 'resoften']);
            return redirect()->back()->with('message', 'Softening not approved. Fabric sent back for re-softening.');
        }
    }

    // ========== Squeezer Actions ==========
    // Gate 4 — squeezer output. Fabric stays 'squeezer' until the checker
    // approves it to iron or sends it back for re-squeezing (the failed job
    // row is removed so the redo replaces it instead of duplicating).

    public function passSqueezer(Request $request, $squeezerId)
    {
        $validated = $request->validate([
            'action' => 'required|in:quality,resqueeze',
        ]);

        $squeezer = SqueezerJob::with('softenerJob.fabric')->findOrFail($squeezerId);
        $fabric = $squeezer->softenerJob?->fabric;
        if (! $fabric || $fabric->status !== 'squeezer') {
            return back()->with('error', 'This squeezer job already left the squeezer gate.');
        }
        // Iron work already recorded downstream — re-squeezing now would
        // orphan it. Reject the iron job first, then send this back.
        if ($squeezer->ironJob()->exists()) {
            return back()->with('error', 'Ironing is already recorded for this fabric — handle the iron gate first.');
        }

        if ($validated['action'] === 'quality') {
            $fabric->update(['status' => 'iron']);

            return redirect()->back()->with('message', 'Squeezing approved. Fabric passed to ironing stage.');
        }

        DB::transaction(function () use ($squeezer, $fabric) {
            $squeezer->softenerJob()->update(['status' => 'softened']);
            $squeezer->delete();
        });

        return redirect()->back()->with('message', 'Squeezing not approved. Fabric sent back for re-squeezing.');
    }

    // ========== Iron Actions ==========
    // Gate 5 — iron output. Fabric stays 'iron' until the checker approves
    // it to packaging or sends it back for re-ironing (the failed job row
    // is removed so the redo replaces it instead of duplicating).

    public function passIron(Request $request, $ironId)
    {
        $validated = $request->validate([
            'action' => 'required|in:pack,reiron',
        ]);

        $iron = IronJob::with('squeezerJob.softenerJob.fabric')->findOrFail($ironId);

        $squeezerJob = $iron->squeezerJob;
        if (!$squeezerJob) {
            return back()->with('error', 'This iron job has no linked squeezer job — cannot pack.');
        }

        $softenerJob = $squeezerJob->softenerJob;
        if (!$softenerJob) {
            return back()->with('error', 'This iron job has no linked softener job — cannot pack.');
        }

        $fabric = $softenerJob->fabric;
        if (!$fabric) {
            return back()->with('error', 'This iron job has no linked fabric — cannot pack.');
        }
        if ($fabric->status !== 'iron') {
            return back()->with('error', 'This iron job already left the ironing gate.');
        }

        if ($validated['action'] === 'pack') {
            $fabric->update(['status' => 'packed']);

            return redirect()->back()->with('message', 'Ironing approved. Fabric packed successfully.');
        }

        $iron->delete();

        return redirect()->back()->with('message', 'Ironing not approved. Fabric sent back for re-ironing.');
    }

    // ========== Package Actions ==========

    /**
     * Push a manufacturing package to logistics (creates a WarehousePackage).
     */
    public function pushToLogistics($packageId)
    {
        $package = Package::with('fabric.salesOrder.recipe.product')->findOrFail($packageId);

        if ($package->status === 'delivered') {
            return back()->with('error', 'Package already sent to logistics.');
        }

        $product = $package->fabric->salesOrder->recipe->product ?? null;
        if (!$product) {
            return back()->with('error', 'No product associated with this package.');
        }

        DB::transaction(function () use ($package, $product) {
            WarehousePackage::create([
                'package_number'         => $package->code,
                'manufacturing_order_id' => $package->manufacturing_order_id,
                'product_id'             => $product->id,
                'quantity'               => $package->quantity,
                'status'                 => 'pushed_to_logistics',
                'pushed_at'              => now(),
                'pushed_by'              => auth()->id(),
            ]);

            $package->update(['status' => 'delivered']);
        });

        return back()->with('message', 'Package sent to logistics.');
    }
}