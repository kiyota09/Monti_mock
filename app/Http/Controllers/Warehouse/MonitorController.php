<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Man\ManufacturingInventoryItem;
use App\Models\War\Warehouse;
use App\Models\War\WarehouseFloor;
use App\Models\War\WarehouseSection;
use App\Models\War\WarehouseShelf;
use App\Models\War\WarehouseStockItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MonitorController extends Controller
{
    /**
     * Display the warehouse monitor with floors, grids and stock.
     */
    public function show(Warehouse $warehouse)
    {
        $user = auth()->user();

        if ($warehouse->supervisor_id !== $user->id && $warehouse->manager_id !== $user->id) {
            abort(403, 'You do not have access to this warehouse.');
        }

        // Auto-provision a Ground Floor for warehouses created before multi-floor support
        if ($warehouse->floors()->count() === 0) {
            $floor = $warehouse->floors()->create([
                'name' => 'Ground Floor',
                'level' => 1,
                'grid_rows' => $warehouse->grid_rows ?? 3,
                'grid_cols' => $warehouse->grid_cols ?? 3,
            ]);
            $warehouse->sections()->whereNull('floor_id')->update(['floor_id' => $floor->id]);
        }

        $floors = $warehouse->floors()
            ->with([
                'sections.shelves.stockItems' => fn ($q) => $q->where('quantity', '>', 0)->whereIn('status', ['in_stock', 'reserved'])->with('material'),
                'sections.stockItemsNoShelf' => fn ($q) => $q->where('quantity', '>', 0)->whereIn('status', ['in_stock', 'reserved'])->with('material'),
            ])
            ->orderBy('level')
            ->get();

        // Flat sections list kept for backward compatibility
        $sections = $warehouse->sections()
            ->with([
                'shelves.stockItems' => fn ($q) => $q->where('quantity', '>', 0)->whereIn('status', ['in_stock', 'reserved'])->with('material'),
                'stockItemsNoShelf' => fn ($q) => $q->where('quantity', '>', 0)->whereIn('status', ['in_stock', 'reserved'])->with('material'),
                'floor',
            ])
            ->get();

        // Fetch stock that has no location assigned yet — hide depleted (0 / used) items
        $unassignedStock = WarehouseStockItem::where('warehouse_id', $warehouse->id)
            ->whereNull('shelf_id')
            ->whereNull('section_id')
            ->where('quantity', '>', 0)
            ->whereIn('status', ['in_stock', 'reserved'])
            ->with('material')
            ->get();

        // Lightweight destination tree for the Move modal (other warehouses + floors/sectors/shelves)
        $moveTargets = Warehouse::where('id', '!=', $warehouse->id)
            ->where(fn ($q) => $q->where('supervisor_id', $user->id)->orWhere('manager_id', $user->id))
            ->with(['floors.sections.shelves'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Dashboard/Warehouse/Monitor', [
            'warehouse' => $warehouse,
            'floors' => $floors,
            'sections' => $sections,
            'unassignedStock' => $unassignedStock,
            'moveTargets' => $moveTargets,
        ]);
    }

    /**
     * Create a new floor in the warehouse.
     */
    public function storeFloor(Request $request, Warehouse $warehouse)
    {
        $user = auth()->user();
        if ($warehouse->supervisor_id !== $user->id && $warehouse->manager_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        $request->merge(['name' => trim((string) $request->input('name', ''))]);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('warehouse_floors', 'name')->where(fn ($q) => $q->where('warehouse_id', $warehouse->id)),
            ],
            'grid_rows' => 'nullable|integer|min:1|max:20',
            'grid_cols' => 'nullable|integer|min:1|max:20',
        ], [
            'name.unique' => 'A floor with this name already exists in this warehouse.',
        ]);

        $maxLevel = (int) ($warehouse->floors()->max('level') ?? 0);

        $floor = $warehouse->floors()->create([
            'name' => $data['name'],
            'level' => $maxLevel + 1,
            'grid_rows' => $data['grid_rows'] ?? 3,
            'grid_cols' => $data['grid_cols'] ?? 3,
        ]);

        return redirect()->back()->with('success', "Floor '{$floor->name}' added.");
    }

    /**
     * Rename / resize a floor.
     */
    public function updateFloor(Request $request, WarehouseFloor $floor)
    {
        $user = auth()->user();
        if ($floor->warehouse->supervisor_id !== $user->id && $floor->warehouse->manager_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        $request->merge(['name' => trim((string) $request->input('name', ''))]);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('warehouse_floors', 'name')->ignore($floor->id)->where(fn ($q) => $q->where('warehouse_id', $floor->warehouse_id)),
            ],
            'grid_rows' => 'required|integer|min:1|max:20',
            'grid_cols' => 'required|integer|min:1|max:20',
        ], [
            'name.unique' => 'A floor with this name already exists in this warehouse.',
        ]);

        $floor->update($data);

        return redirect()->back()->with('success', "Floor '{$floor->name}' updated.");
    }

    /**
     * Delete a floor — only when it holds ZERO live raw materials.
     * Empty sectors/shelves on the floor are cascade-removed with it.
     */
    public function destroyFloor(WarehouseFloor $floor)
    {
        $user = auth()->user();
        if ($floor->warehouse->supervisor_id !== $user->id && $floor->warehouse->manager_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        // Never delete the last remaining floor
        if ($floor->warehouse->floors()->count() <= 1) {
            return redirect()->back()->withErrors(['error' => 'A warehouse must keep at least one floor.']);
        }

        $sectionIds = $floor->sections()->pluck('id');
        $liveCount = WarehouseStockItem::whereIn('section_id', $sectionIds)
            ->where('quantity', '>', 0)
            ->whereIn('status', ['in_stock', 'reserved'])
            ->count();

        if ($liveCount > 0) {
            return redirect()->back()->withErrors(['error' => "Cannot delete “{$floor->name}” — it still holds {$liveCount} raw material(s). Move them out first."]);
        }

        DB::transaction(function () use ($floor) {
            // Remove now-empty shelves/sectors, then the floor itself
            WarehouseShelf::whereIn('section_id', $floor->sections()->pluck('id'))->delete();
            $floor->sections()->delete();
            $floor->delete();
        });

        return redirect()->back()->with('success', 'Empty floor removed.');
    }

    /**
     * Update grid layout and dimensions — multi-floor aware.
     * Accepts new `floors` payload; falls back to legacy single-grid payload.
     * Uses a Sync approach to prevent data loss for existing stock.
     */
    public function updateLayout(Request $request, Warehouse $warehouse)
    {
        $user = auth()->user();
        if ($warehouse->supervisor_id !== $user->id && $warehouse->manager_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        $data = $request->validate([
            'floors' => 'sometimes|array',
            'floors.*.id' => 'nullable',
            'floors.*.name' => 'required_with:floors|string|max:100',
            'floors.*.grid_rows' => 'required_with:floors|integer|min:1|max:20',
            'floors.*.grid_cols' => 'required_with:floors|integer|min:1|max:20',
            'floors.*.sections' => 'sometimes|array',
            'floors.*.sections.*.id' => 'nullable',
            'floors.*.sections.*.name' => 'required|string',
            'floors.*.sections.*.row' => 'required|integer',
            'floors.*.sections.*.col' => 'required|integer',
            'floors.*.sections.*.shelves' => 'sometimes|array',
            // legacy single-grid payload
            'grid_rows' => 'sometimes|integer',
            'grid_cols' => 'sometimes|integer',
            'sections' => 'sometimes|array',
            'sections.*.id' => 'nullable',
            'sections.*.name' => 'required_with:sections|string',
            'sections.*.row' => 'required_with:sections|integer',
            'sections.*.col' => 'required_with:sections|integer',
            'sections.*.shelves' => 'sometimes|array',
        ]);

        try {
            DB::beginTransaction();

            if (!empty($data['floors'])) {
                $activeFloorIds = [];
                $allActiveSectionIds = [];

                foreach (array_values($data['floors']) as $i => $fl) {
                    $floorId = (isset($fl['id']) && !str_starts_with((string) $fl['id'], 'temp-')) ? $fl['id'] : null;

                    $floor = WarehouseFloor::updateOrCreate(
                        ['id' => $floorId, 'warehouse_id' => $warehouse->id],
                        [
                            'name' => $fl['name'],
                            'level' => $i + 1,
                            'grid_rows' => $fl['grid_rows'],
                            'grid_cols' => $fl['grid_cols'],
                        ]
                    );
                    $activeFloorIds[] = $floor->id;

                    $activeSectionIds = [];
                    foreach ($fl['sections'] ?? [] as $sec) {
                        $sectionId = (isset($sec['id']) && !str_starts_with((string) $sec['id'], 'temp-')) ? $sec['id'] : null;

                        $section = WarehouseSection::updateOrCreate(
                            ['id' => $sectionId, 'warehouse_id' => $warehouse->id],
                            [
                                'floor_id' => $floor->id,
                                'name' => $sec['name'],
                                'grid_row' => $sec['row'],
                                'grid_col' => $sec['col'],
                            ]
                        );
                        $activeSectionIds[] = $section->id;
                        $allActiveSectionIds[] = $section->id;

                        $activeShelfIds = [];
                        foreach ($sec['shelves'] ?? [] as $sh) {
                            $shId = (isset($sh['id']) && !str_starts_with((string) $sh['id'], 'ts-')
                                && !str_starts_with((string) $sh['id'], 'temp-')) ? $sh['id'] : null;

                            $shelf = WarehouseShelf::updateOrCreate(
                                ['id' => $shId, 'section_id' => $section->id],
                                ['shelf_number' => $sh['shelf_number']]
                            );
                            $activeShelfIds[] = $shelf->id;
                        }
                        $section->shelves()->whereNotIn('id', $activeShelfIds)->delete();
                    }

                    $floor->sections()->whereNotIn('id', $activeSectionIds)->delete();
                }

                // ── Guard: never orphan live materials via layout sync ──
                $doomedFloorIds = $warehouse->floors()->whereNotIn('id', $activeFloorIds)->pluck('id');
                if ($doomedFloorIds->isNotEmpty()) {
                    $liveInDoomedFloors = WarehouseStockItem::whereIn('section_id', function ($q) use ($doomedFloorIds) {
                        $q->select('id')->from('warehouse_sections')->whereIn('floor_id', $doomedFloorIds);
                    })->where('quantity', '>', 0)->whereIn('status', ['in_stock', 'reserved'])->count();
                    if ($liveInDoomedFloors > 0) {
                        throw new \RuntimeException("Cannot remove floor(s) holding {$liveInDoomedFloors} raw material(s). Move them out first.");
                    }
                }
                $doomedSectionIds = $warehouse->sections()->whereNotIn('id', $allActiveSectionIds)->pluck('id');
                if ($doomedSectionIds->isNotEmpty()) {
                    $liveInDoomedSections = WarehouseStockItem::whereIn('section_id', $doomedSectionIds)
                        ->where('quantity', '>', 0)->whereIn('status', ['in_stock', 'reserved'])->count();
                    if ($liveInDoomedSections > 0) {
                        throw new \RuntimeException("Cannot remove sector(s) holding {$liveInDoomedSections} raw material(s). Move them out first.");
                    }
                }

                $warehouse->floors()->whereNotIn('id', $activeFloorIds)->delete();
                $warehouse->sections()->whereNotIn('id', $allActiveSectionIds)->delete();

                // Keep legacy warehouse grid columns in sync (first floor) for old readers
                $first = $warehouse->floors()->orderBy('level')->first();
                if ($first) {
                    $warehouse->update(['grid_rows' => $first->grid_rows, 'grid_cols' => $first->grid_cols]);
                }
            } else {
                // Legacy path: single grid → first floor
                $floor = $warehouse->floors()->orderBy('level')->first();
                if (!$floor) {
                    $floor = $warehouse->floors()->create([
                        'name' => 'Ground Floor', 'level' => 1,
                        'grid_rows' => $data['grid_rows'] ?? 3,
                        'grid_cols' => $data['grid_cols'] ?? 3,
                    ]);
                } else {
                    $floor->update([
                        'grid_rows' => $data['grid_rows'] ?? $floor->grid_rows,
                        'grid_cols' => $data['grid_cols'] ?? $floor->grid_cols,
                    ]);
                }

                $warehouse->update([
                    'grid_rows' => $data['grid_rows'] ?? $warehouse->grid_rows,
                    'grid_cols' => $data['grid_cols'] ?? $warehouse->grid_cols,
                ]);

                $activeSectionIds = [];

                foreach ($data['sections'] ?? [] as $sec) {
                    $sectionId = (isset($sec['id']) && !str_starts_with((string) $sec['id'], 'temp-')) ? $sec['id'] : null;

                    $section = WarehouseSection::updateOrCreate(
                        ['id' => $sectionId, 'warehouse_id' => $warehouse->id],
                        [
                            'floor_id' => $floor->id,
                            'name' => $sec['name'],
                            'grid_row' => $sec['row'],
                            'grid_col' => $sec['col'],
                        ]
                    );
                    $activeSectionIds[] = $section->id;

                    $activeShelfIds = [];
                    if (isset($sec['shelves'])) {
                        foreach ($sec['shelves'] as $sh) {
                            $shId = (isset($sh['id']) && !str_starts_with((string) $sh['id'], 'ts-')) ? $sh['id'] : null;

                            $shelf = WarehouseShelf::updateOrCreate(
                                ['id' => $shId, 'section_id' => $section->id],
                                ['shelf_number' => $sh['shelf_number']]
                            );
                            $activeShelfIds[] = $shelf->id;
                        }
                    }
                    $section->shelves()->whereNotIn('id', $activeShelfIds)->delete();
                }

                $warehouse->sections()->whereNotIn('id', $activeSectionIds)->delete();
            }

            DB::commit();
            return redirect()->back()->with('success', 'Warehouse layout and shelves saved successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Layout error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Creation failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Assign stock to either a specific shelf OR just a section box.
     */
    public function assignToShelf(Request $request)
    {
        $data = $request->validate([
            'stock_item_id' => 'required|exists:warehouse_stock_items,id',
            'shelf_id'      => 'nullable|exists:warehouse_shelves,id',
            'section_id'    => 'nullable|exists:warehouse_sections,id',
        ]);

        $stock = WarehouseStockItem::findOrFail($data['stock_item_id']);
        $user = auth()->user();

        if ($stock->warehouse->supervisor_id !== $user->id && $stock->warehouse->manager_id !== $user->id) {
            abort(403, 'Unauthorized to assign stock.');
        }

        if (!empty($data['shelf_id'])) {
            $shelf = WarehouseShelf::findOrFail($data['shelf_id']);
            $stock->update([
                'shelf_id' => $shelf->id,
                'section_id' => $shelf->section_id
            ]);
        } elseif (!empty($data['section_id'])) {
            $stock->update([
                'section_id' => $data['section_id'],
                'shelf_id' => null
            ]);
        }

        return redirect()->back()->with('success', 'Material location updated.');
    }

    /**
     * Move a live stock item to another shelf / sector (any floor)
     * or to another warehouse. Quantity stays intact — only location changes.
     */
    public function moveStock(Request $request, WarehouseStockItem $stockItem)
    {
        $data = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'section_id' => 'nullable|exists:warehouse_sections,id',
            'shelf_id' => 'nullable|exists:warehouse_shelves,id',
        ]);

        $user = auth()->user();
        $stockItem->load('warehouse');

        // Must supervise the source warehouse
        if ($stockItem->warehouse->supervisor_id !== $user->id && $stockItem->warehouse->manager_id !== $user->id) {
            abort(403, 'Unauthorized to move this stock.');
        }

        if ((float) $stockItem->quantity <= 0 || $stockItem->status === 'used') {
            return redirect()->back()->withErrors(['error' => 'This stock is already depleted and cannot be moved.']);
        }

        $destWarehouse = Warehouse::findOrFail($data['warehouse_id']);

        // Must be allowed to place into the destination warehouse
        if ($destWarehouse->supervisor_id !== $user->id && $destWarehouse->manager_id !== $user->id) {
            abort(403, 'You do not have access to the destination warehouse.');
        }

        // Resolve destination section/shelf and validate they belong together
        $section = null;
        $shelf = null;

        if (!empty($data['shelf_id'])) {
            $shelf = WarehouseShelf::with('section')->findOrFail($data['shelf_id']);
            $section = $shelf->section;
            if ((int) $section->warehouse_id !== (int) $destWarehouse->id) {
                return redirect()->back()->withErrors(['error' => 'Shelf does not belong to the selected warehouse.']);
            }
        } elseif (!empty($data['section_id'])) {
            $section = WarehouseSection::findOrFail($data['section_id']);
            if ((int) $section->warehouse_id !== (int) $destWarehouse->id) {
                return redirect()->back()->withErrors(['error' => 'Sector does not belong to the selected warehouse.']);
            }
        }

        $stockItem->update([
            'warehouse_id' => $destWarehouse->id,
            'section_id' => $section?->id,
            'shelf_id' => $shelf?->id,
        ]);

        $dest = $shelf
            ? "shelf {$shelf->shelf_number} (sector {$section->name})"
            : ($section ? "sector {$section->name}" : 'incoming queue');

        return redirect()->back()->with('success', "{$stockItem->control_number} moved to {$destWarehouse->name} → {$dest}.");
    }

    /**
     * Transfer material to manufacturing production inventory.
     *
     * FIXES APPLIED:
     * 1. Null-safe access to $stockItem->material->category via optional chaining.
     * 2. Wrapped in try/catch so failures surface a real error instead of 500.
     * 3. Explicit load of material relationship before use to avoid lazy-load miss.
     */
    public function useMaterial(Request $request, WarehouseStockItem $stockItem)
    {
        // Eagerly load material to avoid silent null failures
        $stockItem->load('material');

        // Guard: if the material record no longer exists, bail early with a clear message
        if (!$stockItem->material) {
            return redirect()->back()->withErrors([
                'error' => "Material record not found for stock item {$stockItem->control_number}. Cannot transfer.",
            ]);
        }

        $data = $request->validate([
            'quantity' => 'required|numeric|min:0.01|max:' . $stockItem->quantity,
            'manufacturing_department' => 'required|string|in:knitting,dyeing,maintenance,packaging',
        ]);

        $user = auth()->user();
        if ($stockItem->warehouse->supervisor_id !== $user->id && $stockItem->warehouse->manager_id !== $user->id) {
            abort(403, 'Unauthorized.');
        }

        $quantityToTransfer = $data['quantity'];
        $department         = $data['manufacturing_department'];

        // Determine the category value to store.
        // Falls back to a safe default if for any reason it's null.
        $category = $stockItem->material->category ?? 'General';

        try {
            DB::transaction(function () use ($stockItem, $quantityToTransfer, $department, $user, $category) {
                // 1. Create manufacturing inventory record.
                //    control_number is unique per stock item, so the same item can only
                //    be transferred once. Attempting to transfer again (after partial deduction)
                //    uses the same control_number → unique constraint fires.
                //    RESOLUTION: we check if the control_number already exists and increment
                //    the existing record's quantity instead of creating a duplicate.
                $existing = ManufacturingInventoryItem::where('control_number', $stockItem->control_number)->first();

                if ($existing) {
                    // Add to the existing manufacturing inventory record
                    $existing->increment('initial_quantity',   $quantityToTransfer);
                    $existing->increment('remaining_quantity',  $quantityToTransfer);
                    if ($existing->status === 'depleted') {
                        $existing->update(['status' => 'available']);
                    }
                } else {
                    ManufacturingInventoryItem::create([
                        'control_number'          => $stockItem->control_number,
                        'material_id'             => $stockItem->material_id,
                        'warehouse_stock_item_id' => $stockItem->id,
                        'initial_quantity'        => $quantityToTransfer,
                        'remaining_quantity'      => $quantityToTransfer,
                        'unit'                    => $stockItem->unit,
                        'category'                => $category,
                        'status'                  => 'available',
                        'department'              => $department,
                        'received_at'             => now(),
                        'received_from'           => $user->id,
                        'notes'                   => "Transferred from warehouse by {$user->name}",
                    ]);
                }

                // 2. Deduct from warehouse stock.
                if ($quantityToTransfer >= $stockItem->quantity) {
                    $stockItem->update([
                        'status'     => 'used',
                        'quantity'   => 0,
                        'shelf_id'   => null,
                        'section_id' => null,
                    ]);
                } else {
                    $stockItem->decrement('quantity', $quantityToTransfer);
                }
            });
        } catch (\Exception $e) {
            Log::error("useMaterial failed for stock item {$stockItem->id}: " . $e->getMessage());
            return redirect()->back()->withErrors([
                'error' => 'Transfer failed: ' . $e->getMessage(),
            ]);
        }

        return redirect()->back()->with(
            'success',
            "{$quantityToTransfer} {$stockItem->unit} of {$stockItem->material->name} transferred to {$department} department."
        );
    }
}