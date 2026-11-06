<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loads a traceable opening stock balance for every current material.
 * Each material receives 10,000 kg: 5,000 kg remains unopened in its
 * assigned warehouse lot and 5,000 kg is linked to an opened production lot.
 */
class InventoryOpeningStockSeeder extends Seeder
{
    public function run(): void
    {
        $materials = DB::table('materials')->orderBy('id')->get();
        if ($materials->isEmpty()) {
            throw new RuntimeException('No materials exist; opening stock was not created.');
        }

        $nonKg = $materials->filter(fn ($material) => strtolower(trim((string) $material->unit)) !== 'kg');
        if ($nonKg->isNotEmpty()) {
            throw new RuntimeException('Opening stock requires kg materials. Non-kg material IDs: '.$nonKg->pluck('id')->implode(', '));
        }

        $warehouseNames = array_values(array_unique([
            'KNITTING WAREHOUSE', 'DYEING STUFFS WAREHOUSE', 'BASIC CHEMICALS WAREHOUSE',
            'AUXILIARY AGENT WAREHOUSE', 'BOILER CHEMICALS WAREHOUSE',
            'PRE-TREATMENT WAREHOUSE', 'BIO-PROCESSING WAREHOUSE', 'CARE AGENTS WAREHOUSE',
        ]));
        $warehouses = DB::table('warehouses')->whereIn('name', $warehouseNames)->get()->keyBy('name');
        foreach ($warehouseNames as $name) {
            if (! $warehouses->has($name)) {
                throw new RuntimeException("Required stock warehouse '{$name}' was not found; no stock was created.");
            }
        }

        $receiverId = DB::table('users')
            ->where('role', 'INV')
            ->where('position', 'manager')
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');
        if (! $receiverId) {
            throw new RuntimeException('No active Inventory manager account was found to record the receipts.');
        }

        $batch = 'INV-OPENING-'.now()->format('Ymd').'-ALL-MATERIALS';
        if (DB::table('warehouse_receivings')->where('notes', 'like', "%{$batch}%")->exists()) {
            throw new RuntimeException("Opening stock batch {$batch} already exists; no duplicate stock was created.");
        }

        foreach ($materials as $material) {
            $lot = 'OPN-'.now()->format('Ymd').'-'.$material->mat_id;
            if (DB::table('warehouse_stock_items')->where('control_number', $lot)->exists()
                || DB::table('manufacturing_inventory_items')->where('control_number', $lot)->exists()) {
                throw new RuntimeException("Stock lot {$lot} already exists; no stock was created.");
            }
        }

        $warehouseForCategory = static function (string $category): string {
            return match ($category) {
                'Yarn' => 'KNITTING WAREHOUSE',
                'Dye Stuff', 'Post-Treatment' => 'DYEING STUFFS WAREHOUSE',
                'Basic Chemicals' => 'BASIC CHEMICALS WAREHOUSE',
                'Auxillary Agent' => 'AUXILIARY AGENT WAREHOUSE',
                'Boiler Chemicals' => 'BOILER CHEMICALS WAREHOUSE',
                'Pre-Treatment' => 'PRE-TREATMENT WAREHOUSE',
                'Bio-Processing Enzymes' => 'BIO-PROCESSING WAREHOUSE',
                'Care Agents' => 'CARE AGENTS WAREHOUSE',
                default => throw new RuntimeException("No warehouse mapping for material category '{$category}'."),
            };
        };
        $departmentForCategory = static function (string $category): string {
            if ($category === 'Yarn') return 'knitting';
            if ($category === 'Boiler Chemicals') return 'boiler';
            return 'dyeing';
        };

        DB::transaction(function () use ($materials, $warehouses, $receiverId, $batch, $warehouseForCategory, $departmentForCategory): void {
            $now = now();
            $itemsByWarehouse = $materials->groupBy(fn ($material) => $warehouseForCategory((string) $material->category));

            foreach ($itemsByWarehouse as $warehouseName => $warehouseMaterials) {
                $warehouse = $warehouses[$warehouseName];
                $receivingId = DB::table('warehouse_receivings')->insertGetId([
                    'receiving_number' => 'RCV-OP-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(3))),
                    'warehouse_id' => $warehouse->id,
                    'scm_purchase_order_id' => null,
                    'received_at' => $now,
                    'received_by' => $receiverId,
                    'status' => 'completed',
                    'notes' => "{$batch}; manual opening balance; 10,000 Kg received per material, with 5,000 Kg retained unopened and 5,000 Kg released to production.",
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($warehouseMaterials as $material) {
                    $lot = 'OPN-'.now()->format('Ymd').'-'.$material->mat_id;
                    $totalReceived = 10000;
                    $productionQty = 5000;
                    $warehouseQty = 5000;
                    $department = $departmentForCategory((string) $material->category);
                    $productionCategory = $material->category === 'Dye Stuff' ? 'Dye' : $material->category;
                    $yarnUnits = $material->category === 'Yarn' ? (int) ($productionQty / 2) : null;

                    DB::table('warehouse_receiving_items')->insert([
                        'receiving_id' => $receivingId,
                        'material_id' => $material->id,
                        'expected_qty' => $totalReceived,
                        'received_qty' => $totalReceived,
                        'rejected_qty' => 0,
                        'status' => 'accepted',
                        'reject_reason' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $stockId = DB::table('warehouse_stock_items')->insertGetId([
                        'control_number' => $lot,
                        'warehouse_id' => $warehouse->id,
                        'section_id' => null,
                        'shelf_id' => null,
                        'material_id' => $material->id,
                        'quantity' => $warehouseQty,
                        'unit' => 'Kg',
                        'received_at' => $now,
                        'received_by' => $receiverId,
                        'purchase_order_id' => null,
                        'status' => 'in_stock',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('manufacturing_inventory_items')->insert([
                        'control_number' => $lot,
                        'material_id' => $material->id,
                        'warehouse_stock_item_id' => $stockId,
                        'initial_quantity' => $productionQty,
                        'remaining_quantity' => $productionQty,
                        'unit' => 'Kg',
                        'category' => $productionCategory,
                        'status' => 'available',
                        'total_units' => $yarnUnits,
                        'used_units' => 0,
                        'unit_type' => $yarnUnits ? 'roll' : null,
                        'unit_weight' => $yarnUnits ? 2 : null,
                        'department' => $department,
                        'received_at' => $now,
                        'received_from' => $receiverId,
                        'notes' => "{$batch}; 5,000 Kg opened into {$department} production from the same receiving lot; 5,000 Kg remains unopened in {$warehouseName}.",
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });

        $this->command?->info("Opening stock batch {$batch} recorded for {$materials->count()} materials (5,000 Kg warehouse + 5,000 Kg production per material).");
    }
}
