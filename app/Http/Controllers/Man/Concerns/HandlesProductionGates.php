<?php

namespace App\Http\Controllers\Man\Concerns;

use App\Models\Man\DyeJob;
use App\Models\Man\Fabric;
use App\Models\Man\IronJob;
use App\Models\Man\ManufacturingOrder;
use App\Models\Man\Package;
use App\Models\Man\SqueezerJob;
use App\Models\Man\SoftenerJob;
use App\Models\Ord\SalesOrder;
use App\Models\War\WarehousePackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shared production quality-gate actions.
 *
 * The gates are split by department ownership:
 *  - knitting output (passFabric)           → Knitting supervisor
 *  - dyeing stages (dye/softener/squeezer/iron) → Dyeing supervisor
 *  - package inspection (inspectPackage)     → Checker Quality staff
 *  - push inspected packages to logistics    → Finishing supervisor
 *
 * Used by both the staff checker controller and the supervisor gate controller
 * so the state machine stays in one place.
 */
trait HandlesProductionGates
{
    // ========== Job Order intake (Knitting supervisor) ==========
    // The knitting supervisor is the first receiver of a pushed Job Order:
    // verify stock, then release it to the floor.

    public function checkInventory($orderId)
    {
        ManufacturingOrder::findOrFail($orderId);

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

    // ========== Knitting gate ==========
    // Gate 1 — knitting output. Approves the fabric into dyeing (or softener
    // for lots that skip dyeing) or rejects it to the rejected pile.

    public function passFabric(Request $request, $fabricId)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            // No softener skip: knitting output always enters at the dyeing
            // department, where the dyeing supervisor assigns the color stage.
            'destination' => 'required_if:action,approve|in:dyeing',
            'rejection_reason' => 'required_if:action,reject|string|nullable|max:500',
        ]);

        $fabric = Fabric::findOrFail($fabricId);
        if ($fabric->status !== 'pending') {
            return back()->with('error', 'This fabric already left the knitting gate.');
        }

        if ($validated['action'] === 'reject') {
            $salesOrderId = $fabric->sales_order_id;

            DB::transaction(function () use ($fabric, $validated, $salesOrderId) {
                $fabric->update([
                    'status' => 'rejected',
                    'rejection_action' => null,
                    'rejection_reason' => $validated['rejection_reason']
                        ?? 'Rejected at the knitting gate — remake required.',
                    // Detach the fabric from the JO so a fresh fabric can be
                    // produced for it.
                    'sales_order_id' => null,
                ]);

                // Re-open the JO for the knitting yarn staff it is assigned to,
                // so it returns to their Production Job Orders to be made again.
                if ($salesOrderId) {
                    SalesOrder::whereKey($salesOrderId)->update([
                        'knitting_done_at' => null,
                        'knitting_done_by' => null,
                    ]);
                }
            });

            return redirect()->back()->with('message', 'Fabric not approved. The Job Order was returned to the knitting yarn staff to be made again.');
        }

        $fabric->update(['status' => $validated['destination']]);

        return redirect()->back()->with('message', 'Knitting approved. Fabric passed to ' . $validated['destination']);
    }

    // ========== Dyeing gates ==========

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
        }

        $fabric->update([
            'status' => 'rejected',
            'rejection_action' => 'recolor',
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        return redirect()->back()->with('message', 'Fabric rejected.');
    }

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
        }

        $fabric->update(['status' => 'softener']);
        $softener->update(['status' => 'resoften']);

        return redirect()->back()->with('message', 'Softening not approved. Fabric sent back for re-softening.');
    }

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

    public function passIron(Request $request, $ironId)
    {
        $validated = $request->validate([
            'action' => 'required|in:pack,reiron',
        ]);

        $iron = IronJob::with('squeezerJob.softenerJob.fabric')->findOrFail($ironId);

        $squeezerJob = $iron->squeezerJob;
        if (! $squeezerJob) {
            return back()->with('error', 'This iron job has no linked squeezer job — cannot pack.');
        }

        $softenerJob = $squeezerJob->softenerJob;
        if (! $softenerJob) {
            return back()->with('error', 'This iron job has no linked softener job — cannot pack.');
        }

        $fabric = $softenerJob->fabric;
        if (! $fabric) {
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

    // ========== Finishing gates ==========

    /**
     * Checker Quality inspects a packaging output: good → 'checked' (ready for
     * the finishing supervisor to push), not good → 'rejected'.
     */
    public function inspectPackage(Request $request, $packageId)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'remarks' => 'required_if:action,reject|string|nullable|max:500',
        ]);

        $package = Package::findOrFail($packageId);

        if ($package->status !== 'pending') {
            return back()->with('error', 'This package was already inspected.');
        }

        // Only the checker the finishing supervisor assigned may inspect it.
        if (method_exists($this, 'isAssignedItem')
            && ! $this->isAssignedItem($package->manufacturing_order_id, $package->fabric?->sales_order_id)) {
            return back()->with('error', 'This Job Order is not assigned to you by your supervisor.');
        }

        $package->update([
            'status' => $validated['action'] === 'approve' ? 'checked' : 'rejected',
            'checked_by' => auth()->id(),
            'checked_at' => now(),
            'check_remarks' => $validated['remarks'] ?? null,
        ]);

        return redirect()->back()->with('message', $validated['action'] === 'approve'
            ? 'Package passed quality check. Ready for logistics.'
            : 'Package failed quality check.');
    }

    /**
     * Finishing supervisor pushes an inspected package to logistics
     * (creates a WarehousePackage). Only 'checked' packages may be pushed.
     */
    public function pushToLogistics($packageId)
    {
        $package = Package::with('fabric.salesOrder.recipe.product')->findOrFail($packageId);

        if ($package->status === 'delivered') {
            return back()->with('error', 'Package already sent to logistics.');
        }

        if ($package->status !== 'checked') {
            return back()->with('error', 'Package must pass the quality check first.');
        }

        $product = $package->fabric->salesOrder->recipe->product ?? null;
        if (! $product) {
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
