<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\War\Warehouse;
use App\Models\War\WarehouseReceiving;
use App\Models\War\WarehouseReceivingItem;
use App\Models\War\WarehouseStockItem;
use App\Models\Inv\Material;
use App\Models\Scm\ScmPurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReceivingController extends Controller
{
    /**
     * Display the receiving dashboard.
     */
    public function index()
    {
        $user = auth()->user();
        
        $warehouses = in_array($user->position, ['secretary', 'special_officer'])
            ? Warehouse::all() 
            : Warehouse::where('supervisor_id', $user->id)->get();
        
        $receivings = WarehouseReceiving::with(['warehouse', 'items.material', 'purchaseOrder', 'receivedByUser'])
            ->whereIn('warehouse_id', $warehouses->pluck('id'))
            ->orderByDesc('received_at')
            ->get();

        $pendingPOs = ScmPurchaseOrder::with(['items.material'])
            ->where('status', 'shipping')
            ->get();

        $materials = Material::all();

        return Inertia::render('Dashboard/Warehouse/Receiving', [
            'receivings' => $receivings,
            'pendingPOs' => $pendingPOs,
            'materials'  => $materials,
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * Process receiving and generate Lot Numbers for DB storage.
     */
    public function receive(Request $request)
    {
        $data = $request->validate([
            'warehouse_id'         => 'required|exists:warehouses,id',
            'po_id'                => 'nullable|exists:scm_purchase_orders,id',
            'items'                => 'required|array',
            'items.*.material_id'  => 'required|exists:materials,id',
            'items.*.expected_qty' => 'required|numeric|min:0',
            'items.*.received_qty' => 'required|numeric|min:0',
            'items.*.rejected_qty' => 'nullable|numeric|min:0',
            'items.*.reject_reason'=> 'nullable|string',
        ]);

        return DB::transaction(function () use ($data) {
            // 1. Receiving Header
            $receivingNumber = 'RCV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

            $receiving = WarehouseReceiving::create([
                'receiving_number'      => $receivingNumber,
                'warehouse_id'          => $data['warehouse_id'],
                'scm_purchase_order_id' => $data['po_id'],
                'received_at'           => now(),
                'received_by'           => auth()->id(),
                'status'                => 'completed',
            ]);

            foreach ($data['items'] as $item) {
                $received = (float)($item['received_qty'] ?? 0);
                $rejected = (float)($item['rejected_qty'] ?? 0);
                
                $status = 'accepted';
                if ($received > 0 && $rejected > 0) $status = 'partial';
                if ($received == 0 && $rejected > 0) $status = 'rejected';

                // 2. Receiving Line Item
                WarehouseReceivingItem::create([
                    'receiving_id'  => $receiving->id,
                    'material_id'   => $item['material_id'],
                    'expected_qty'  => $item['expected_qty'],
                    'received_qty'  => $received,
                    'rejected_qty'  => $rejected,
                    'status'        => $status,
                    'reject_reason' => $item['reject_reason'] ?? null,
                ]);

                // 3. GENERATE LOT NUMBER & STORE TO STOCK DB
                if ($received > 0) {
                    $material = Material::find($item['material_id']);
                    
                    // Lot number format: LOT - date - materialID - random
                    $lotNumber = 'LOT-' . date('ymd') . '-' . str_pad($item['material_id'], 3, '0', STR_PAD_LEFT) . '-' . rand(100, 999);

                    WarehouseStockItem::create([
                        'control_number'   => $lotNumber, // Storing the generated Lot Number
                        'warehouse_id'     => $data['warehouse_id'],
                        'material_id'      => $item['material_id'],
                        'quantity'         => $received,
                        'unit'             => $material->unit,
                        'received_at'      => now(),
                        'received_by'      => auth()->id(),
                        'purchase_order_id'=> $data['po_id'],
                        'status'           => 'in_stock',
                    ]);
                }
            }

            // 4. Close PO
            if ($data['po_id']) {
                $po = ScmPurchaseOrder::find($data['po_id']);
                if ($po && $po->status === 'shipping') {
                    $po->update(['status' => 'delivered']);
                }
            }

            return redirect()->back()->with('success', "Delivery processed. Lot numbers generated.");
        });
    }

    public function store(Request $request)
    {
        return $this->receive($request);
    }
}