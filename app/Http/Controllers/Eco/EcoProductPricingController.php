<?php

namespace App\Http\Controllers\Eco;

use App\Http\Controllers\Controller;
use App\Models\Inv\Material;
use App\Models\Inv\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EcoProductPricingController extends Controller
{
    private const COLOR_GROUPS = ['white', 'light', 'dark'];

    public function index()
    {
        $yarns = Material::where('category', 'Yarn')
            ->orderBy('name')
            ->orderBy('yarn_tier')
            ->get(['id', 'mat_id', 'name', 'yarn_tier'])
            ->map(fn (Material $material) => [
                'id' => $material->id,
                'mat_id' => $material->mat_id,
                'name' => $material->display_name,
                'yarn_tier' => $material->yarn_tier,
            ])
            ->values();

        $products = Product::with('ecoVariantPrices')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'product_id' => $product->product_id,
                'sku' => $product->sku,
                'name' => $product->name,
                'category' => $product->category,
                'status' => $product->status,
                'minimum_order_quantity' => $product->minimum_order_quantity,
                'prices' => $product->ecoVariantPrices->map(fn ($price) => [
                    'material_id' => $price->material_id,
                    'color_group' => $price->color_group,
                    'price' => $price->price,
                ])->values(),
            ])->values();

        return Inertia::render('Dashboard/ECO/Pricing', [
            'products' => $products,
            'yarns' => $yarns,
            'colorGroups' => [
                ['key' => 'white', 'label' => 'White'],
                ['key' => 'light', 'label' => 'Light Colors'],
                ['key' => 'dark', 'label' => 'Dark Colors'],
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'prices' => ['required', 'array'],
            'prices.*.material_id' => ['required', 'integer'],
            'prices.*.color_group' => ['required', Rule::in(self::COLOR_GROUPS)],
            'prices.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        $product = Product::findOrFail($data['product_id']);

        $rows = collect($data['prices']);
        $keys = $rows->map(fn ($row) => (int) $row['material_id'].'|'.$row['color_group']);
        if ($keys->unique()->count() !== $keys->count()) {
            return back()->withErrors(['prices' => 'A yarn and color combination can only appear once.']);
        }

        $requestedYarnIds = $rows->pluck('material_id')->map(fn ($id) => (int) $id)->unique();
        $validYarnIds = Material::where('category', 'Yarn')
            ->whereIn('id', $requestedYarnIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
        if ($validYarnIds->count() !== $requestedYarnIds->count()) {
            return back()->withErrors(['prices' => 'Choose yarn materials from Inventory.']);
        }

        DB::transaction(function () use ($product, $rows) {
            foreach ($rows as $row) {
                $product->ecoVariantPrices()->updateOrCreate(
                    [
                        'material_id' => (int) $row['material_id'],
                        'color_group' => $row['color_group'],
                    ],
                    ['price' => $row['price'] === '' ? null : ($row['price'] ?? null)]
                );
            }
        });

        return back()->with('success', 'Product variant pricing saved.');
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'minimum_order_quantity' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
        ]);

        // MOQ is a shared quotation default: every product, color, and yarn
        // combination uses the same saved minimum.
        Product::query()->update(['minimum_order_quantity' => $data['minimum_order_quantity']]);

        return back()->with('success', 'Minimum order quantity saved for all products.');
    }
}
