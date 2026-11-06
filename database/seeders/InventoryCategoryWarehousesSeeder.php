<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Creates category warehouses, assigns their supervisor and places active stock by material category. */
class InventoryCategoryWarehousesSeeder extends Seeder
{
    private const NEW_WAREHOUSES = [
        'AUXILIARY AGENT WAREHOUSE' => ['location' => 'South Monti', 'color' => 'cyan'],
        'BOILER CHEMICALS WAREHOUSE' => ['location' => 'North Monti', 'color' => 'cyan'],
        'PRE-TREATMENT WAREHOUSE' => ['location' => 'South Monti', 'color' => 'amber'],
        'BIO-PROCESSING WAREHOUSE' => ['location' => 'South Monti', 'color' => 'emerald'],
        'CARE AGENTS WAREHOUSE' => ['location' => 'South Monti', 'color' => 'violet'],
    ];

    private const CATEGORY_WAREHOUSES = [
        'Yarn' => 'KNITTING WAREHOUSE',
        'Dye Stuff' => 'DYEING STUFFS WAREHOUSE',
        'Basic Chemicals' => 'BASIC CHEMICALS WAREHOUSE',
        'Auxillary Agent' => 'AUXILIARY AGENT WAREHOUSE',
        'Boiler Chemicals' => 'BOILER CHEMICALS WAREHOUSE',
        'Post-Treatment' => 'DYEING STUFFS WAREHOUSE',
        'Pre-Treatment' => 'PRE-TREATMENT WAREHOUSE',
        'Bio-Processing Enzymes' => 'BIO-PROCESSING WAREHOUSE',
        'Care Agents' => 'CARE AGENTS WAREHOUSE',
    ];

    public function run(): void
    {
        $supervisor = DB::table('users')
            ->where('name', 'Chuwi Cholo')
            ->where('is_active', true)
            ->first(['id']);
        if (! $supervisor) {
            throw new RuntimeException('Active user Chuwi Cholo was not found; warehouses were not changed.');
        }
        if (! DB::table('user_module_access')->where('user_id', $supervisor->id)->where('module', 'WAR')->exists()) {
            throw new RuntimeException('Chuwi Cholo does not have Warehouse module access; warehouses were not changed.');
        }

        $unknownCategories = DB::table('materials')->distinct()->pluck('category')
            ->filter(fn ($category) => ! isset(self::CATEGORY_WAREHOUSES[$category]));
        if ($unknownCategories->isNotEmpty()) {
            throw new RuntimeException('No warehouse mapping exists for: '.$unknownCategories->implode(', '));
        }

        $existingDestinations = ['KNITTING WAREHOUSE', 'DYEING STUFFS WAREHOUSE', 'BASIC CHEMICALS WAREHOUSE'];
        foreach ($existingDestinations as $name) {
            if (! DB::table('warehouses')->where('name', $name)->exists()) {
                throw new RuntimeException("Required existing warehouse '{$name}' was not found; no stock was moved.");
            }
        }

        DB::transaction(function () use ($supervisor): void {
            $now = now();
            foreach (self::NEW_WAREHOUSES as $name => $settings) {
                $warehouseId = DB::table('warehouses')->where('name', $name)->value('id');
                $values = [
                    'location' => $settings['location'],
                    'supervisor_id' => $supervisor->id,
                    'color' => $settings['color'],
                    'updated_at' => $now,
                ];
                if ($warehouseId) {
                    DB::table('warehouses')->where('id', $warehouseId)->update($values);
                } else {
                    DB::table('warehouses')->insert([...$values, 'name' => $name, 'created_at' => $now]);
                }
            }

            $warehouseIds = DB::table('warehouses')
                ->whereIn('name', array_values(self::CATEGORY_WAREHOUSES))
                ->pluck('id', 'name');
            foreach (array_values(self::CATEGORY_WAREHOUSES) as $name) {
                if (! $warehouseIds->has($name)) {
                    throw new RuntimeException("Destination warehouse '{$name}' could not be resolved.");
                }
            }

            $sections = [];
            $sectionWarehouseNames = array_values(array_unique([
                ...array_keys(self::NEW_WAREHOUSES),
                'BASIC CHEMICALS WAREHOUSE',
            ]));
            foreach ($sectionWarehouseNames as $name) {
                $warehouseId = (int) $warehouseIds[$name];
                $floorId = DB::table('warehouse_floors')
                    ->where('warehouse_id', $warehouseId)
                    ->where('level', 1)
                    ->value('id');
                if (! $floorId) {
                    $floorId = DB::table('warehouse_floors')->insertGetId([
                        'warehouse_id' => $warehouseId,
                        'name' => 'Ground Floor',
                        'level' => 1,
                        'grid_rows' => 3,
                        'grid_cols' => 3,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $sectionId = DB::table('warehouse_sections')
                    ->where('warehouse_id', $warehouseId)
                    ->where('name', 'MAIN STORAGE')
                    ->value('id');
                if (! $sectionId) {
                    $sectionId = DB::table('warehouse_sections')->insertGetId([
                        'warehouse_id' => $warehouseId,
                        'floor_id' => $floorId,
                        'name' => 'MAIN STORAGE',
                        'grid_row' => 1,
                        'grid_col' => 1,
                        'capacity' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $sections[$name] = (int) $sectionId;
            }

            $movedLots = 0;
            foreach (self::CATEGORY_WAREHOUSES as $category => $warehouseName) {
                $destinationId = (int) $warehouseIds[$warehouseName];
                $sectionId = $sections[$warehouseName] ?? null;
                $query = DB::table('warehouse_stock_items as stock')
                    ->join('materials', 'materials.id', '=', 'stock.material_id')
                    ->where('materials.category', $category)
                    ->where('stock.quantity', '>', 0)
                    ->whereIn('stock.status', ['in_stock', 'reserved'])
                    ->where('stock.warehouse_id', '!=', $destinationId);

                $movedLots += $query->count();
                $query->update([
                    'stock.warehouse_id' => $destinationId,
                    'stock.section_id' => $sectionId,
                    'stock.shelf_id' => null,
                    'stock.updated_at' => $now,
                ]);
            }

            foreach (self::NEW_WAREHOUSES as $warehouseName => $_settings) {
                DB::table('warehouses')->where('name', $warehouseName)->update(['supervisor_id' => $supervisor->id]);
            }

            $openingLots = DB::table('manufacturing_inventory_items as inventory')
                ->join('materials', 'materials.id', '=', 'inventory.material_id')
                ->where('inventory.control_number', 'like', 'OPN-20261104-MAT-%')
                ->select('inventory.id', 'materials.category')
                ->get();
            foreach ($openingLots as $lot) {
                $warehouseName = self::CATEGORY_WAREHOUSES[$lot->category];
                DB::table('manufacturing_inventory_items')->where('id', $lot->id)->update([
                    'notes' => "INV-OPENING-20261104-ALL-MATERIALS; 5,000 Kg opened into production; 5,000 Kg unopened stock is stored in {$warehouseName}.",
                    'updated_at' => $now,
                ]);
            }

            $this->command?->info('Assigned Chuwi Cholo to five category warehouses; moved '.$movedLots.' active stock lot(s) to the warehouses matching their material categories.');
        });
    }
}
