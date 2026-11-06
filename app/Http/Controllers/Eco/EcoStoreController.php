<?php

namespace App\Http\Controllers\Eco;

use App\Http\Controllers\Controller;
use App\Models\Inv\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EcoStoreController extends Controller
{
    public function index()
    {
        // Load only active products with their images
        $products = Product::where('status', 'Active')
            ->with(['yarnMaterials', 'images' => function ($query) {
                // Order by ID since sort_order is gone
                $query->orderBy('id', 'asc');
            }])
            ->get()
            ->map(function ($product) {

                // FLATTEN IMAGES: Convert to a simple array of URL strings.
                // Handles string URLs, /storage/... paths, storage/... paths,
                // and absolute http(s) URLs so the carousel never breaks.
                $images = $product->images->map(function ($image) {
                    $rawPath = $image->url ?? $image->path ?? '';
                    if (empty($rawPath)) {
                        return null;
                    }
                    if (str_starts_with($rawPath, 'http://') || str_starts_with($rawPath, 'https://')) {
                        return $rawPath;
                    }
                    // Normalize to a single storage-relative path, then asset URL.
                    $cleanPath = ltrim(str_replace('storage/', '', $rawPath), '/');
                    if ($cleanPath === '') {
                        return null;
                    }
                    return asset('storage/' . $cleanPath);
                })->filter()->values()->toArray();

                return [
                    'id' => $product->id,
                    'product_id' => $product->product_id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category' => $product->category,
                    'subcategory' => $product->subcategory,
                    'description' => $product->description,
                    'colors' => $product->colors ?? [],
                    'colorName' => $product->color_name,
                    'colorHex' => $product->color_hex,
                    'yarnMaterials' => $product->yarnMaterials->map(fn ($material) => [
                        'id' => $material->id,
                        'mat_id' => $material->mat_id,
                        'name' => $material->name,
                        'display_name' => $material->display_name,
                        'yarn_tier' => $material->yarn_tier,
                        'unit' => $material->unit,
                    ])->values()->toArray(),
                    'unit_cost' => $product->unit_cost, // Needed for Margin calculation in Vue
                    'selling_price' => $product->selling_price,
                    'stock_on_hand' => $product->stock_on_hand,
                    'moq' => $product->moq,
                    'weight' => $product->weight,      // Added for UI icons
                    'dimensions' => $product->dimensions, // Added for UI icons
                    'images' => $images, // Now this is ['url1', 'url2'] - Vue will love this
                ];
            });

        return Inertia::render('Dashboard/ECO/Store', [
            'products' => $products,
        ]);
    }
}
