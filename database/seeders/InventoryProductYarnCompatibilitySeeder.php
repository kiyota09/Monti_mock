<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventoryProductYarnCompatibilitySeeder extends Seeder
{
    public function run(): void
    {
        $productIds = DB::table('products')->orderBy('id')->pluck('id');
        $yarnIds = DB::table('materials')
            ->where('category', 'Yarn')
            ->orderBy('id')
            ->pluck('id');

        if ($productIds->isEmpty() || $yarnIds->isEmpty()) {
            $this->command?->warn('No product/yarn compatibility links were seeded: products and Yarn materials must exist.');

            return;
        }

        $rows = [];
        foreach ($productIds as $productId) {
            foreach ($yarnIds as $yarnId) {
                $rows[] = [
                    'product_id' => $productId,
                    'material_id' => $yarnId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('product_yarns')->insertOrIgnore($chunk);
        }

        $this->command?->info('Linked '.$productIds->count().' products to all '.$yarnIds->count().' available Yarn materials.');
    }
}
