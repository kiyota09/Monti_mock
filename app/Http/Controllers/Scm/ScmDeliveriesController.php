<?php

namespace App\Http\Controllers\Scm;

use App\Http\Controllers\Controller;
use App\Models\War\WarehouseReceiving;
use Inertia\Inertia;

class ScmDeliveriesController extends Controller
{
    /**
     * Inbound Deliveries & QC — every goods receipt from suppliers
     * (yarn lots, dye chemicals, accessories) with warehouse, linked
     * PO and line-level accept / reject quantities for fabric QC.
     */
    public function index()
    {
        $receivings = WarehouseReceiving::with(['warehouse', 'purchaseOrder', 'items.material', 'receivedByUser'])
            ->orderBy('received_at', 'desc')
            ->take(100)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'receiving_number' => $r->receiving_number,
                'warehouse_name' => $r->warehouse->name ?? '—',
                'po_number' => $r->purchaseOrder->po_number ?? null,
                'status' => $r->status,
                'received_at' => $r->received_at,
                'received_by' => $r->receivedByUser->name ?? '—',
                'items' => $r->items->map(fn ($i) => [
                    'material_name' => $i->material->name ?? '—',
                    'expected_qty' => $i->expected_qty,
                    'received_qty' => $i->received_qty,
                    'rejected_qty' => $i->rejected_qty,
                    'status' => $i->status,
                    'reject_reason' => $i->reject_reason,
                ]),
            ]);

        return Inertia::render('Dashboard/SCM/Deliveries', [
            'summary' => [
                'total' => $receivings->count(),
                'completed' => $receivings->where('status', 'completed')->count(),
                'partial' => $receivings->where('status', 'partial')->count(),
                'pending' => $receivings->where('status', 'pending')->count(),
                'rejectedQty' => $receivings->flatMap(fn ($r) => $r['items'])->sum('rejected_qty'),
            ],
            'receivings' => $receivings->values(),
        ]);
    }
}
