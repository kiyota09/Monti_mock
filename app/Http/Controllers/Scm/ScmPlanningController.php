<?php

namespace App\Http\Controllers\Scm;

use App\Http\Controllers\Controller;
use App\Models\Inv\Material;
use App\Models\Scm\MaterialRequest;
use App\Models\War\WarehouseStockItem;
use Inertia\Inertia;

class ScmPlanningController extends Controller
{
    /**
     * Demand & Materials Planning (textile MRP-lite).
     *
     * Compares open demand (pending / forwarded / rfq_sent material
     * requests for yarn, dyes, chemicals, accessories) against live
     * warehouse on-hand per material and flags shortages. Read-only —
     * fulfilment actions stay on Procurement / PRO pages.
     */
    public function index()
    {
        // On-hand per material (live stock only)
        $onHand = WarehouseStockItem::whereIn('status', ['in_stock', 'reserved'])
            ->where('quantity', '>', 0)
            ->selectRaw('material_id, SUM(quantity) as qty')
            ->groupBy('material_id')
            ->pluck('qty', 'material_id');

        // Open demand grouped by material
        $demand = MaterialRequest::whereIn('status', ['pending', 'forwarded', 'rfq_sent'])
            ->selectRaw('material_id, material_name, unit, SUM(required_qty) as qty, MAX(urgency) as top_urgency, COUNT(*) as requests')
            ->groupBy('material_id', 'material_name', 'unit')
            ->orderByDesc('qty')
            ->get();

        $materials = Material::whereIn('id', $demand->pluck('material_id')->filter()->all())
            ->get(['id', 'name', 'reorder_point', 'unit'])
            ->keyBy('id');

        $rows = $demand->map(function ($d) use ($onHand, $materials) {
            $have = (float) ($onHand[$d->material_id] ?? 0);
            $need = (float) $d->qty;
            $meta = $materials[$d->material_id] ?? null;

            return [
                'material_id' => $d->material_id,
                'material_name' => $d->material_name,
                'unit' => $d->unit,
                'required_qty' => $need,
                'on_hand' => $have,
                'gap' => $need - $have,
                'shortage' => $have < $need,
                'reorder_point' => $meta->reorder_point ?? null,
                'below_reorder' => $meta && $meta->reorder_point !== null ? $have < $meta->reorder_point : false,
                'requests' => (int) $d->requests,
                'top_urgency' => $d->top_urgency,
            ];
        })->values();

        return Inertia::render('Dashboard/SCM/Planning', [
            'summary' => [
                'materials' => $rows->count(),
                'shortages' => $rows->where('shortage', true)->count(),
                'totalRequired' => $rows->sum('required_qty'),
                'totalOnHand' => $rows->sum('on_hand'),
            ],
            'requirements' => $rows,
        ]);
    }
}
