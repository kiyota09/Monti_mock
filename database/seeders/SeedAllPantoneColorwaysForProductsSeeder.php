<?php

namespace Database\Seeders;

use App\Models\Inv\Product;
use App\Models\Man\PantoneReference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SeedAllPantoneColorwaysForProductsSeeder extends Seeder
{
    public function run(): void
    {
        $pantones = PantoneReference::query()
            ->orderBy('name')
            ->get(['id', 'name', 'hex']);

        if ($pantones->isEmpty()) {
            throw new RuntimeException('Cannot seed product colorways because the Pantone library is empty.');
        }

        $colors = $pantones->map(fn (PantoneReference $pantone) => [
            'pantone_id' => $pantone->id,
            'name' => $pantone->name,
            'hex' => $pantone->hex,
        ])->values()->all();

        $products = Product::query()->orderBy('id')->get();
        DB::transaction(function () use ($products, $colors) {
            foreach ($products as $product) {
                $product->colors = $colors;
                $product->save();
            }
        });

        $this->command?->info('Seeded all '.$pantones->count().' Pantone colorways onto '.$products->count().' products ('.$pantones->count() * $products->count().' product colorway entries).');
    }
}
