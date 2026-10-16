<?php

namespace App\Services\Eco;

use App\Models\Inv\Material;
use App\Models\Man\ManufacturingInventoryItem;
use App\Models\Ord\SalesOrder;
use App\Models\Scm\MaterialRequest;
use App\Models\War\WarehouseStockItem;

/**
 * Decision Support: stock sustainability check for ECO order acceptance.
 *
 * Before ECO accepts (pushes) a job order, this service verifies that live
 * stock can cover BOTH the new order AND everything already committed to
 * previously accepted orders:
 *
 *   supply   = unopened warehouse stock + opened production remaining
 *   atp      = supply − committed_to_accepted_orders
 *
 * Example: 2000kg on hand (1500 unopened + 500 opened), accepted orders
 * already consume 1800kg → ATP = 200kg, so a new 300kg order is blocked
 * and procurement is suggested for the shortfall materials.
 *
 * Committed = accepted-but-unfinished job orders (stock not yet consumed).
 * Terminal states (completed / delivered / cancelled) neither commit nor
 * consume in this model — their stock was already deducted at release.
 */
class StockSustainabilityService
{
    /**
     * Sales-order statuses that hold a claim on warehouse stock.
     */
    public const COMMITTED_STATUSES = [
        'pushed_to_scm',
        'pushed_to_ordermgmt',
        'confirmed',
        'in_planning',
        'inv_check',
        'inv_checked',
        'in_production',
        'production_done',
        'ready_for_dispatch',
        'in_transit',
        'on_hold',
    ];

    /**
     * Material requirement RATES for one order: [material_id => qty per
     * order unit], before multiplying by the order quantity. Shared by
     * materialsForOrder() and the DSS "why" breakdown in evaluate().
     */
    public function recipeRates(SalesOrder $order): array
    {
        $order->loadMissing('recipe');
        $recipe = $order->recipe;

        if (! $recipe) {
            return [];
        }

        $materials = $recipe->materials;
        if (is_string($materials)) {
            $materials = json_decode($materials, true);
        }
        if (! is_array($materials) || empty($materials)) {
            return [];
        }

        $byName = [];
        $names = [];
        foreach ($materials as $key => $qtyPerUnit) {
            if (! is_numeric($key)) {
                $names[] = (string) $key;
            }
        }
        if ($names) {
            $byName = Material::whereIn('name', array_unique($names))
                ->pluck('id', 'name')
                ->toArray();
        }

        $rates = [];
        foreach ($materials as $materialKey => $qtyPerUnit) {
            $matId = is_numeric($materialKey)
                ? (int) $materialKey
                : (int) ($byName[(string) $materialKey] ?? 0);
            if ($matId <= 0 || ((float) $qtyPerUnit) <= 0) {
                continue;
            }
            $rates[$matId] = ($rates[$matId] ?? 0) + (float) $qtyPerUnit;
        }

        return $rates;
    }

    /**
     * Material requirements for one order: [material_id => required_qty].
     * Derived from the attached recipe (BOM) × order quantity in kg.
     * Returns [] when the order has no usable recipe (verdict: no_recipe).
     *
     * Recipes come in two shapes: ID-keyed ({"1": qty} from CRM/BOM) and
     * name-keyed ({"Blue Dye": qty} from lab sign-off). Names are resolved
     * to material IDs; keys matching nothing are skipped instead of
     * surfacing as "Unknown material".
     */
    public function materialsForOrder(SalesOrder $order): array
    {
        $qty = (float) ($order->quantity ?? 0);
        $needed = [];
        foreach ($this->recipeRates($order) as $matId => $rate) {
            $need = $rate * $qty;
            $needed[$matId] = ($needed[$matId] ?? 0) + $need;
        }

        return array_filter($needed, fn ($v) => $v > 0);
    }

    /**
     * Per-material claim breakdown: which accepted orders hold how much.
     * [material_id => [{order_id, jo_number, qty}, ...]].
     * The order being evaluated is excluded so it isn't double-counted.
     */
    public function committedBreakdown(?int $excludeId = null): array
    {
        $orders = SalesOrder::whereIn('status', self::COMMITTED_STATUSES)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->with('recipe')
            ->orderBy('id')
            ->get();

        $lines = [];
        foreach ($orders as $order) {
            foreach ($this->materialsForOrder($order) as $matId => $qty) {
                $lines[$matId][] = [
                    'order_id' => $order->id,
                    'jo_number' => $order->jo_number ?? 'JO-'.$order->id,
                    'qty' => round($qty, 2),
                ];
            }
        }

        return $lines;
    }

    /**
     * Total kg already promised to accepted orders, per material.
     * The order being evaluated is excluded so it isn't double-counted.
     */
    public function committedTotals(?int $excludeId = null): array
    {
        $totals = [];
        foreach ($this->committedBreakdown($excludeId) as $matId => $lines) {
            $totals[$matId] = round(array_sum(array_column($lines, 'qty')), 2);
        }

        return $totals;
    }

    /**
     * Live warehouse stock per material — canonical on-hand definition
     * shared with Materials / Checker / Monitor / Planning (unopened rows:
     * in_stock + reserved, qty > 0).
     */
    public function liveStock(array $materialIds): array
    {
        if (empty($materialIds)) {
            return [];
        }

        return WarehouseStockItem::whereIn('material_id', $materialIds)
            ->whereIn('status', ['in_stock', 'reserved'])
            ->where('quantity', '>', 0)
            ->selectRaw('material_id, SUM(quantity) as qty')
            ->groupBy('material_id')
            ->pluck('qty', 'material_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();
    }

    /**
     * Live OPENED (production remaining) stock per material — same rule as
     * the Materials/Checker opened check: live manufacturing lots only
     * (depleted excluded, mirrors MAN ProductionInventory).
     */
    public function liveOpenedStock(array $materialIds): array
    {
        if (empty($materialIds)) {
            return [];
        }

        return ManufacturingInventoryItem::whereIn('material_id', $materialIds)
            ->where('status', '!=', 'depleted')
            ->selectRaw('material_id, SUM(remaining_quantity) as qty')
            ->groupBy('material_id')
            ->pluck('qty', 'material_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();
    }

    /**
     * Full sustainability verdict for one pending order.
     */
    public function evaluate(SalesOrder $order): array
    {
        $required = $this->materialsForOrder($order);

        if (empty($required)) {
            return [
                'verdict' => 'no_recipe',
                'sufficient' => true,
                'warning' => $order->recipe
                    ? 'Recipe has no resolvable materials — none of its entries match a known material. Correct the formulation for a precise check.'
                    : 'No recipe (BOM) attached — material needs could not be verified. Attach a recipe for a precise check.',
                'order_id' => $order->id,
                'jo_number' => $order->jo_number ?? 'JO-'.$order->id,
                'committed_orders' => 0,
                'materials' => [],
            ];
        }

        $committed = $this->committedTotals($order->id);
        $breakdown = $this->committedBreakdown($order->id);
        $rates = $this->recipeRates($order);
        $orderQty = (float) ($order->quantity ?? 0);
        $stock = $this->liveStock(array_keys($required));
        $opened = $this->liveOpenedStock(array_keys($required));
        $materials = Material::whereIn('id', array_keys($required))->get()->keyBy('id');

        $rows = [];
        $sufficient = true;
        foreach ($required as $matId => $need) {
            $unopened = (float) ($stock[$matId] ?? 0);
            $onFloor = (float) ($opened[$matId] ?? 0);
            // Full supply view: sealed warehouse stock PLUS usable opened
            // lots on the floor. An order is pushable while EITHER pool
            // (or both together) covers the total demand.
            $have = $unopened + $onFloor;
            $taken = (float) ($committed[$matId] ?? 0);
            $atp = $have - $taken;
            $shortage = max(0, $need - $atp);
            if ($shortage > 0) {
                $sufficient = false;
            }

            // "Why" detail for the DSS modal: every figure derived, nothing
            // unexplained. Committed lines capped (count kept exact).
            $lines = array_values($breakdown[$matId] ?? []);

            $rows[] = [
                'material_id' => $matId,
                'material_name' => $materials[$matId]->name ?? 'Unknown material',
                'unit' => $materials[$matId]->unit ?? 'kg',
                'required' => round($need, 2),
                'committed' => round($taken, 2),
                // Total demand on this material: this order's need plus
                // everything already promised to accepted orders.
                'total_needed' => round($need + $taken, 2),
                'available' => round($have, 2),
                'unopened' => round($unopened, 2),
                'opened' => round($onFloor, 2),
                'atp' => round($atp, 2),
                'shortage' => round($shortage, 2),
                'sufficient' => $shortage <= 0,
                'order_qty' => $orderQty,
                'recipe_rate' => round($rates[$matId] ?? 0, 4),
                'committed_lines' => array_slice($lines, 0, 10),
                'committed_lines_extra' => max(0, count($lines) - 10),
                'committed_orders_count' => count($lines),
            ];
        }

        return [
            'verdict' => $sufficient ? 'sufficient' : 'insufficient',
            'sufficient' => $sufficient,
            'order_id' => $order->id,
            'jo_number' => $order->jo_number ?? 'JO-'.$order->id,
            'committed_orders' => SalesOrder::whereIn('status', self::COMMITTED_STATUSES)->where('id', '!=', $order->id)->count(),
            'materials' => $rows,
        ];
    }

    /**
     * Create procurement suggestions (pending MaterialRequests) for every
     * shortfall material. Idempotent per job order: an existing pending
     * request for the same material + JO is reused, never duplicated.
     *
     * @return array the requests created (with `created` flag each)
     */
    public function suggestProcurement(SalesOrder $order, array $evaluation, ?string $requestedBy = null): array
    {
        $jo = $evaluation['jo_number'] ?? ('JO-'.$order->id);
        $created = [];

        foreach ($evaluation['materials'] ?? [] as $row) {
            if (($row['shortage'] ?? 0) <= 0) {
                continue;
            }

            $created[] = $this->fileShortageRequest($jo, $row, $requestedBy);
        }

        return $created;
    }

    /**
     * File a procurement request for ONE specific shortage material
     * (the Push Center DSS modal "Request" button). Same idempotency as
     * the bulk suggestions: an existing pending request for the same
     * material + JO is reused, never duplicated.
     *
     * @return array ['requested' => bool, 'reason'?, 'req_number'?, 'created'?, ...row]
     */
    public function requestMaterial(SalesOrder $order, int $materialId, ?string $requestedBy = null): array
    {
        $evaluation = $this->evaluate($order);
        $jo = $evaluation['jo_number'] ?? ('JO-'.$order->id);

        $row = collect($evaluation['materials'] ?? [])
            ->first(fn ($r) => (int) ($r['material_id'] ?? 0) === $materialId);

        if ($row === null) {
            return ['requested' => false, 'reason' => 'not_required', 'message' => 'This material is not required by the job recipe.'];
        }

        if (($row['shortage'] ?? 0) <= 0) {
            return ['requested' => false, 'reason' => 'no_shortage', 'message' => 'Stock covers this material — no request needed.'] + $row;
        }

        return ['requested' => true] + $this->fileShortageRequest($jo, $row, $requestedBy);
    }

    /**
     * Shared single-row filer behind suggestProcurement()/requestMaterial().
     */
    protected function fileShortageRequest(string $jo, array $row, ?string $requestedBy): array
    {
        $existing = MaterialRequest::where('material_id', $row['material_id'])
            ->where('status', 'pending')
            ->where('notes', 'like', '%'.$jo.'%')
            ->first();

        if ($existing) {
            return ['req_number' => $existing->req_number, 'created' => false] + $row;
        }

        $material = Material::find($row['material_id']);
        $mr = MaterialRequest::create([
                'req_number' => 'REQ-'.strtoupper(bin2hex(random_bytes(3))),
                'material_id' => $row['material_id'],
                'material_name' => $row['material_name'],
                'category' => $material->category ?? 'General',
                'unit' => $row['unit'],
                'current_stock' => $row['available'],
                'required_qty' => $row['shortage'],
                'urgency' => 'High',
                'notes' => "DSS auto-suggestion: {$jo} needs {$row['required']}{$row['unit']}, ATP {$row['atp']}{$row['unit']} — short {$row['shortage']}{$row['unit']}.",
                'requested_by' => $requestedBy ?? 'ECO-DSS',
                'requested_at' => now(),
                'status' => 'pending',
            ]);

            return ['req_number' => $mr->req_number, 'created' => true] + $row;
    }
}
