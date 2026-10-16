<?php

namespace App\Services\Inv;

use App\Models\Inv\Material;
use App\Models\Man\ManufacturingInventoryItem;
use App\Models\War\Warehouse;
use App\Models\War\WarehouseStockItem;

/**
 * Single source of truth for inventory stock math.
 *
 * Two inventories, one material:
 *  - UNOPENED (warehouse): in_stock + reserved rows with qty > 0 inside
 *    warehouses the user may see. Stock moved to production becomes 'used'
 *    (qty 0); 'rejected' rows never count.
 *  - OPENED (production): remaining qty on live manufacturing lots
 *    (depleted excluded — mirrors MAN ProductionInventory).
 *
 * Materials, Stock Checker and the INV dashboard all build from here so
 * their statuses can never drift apart again.
 */
class MaterialStockService
{
    /**
     * Warehouses the given user may see.
     * Secretaries / special officers see all. Users WITH explicit grants are
     * scoped to them; users with NO grants see all (grant tables are
     * unpopulated — no granting UI exists yet — so scoping them would
     * zero-out every material).
     */
    public static function visibleWarehouseIds($user): array
    {
        if (in_array($user->position ?? null, ['secretary', 'special_officer'], true)) {
            return Warehouse::pluck('id')->toArray();
        }

        if (method_exists($user, 'hasWarehouseAccess') && !$user->hasWarehouseAccess()) {
            return Warehouse::pluck('id')->toArray();
        }

        if (method_exists($user, 'warehouseAccess')) {
            return $user->warehouseAccess()->get()->pluck('id')->toArray();
        }

        return Warehouse::pluck('id')->toArray();
    }

    /**
     * Unopened (warehouse) available qty for one material.
     */
    public static function unopenedStock(int $materialId, array $warehouseIds): float
    {
        return (float) WarehouseStockItem::where('material_id', $materialId)
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereIn('status', ['in_stock', 'reserved'])
            ->where('quantity', '>', 0)
            ->sum('quantity');
    }

    /**
     * Unopened stock split per warehouse id.
     */
    public static function unopenedStockByWarehouse(int $materialId, array $warehouseIds): array
    {
        $out = [];
        foreach ($warehouseIds as $wid) {
            $out[$wid] = (float) WarehouseStockItem::where('material_id', $materialId)
                ->where('warehouse_id', $wid)
                ->whereIn('status', ['in_stock', 'reserved'])
                ->where('quantity', '>', 0)
                ->sum('quantity');
        }
        return $out;
    }

    /**
     * Unopened status key: out | low | ok.
     */
    public static function unopenedStatus(float $totalStock, $reorderPoint): string
    {
        return ($totalStock <= 0) ? 'out' : (($totalStock <= $reorderPoint) ? 'low' : 'ok');
    }

    /**
     * Bulk OPENED (production) aggregates for all materials at once.
     *
     * @return array{totals: \Illuminate\Support\Collection, ever: \Illuminate\Support\Collection, byDept: \Illuminate\Support\Collection, lifecycle: \Illuminate\Support\Collection}
     */
    public static function openedAggregates(): array
    {
        $totals = ManufacturingInventoryItem::where('status', '!=', 'depleted')
            ->selectRaw('material_id, SUM(remaining_quantity) as qty, COUNT(*) as lots')
            ->groupBy('material_id')
            ->get()
            ->keyBy('material_id');

        $ever = ManufacturingInventoryItem::selectRaw('material_id, COUNT(*) as lots')
            ->groupBy('material_id')
            ->pluck('lots', 'material_id');

        $byDept = ManufacturingInventoryItem::where('status', '!=', 'depleted')
            ->selectRaw('material_id, department, SUM(remaining_quantity) as qty, COUNT(*) as lots')
            ->groupBy('material_id', 'department')
            ->get()
            ->groupBy('material_id');

        $lifecycle = ManufacturingInventoryItem::selectRaw(
                'material_id, SUM(initial_quantity) as init, SUM(remaining_quantity) as rem'
            )
            ->groupBy('material_id')
            ->get()
            ->keyBy('material_id');

        return compact('totals', 'ever', 'byDept', 'lifecycle');
    }

    /**
     * Opened side for one material: stock, lots, status, per-dept breakdown.
     * Status: none (never moved) | available (remaining > 0) | depleted.
     */
    public static function openedInfo(int $materialId, array $aggregates): array
    {
        $row = $aggregates['totals'][$materialId] ?? null;
        $openedStock = (float) ($row->qty ?? 0);

        return [
            'opened_stock' => $openedStock,
            'opened_lots' => (int) ($row->lots ?? 0),
            'opened_status' => !$aggregates['ever']->has($materialId)
                ? 'none'
                : ($openedStock > 0 ? 'available' : 'depleted'),
            'production_breakdown' => ($aggregates['byDept'][$materialId] ?? collect())->map(fn ($r) => [
                'department' => $r->department,
                'remaining' => (float) $r->qty,
                'lots' => (int) $r->lots,
            ])->values(),
        ];
    }

    /**
     * Flow reconciliation for one material (detail modal strip):
     * received = warehouse live + production remaining + consumed.
     * Consumed is derived (moved initial − remaining, clamped ≥ 0).
     * Expects $material->receivingItems to be loaded.
     */
    public static function flowInfo(Material $material, array $aggregates): array
    {
        $life = $aggregates['lifecycle'][$material->id] ?? null;
        $movedInitial = (float) ($life->init ?? 0);

        return [
            'received_total' => (float) $material->receivingItems->sum('received_qty'),
            'moved_to_production' => $movedInitial,
            'consumed_qty' => max(0, $movedInitial - (float) ($life->rem ?? 0)),
        ];
    }

    /**
     * Full per-material payload shared by Materials and Stock Checker.
     * Pass precomputed openedAggregates() once and reuse for every row.
     */
    public static function buildRow(Material $material, array $warehouseIds, array $aggregates): array
    {
        $totalStock = self::unopenedStock($material->id, $warehouseIds);

        return [
            'id' => $material->id,
            'mat_id' => $material->mat_id,
            'name' => $material->name,
            'category' => $material->category,
            'unit' => $material->unit,
            'reorder_point' => (float) $material->reorder_point,
            'total_stock' => (float) $totalStock,
            'status' => self::unopenedStatus((float) $totalStock, $material->reorder_point),
        ] + self::openedInfo($material->id, $aggregates);
    }
}
