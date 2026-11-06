<?php

namespace Database\Seeders;

use App\Models\Eco\EcoProductVariantPrice;
use App\Models\Inv\Material;
use App\Models\Inv\Product;
use Illuminate\Database\Seeder;

class EcoProductVariantPricingDemoSeeder extends Seeder
{
    private const RANGES = [
        'white' => [200, 250],
        'light' => [250, 300],
        'dark' => [300, 350],
    ];

    public function run(): void
    {
        $products = Product::query()->orderBy('id')->get(['id']);
        $yarns = Material::query()
            ->where('category', 'Yarn')
            ->orderBy('id')
            ->get(['id']);

        if ($products->isEmpty() || $yarns->isEmpty()) {
            $this->command?->warn('No demo prices were seeded: add at least one product and one Inventory material in the Yarn category.');

            return;
        }

        $count = 0;
        foreach ($products as $product) {
            foreach ($yarns as $yarn) {
                foreach (self::RANGES as $colorGroup => [$minimum, $maximum]) {
                    // Stable sample values across reruns, varied by product/yarn/color.
                    $offset = ($product->id * 7 + $yarn->id * 3 + strlen($colorGroup) * 5) % ($maximum - $minimum + 1);

                    EcoProductVariantPrice::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'material_id' => $yarn->id,
                            'color_group' => $colorGroup,
                        ],
                        ['price' => $minimum + $offset]
                    );
                    $count++;
                }
            }
        }

        $this->command?->info("Seeded {$count} demo product/yarn color prices (white 200–250, light 250–300, dark 300–350). Existing matching demo prices were refreshed.");
    }
}
