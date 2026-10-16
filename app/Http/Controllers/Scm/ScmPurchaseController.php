<?php

namespace App\Http\Controllers\Scm;

use App\Http\Controllers\Controller;
use App\Models\Scm\ScmPurchaseOrder;
use Inertia\Inertia;

class ScmPurchaseController extends Controller
{
    /**
     * Purchase Order Tracking — pipeline visibility over every SCM PO
     * (draft → sent → confirmed → partially_received → received).
     * Execution (send / invoice / pay) stays in PRO; SCM tracks.
     */
    public function index()
    {
        $orders = ScmPurchaseOrder::with(['items', 'invoices'])
            ->orderBy('issued_date', 'desc')
            ->take(100)
            ->get()
            ->map(fn ($po) => [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'supplier_name' => $po->supplier_name,
                'status' => $po->status,
                'received' => (bool) $po->received,
                'issued_date' => $po->issued_date,
                'expected_delivery' => $po->expected_delivery,
                'grand_total' => $po->grand_total,
                'items_count' => $po->items->count(),
                'invoices_count' => $po->invoices->count(),
                'items' => $po->items->map(fn ($i) => [
                    'material_name' => $i->material_name,
                    'qty' => $i->qty,
                    'unit' => $i->unit,
                    'unit_price' => $i->unit_price,
                    'total' => $i->total,
                ]),
            ]);

        return Inertia::render('Dashboard/SCM/PurchaseOrders', [
            'summary' => [
                'total' => $orders->count(),
                'awaiting' => $orders->whereIn('status', ['draft', 'sent', 'confirmed'])->count(),
                'inTransit' => $orders->whereIn('status', ['partially_received'])->count(),
                'received' => $orders->where('received', true)->count(),
            ],
            'orders' => $orders->values(),
        ]);
    }
}
