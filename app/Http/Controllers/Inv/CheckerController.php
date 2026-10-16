<?php

namespace App\Http\Controllers\Inv;

use App\Http\Controllers\Controller;
use App\Models\Inv\Material;
use App\Models\Ord\PurchaseOrder;
use App\Models\Scm\MaterialRequest;
use App\Services\Inv\MaterialStockService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckerController extends Controller
{
    /**
     * Display stock checker dashboard.
     * Stock math comes from MaterialStockService (shared with Materials and
     * the INV dashboard) — per-material Procure decisions use the same
     * unopened availability.
     */
    public function index()
    {
        $warehouseIds = MaterialStockService::visibleWarehouseIds(auth()->user());
        $aggregates = MaterialStockService::openedAggregates();

        $stockStatus = Material::all()->map(
            fn ($material) => MaterialStockService::buildRow($material, $warehouseIds, $aggregates)
        )->values()->all();

        $pendingOrdersCount = PurchaseOrder::whereHas('queue', function ($q) {
            $q->where('stage', 'inv_check');
        })->count();

        return Inertia::render('Dashboard/Inventory/Checker', [
            'materials' => $stockStatus,
            'pendingOrdersCount' => $pendingOrdersCount,
        ]);
    }

    /**
     * Request procurement for a specific material.
     * Now accepts quantity, urgency, and notes from the frontend.
     */
    public function requestProcurement(Request $request, Material $material)
    {
        // Now $request->all() will contain: ['required_qty' => ..., 'urgency' => ...]
      
        $validated = $request->validate([
            'required_qty' => 'required|numeric|min:0.01',
            'urgency' => 'required|in:High,Medium,Low',
            'notes' => 'nullable|string|max:1000',
        ]);

        $currentStock = MaterialStockService::unopenedStock(
            $material->id,
            MaterialStockService::visibleWarehouseIds(auth()->user())
        );
        $reqNumber = 'MR-' . date('Ymd') . '-' . rand(1000, 9999);

        MaterialRequest::create([
            'req_number' => $reqNumber,
            'material_id' => $material->id,
            'material_name' => $material->name,
            'category' => $material->category,
            'unit' => $material->unit,
            'current_stock' => (float) $currentStock,
            'reorder_point' => $material->reorder_point,
            'required_qty' => $validated['required_qty'],
            'urgency' => $validated['urgency'],
            'notes' => $validated['notes'] ?? null,
            'requested_by' => auth()->user()->name,
            'requested_at' => now(),
            'status' => 'pending',
        ]);

        return redirect()->back()->with('success', "Procurement request {$reqNumber} sent.");
    }

    /**
     * Check a specific order.
     */
    public function checkOrder(PurchaseOrder $order)
    {
        // Implement detailed check logic here
        // For now, just a placeholder
        return redirect()->back()->with('message', "Order {$order->po_number} checked.");
    }
}
