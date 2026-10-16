<?php

namespace App\Http\Controllers\Inv;

use App\Http\Controllers\Controller;
use App\Models\Inv\Material;
use App\Models\Scm\MaterialRequest;
use App\Services\Inv\MaterialStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class MaterialController extends Controller
{
    /**
     * Display the materials catalog with stock levels and delivery history.
     * Stock math comes from MaterialStockService (shared with Checker and
     * the INV dashboard).
     */
    public function index()
    {
        $warehouseIds = MaterialStockService::visibleWarehouseIds(auth()->user());

        // Fetch materials with deep relationships
        // We include stockItems to grab the specific 'control_number' (Lot Number)
        $materials = Material::with([
            'receivingItems.receiving.warehouse', 
            'receivingItems.receiving.purchaseOrder.items',
            'stockItems' 
        ])
        ->orderBy('name')
        ->get();

        // 2b. OPENED (production) stock per material — bulk aggregates so the
        // per-material map below stays cheap. Depleted lots are excluded
        // Shared stock math (unopened + opened + flow). Delivery history
        // below is Materials-specific.
        $aggregates = MaterialStockService::openedAggregates();

        $materialsWithStock = $materials->map(function ($material) use ($warehouseIds, $aggregates) {
            $row = MaterialStockService::buildRow($material, $warehouseIds, $aggregates);
            $flow = MaterialStockService::flowInfo($material, $aggregates);

            // 3. Map Delivery History from actual Receiving Logs
            $deliveryHistory = $material->receivingItems->map(function ($item) use ($material) {
                $receivingRecord = $item->receiving;
                if (!$receivingRecord) return null;
                
                // Link back to PO items to retrieve the historical unit price
                $poItem = $receivingRecord->purchaseOrder->items
                    ->where('material_id', $item->material_id)
                    ->first();

                $unitPrice = (float) ($poItem->unit_price ?? 0);

                /**
                 * FETCH ACTUAL LOT NUMBER (control_number)
                 * We find the specific stock record created during this receiving event
                 */
                $stockRecord = $material->stockItems
                    ->where('purchase_order_id', $receivingRecord->scm_purchase_order_id)
                    ->where('warehouse_id', $receivingRecord->warehouse_id)
                    ->where('quantity', $item->received_qty)
                    ->first();

                return [
                    'po_number'        => $receivingRecord->purchaseOrder->po_number ?? 'N/A',
                    'receiving_number' => $receivingRecord->receiving_number ?? 'N/A',
                    // TARGET: control_number column from warehouse_stock_items
                    'lot_number'       => $stockRecord->control_number ?? 'LOT-GEN-' . $item->id,
                    'warehouse_name'   => $receivingRecord->warehouse->name ?? 'Primary Hub',
                    'received_date'    => $receivingRecord->received_at 
                                            ? Carbon::parse($receivingRecord->received_at)->format('Y-m-d') 
                                            : 'N/A',
                    'kg'               => (float) $item->received_qty,
                    'price_per_kg'     => $unitPrice,
                    'total_amount'     => (float) ($item->received_qty * $unitPrice),
                ];
            })->filter()->values();

            return $row + [
                'unit_cost'        => (float) $material->unit_cost,
                'received_total'   => $flow['received_total'],
                'moved_to_production' => $flow['moved_to_production'],
                'consumed_qty'     => $flow['consumed_qty'],
                'delivery_history' => $deliveryHistory,
            ];
        });

        return Inertia::render('Dashboard/Inventory/Materials', [
            'materials' => $materialsWithStock,
        ]);
    }

    public function material() { return $this->index(); }

    /**
     * Handle the Procurement Request (Shopping Cart)
     */
    public function procurement(Request $request, $id)
    {
        $material = Material::findOrFail($id);

        // Validating data sent from the Modal
        $validated = $request->validate([
            'required_qty' => 'required|numeric|min:0.01',
            'urgency'      => 'nullable|in:High,Medium,Low',
            'notes'        => 'nullable|string|max:500',
        ]);

        MaterialRequest::create([
            'req_number'    => 'REQ-' . strtoupper(bin2hex(random_bytes(3))),
            'material_id'   => $material->id,
            'material_name' => $material->name,
            'category'      => $material->category,
            'unit'          => $material->unit,
            'required_qty'  => $validated['required_qty'] ?? $material->reorder_point,
            'urgency'       => $validated['urgency'] ?? 'Medium',
            'notes'         => $validated['notes'] ?? 'Auto-generated from Stock Checker',
            'requested_by'  => auth()->user()->name,
            'requested_at'  => now(),
            'status'        => 'pending',
        ]);

        return redirect()->back()->with('success', 'Procurement request sent to SCM.');
    }

    /**
     * Bulk procurement: request multiple raw materials in one send.
     * Each line carries its own quantity, urgency and notes (falling back
     * to batch-level urgency/notes when omitted). Creates one material
     * request per chosen material inside a single transaction — SCM picks
     * them up as individual pending rows on its Procurement Orders page.
     */
    public function bulkProcurement(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1|max:100',
            'items.*.material_id' => 'required|integer|exists:materials,id',
            'items.*.required_qty' => 'required|numeric|min:0.01',
            'items.*.urgency' => 'nullable|in:High,Medium,Low',
            'items.*.notes' => 'nullable|string|max:1000',
            'urgency' => 'nullable|in:High,Medium,Low',
            'notes' => 'nullable|string|max:1000',
        ]);

        $warehouseIds = MaterialStockService::visibleWarehouseIds(auth()->user());
        $batch = 'BULK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

        $created = 0;
        DB::transaction(function () use ($validated, $warehouseIds, $batch, &$created) {
            foreach ($validated['items'] as $item) {
                $material = Material::findOrFail($item['material_id']);

                MaterialRequest::create([
                    'req_number' => 'REQ-' . strtoupper(bin2hex(random_bytes(3))),
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'category' => $material->category,
                    'unit' => $material->unit,
                    'current_stock' => MaterialStockService::unopenedStock($material->id, $warehouseIds),
                    'reorder_point' => $material->reorder_point,
                    'required_qty' => $item['required_qty'],
                    'urgency' => $item['urgency'] ?? $validated['urgency'] ?? 'Medium',
                    'notes' => "[{$batch}] " . ($item['notes'] ?? $validated['notes'] ?? 'Bulk request from Stock Checker'),
                    'requested_by' => auth()->user()->name,
                    'requested_at' => now(),
                    'status' => 'pending',
                ]);
                $created++;
            }
        });

        return redirect()->back()->with(
            'success',
            "{$created} procurement request(s) sent to SCM ({$batch})."
        );
    }

    /**
     * Update material details (name, category, unit and reorder point).
     * Category/unit enums match store() so master data stays consistent.
     */
    public function update(Request $request, $id)
    {
        $material = Material::findOrFail($id);

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'category'      => 'required|in:Yarn,Dye,Supplies,Packaging',
            'unit'          => 'required|in:Rolls,Kg,Pcs',
            'reorder_point' => 'required|numeric|min:0',
        ]);

        $material->update($validated);

        return redirect()->back()->with('success', 'Material updated successfully.');
    }

    /**
     * Store a new material in the system.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'category'      => 'required|in:Yarn,Dye,Supplies,Packaging',
            'unit'          => 'required|in:Rolls,Kg,Pcs',
            'reorder_point' => 'required|integer|min:0',
        ]);

        Material::create([
            'mat_id'        => Material::nextMatId(),
            'name'          => $validated['name'],
            'category'      => $validated['category'],
            'unit'          => $validated['unit'],
            'reorder_point' => $validated['reorder_point'],
            'unit_cost'     => 0, 
        ]);

        return redirect()->back()->with('success', 'Material registered successfully.');
    }

    /**
     * Delete material if no stock history exists.
     */
    public function destroy($id)
    {
        $material = Material::findOrFail($id);
        if (WarehouseStockItem::where('material_id', $material->id)->exists()) {
            return redirect()->back()->withErrors(['error' => 'Deletion denied: Material has associated stock records.']);
        }
        $material->delete();
        return redirect()->back()->with('success', 'Material removed.');
    }
}