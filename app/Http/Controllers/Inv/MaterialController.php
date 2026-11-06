<?php

namespace App\Http\Controllers\Inv;

use App\Http\Controllers\Controller;
use App\Models\Inv\Material;
use App\Models\Scm\MaterialRequest;
use App\Services\Inv\MaterialStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Carbon\Carbon;

class MaterialController extends Controller
{
    private const MATERIAL_CATEGORIES = [
        'Yarn',
        'Dye Stuff',
        'Auxillary Agent',
        'Basic Chemicals',
        'Boiler Chemicals',
        'Post-Treatment',
        'Pre-Treatment',
        'Bio-Processing Enzymes',
        'Care Agents',
    ];

    // Let existing records keep their classification until staff reassign them.
    private const LEGACY_CATEGORIES = ['Supplies', 'Packaging'];
    private const YARN_TIERS = ['Ultra Premium', 'Premium', 'Standard', 'Economy', 'Recycled'];

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
                $poItems = $receivingRecord->purchaseOrder?->items;
                $poItem = $poItems?->where('material_id', $item->material_id)->first();

                $unitPrice = (float) ($poItem->unit_price ?? 0);

                /**
                 * FETCH ACTUAL LOT NUMBER (control_number)
                 * We find the specific stock record created during this receiving event
                 */
                $stockRecord = $material->stockItems->first(fn ($stock) =>
                    $stock->purchase_order_id == $receivingRecord->scm_purchase_order_id
                    && (int) $stock->warehouse_id === (int) $receivingRecord->warehouse_id
                    && (float) $stock->quantity === (float) $item->received_qty
                );

                // Manual opening receipts have no purchase order, and their
                // stock lot may later be moved to its category warehouse.
                // Match by the receipt timestamp and receiver so history still
                // shows the original lot after a warehouse move.
                if (! $stockRecord && ! $receivingRecord->scm_purchase_order_id) {
                    $receivedAt = $receivingRecord->received_at
                        ? Carbon::parse($receivingRecord->received_at)->format('Y-m-d H:i:s')
                        : null;
                    $stockRecord = $material->stockItems->first(fn ($stock) =>
                        ! $stock->purchase_order_id
                        && (int) $stock->received_by === (int) $receivingRecord->received_by
                        && $receivedAt !== null
                        && Carbon::parse($stock->received_at)->format('Y-m-d H:i:s') === $receivedAt
                    );
                }

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
            'material_name' => $material->display_name,
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
                    'material_name' => $material->display_name,
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

        $tier = $request->input('yarn_tier');
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'category'      => ['required', Rule::in([...self::MATERIAL_CATEGORIES, ...self::LEGACY_CATEGORIES])],
            'yarn_tier'     => [$request->input('category') === 'Yarn' ? 'required' : 'nullable', Rule::in(self::YARN_TIERS)],
            'unit'          => 'required|in:Rolls,Kg,Pcs',
            'reorder_point' => 'required|numeric|min:0',
        ]);

        if ($validated['category'] === 'Yarn') {
            $duplicate = Material::where('category', 'Yarn')->where('name', $validated['name'])
                ->where('yarn_tier', $tier)->where('id', '!=', $material->id)->exists();
            if ($duplicate) return back()->withErrors(['name' => 'A Yarn material with this name and tier already exists.']);
        } else {
            $validated['yarn_tier'] = null;
        }
        $material->update($validated);

        return redirect()->back()->with('success', 'Material updated successfully.');
    }

    /**
     * Store a new material in the system.
     */
    public function store(Request $request)
    {
        $tier = $request->input('yarn_tier');
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'category'      => ['required', Rule::in(self::MATERIAL_CATEGORIES)],
            'yarn_tier'     => [$request->input('category') === 'Yarn' ? 'required' : 'nullable', Rule::in(self::YARN_TIERS)],
            'unit'          => 'required|in:Rolls,Kg,Pcs',
            'reorder_point' => 'required|integer|min:0',
        ]);

        if ($validated['category'] === 'Yarn' && Material::where('category', 'Yarn')->where('name', $validated['name'])->where('yarn_tier', $tier)->exists()) {
            return back()->withErrors(['name' => 'A Yarn material with this name and tier already exists.']);
        }
        if ($validated['category'] !== 'Yarn') $validated['yarn_tier'] = null;

        Material::create([
            'mat_id'        => Material::nextMatId(),
            'name'          => $validated['name'],
            'category'      => $validated['category'],
            'yarn_tier'     => $validated['yarn_tier'] ?? null,
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
