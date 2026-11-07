<?php

namespace App\Http\Controllers\Inv;

use App\Http\Controllers\Controller;
use App\Models\Inv\Product;
use App\Models\Inv\ProductImage;
use App\Models\Inv\Material;
use App\Models\Man\PantoneReference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function product()
    {
        $yarnMaterials = Material::query()
            ->where('category', 'Yarn')
            ->orderBy('yarn_tier')
            ->orderBy('name')
            ->get(['id', 'mat_id', 'name', 'category', 'yarn_tier', 'unit'])
            ->map(fn (Material $material) => [
                'id' => $material->id,
                'mat_id' => $material->mat_id,
                'name' => $material->name,
                'display_name' => $material->display_name,
                'yarn_tier' => $material->yarn_tier,
                'unit' => $material->unit,
            ])
            ->values()
            ->toArray();

        $pantoneColors = PantoneReference::query()
            ->orderBy('name')
            ->get(['id', 'name', 'hex'])
            ->map(fn (PantoneReference $pantone) => [
                'id' => $pantone->id,
                'name' => $pantone->name,
                'hex' => $pantone->hex,
            ])
            ->values()
            ->toArray();

        // Bulk inquiries are conversation records, not catalog products.
        // Exclude legacy placeholder rows while retaining them for older
        // inquiries that may still reference their product IDs.
        $products = Product::query()
            ->whereRaw('LOWER(TRIM(name)) <> ?', ['bulk inquiry'])
            ->whereRaw('UPPER(TRIM(COALESCE(sku, ?))) <> ?', ['', 'BULK-SKU'])
            ->where(fn ($query) => $query->whereNull('product_id')->orWhere('product_id', 'not like', 'BULK-%'))
            ->with(['images', 'yarnMaterials'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (Product $p) {
                $colorsArray = [];
                if (!empty($p->colors)) {
                    $colorsArray = is_string($p->colors) ? json_decode($p->colors, true) : $p->colors;
                }

                return [
                    'id' => $p->id,
                    'product_id' => $p->product_id,
                    'sku' => $p->sku,
                    'name' => $p->name,
                    'category' => $p->category,
                    'status' => $p->status,
                    'colors' => $colorsArray,
                    'colorName' => $p->color_name ?? null,
                    'colorHex' => $p->color_hex ?? null,
                    'yarnMaterials' => $p->yarnMaterials->map(fn (Material $material) => [
                        'id' => $material->id,
                        'mat_id' => $material->mat_id,
                        'name' => $material->name,
                        'display_name' => $material->display_name,
                        'yarn_tier' => $material->yarn_tier,
                        'unit' => $material->unit,
                    ])->values()->toArray(),
                    'images' => $p->images->map(fn($img) => [
                        'id' => $img->id,
                        'url' => $img->url,
                    ])->toArray(),
                ];
            })
            ->values()
            ->toArray();

        return Inertia::render('Dashboard/Inventory/Products', [
            'products' => $products,
            'yarnMaterials' => $yarnMaterials,
            'pantoneColors' => $pantoneColors,
        ]);
    }

    /**
     * Generate a unique product_id and SKU with retry logic
     */
    private function generateUniqueIdentifiers($name)
    {
        $maxAttempts = 10;
        $attempt = 0;

        do {
            $productId = 'PRD-' . strtoupper(Str::random(6));
            $skuBase = strtoupper(substr(Str::slug($name), 0, 3));
            $sku = $skuBase . '-' . strtoupper(Str::random(4));

            $exists = Product::where('product_id', $productId)
                ->orWhere('sku', $sku)
                ->exists();

            if (!$exists) {
                return ['product_id' => $productId, 'sku' => $sku];
            }

            $attempt++;
            if ($attempt >= $maxAttempts) {
                throw new \Exception('Unable to generate unique product_id/sku after ' . $maxAttempts . ' attempts.');
            }
        } while (true);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'colors' => 'required|array|min:10',
                'colors.*.name' => 'required|string|max:80',
                'colors.*.hex' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'colors.*.pantone_id' => 'nullable|integer|exists:pantone_references,id',
                'yarn_material_ids' => 'nullable|array',
                'yarn_material_ids.*' => ['integer', 'distinct', Rule::exists('materials', 'id')->where(fn ($query) => $query->where('category', 'Yarn'))],
                'category' => 'nullable|string',
                'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
            ]);

            // Generate unique product_id and SKU
            $identifiers = $this->generateUniqueIdentifiers($request->name);

            // Create Product
            $product = Product::create([
                'name' => $request->name,
                'colors' => $request->colors ?? [],
                'category' => $request->category ?? 'Uncategorized',
                'status' => 'Active',
                'product_id' => $identifiers['product_id'],
                'sku' => $identifiers['sku'],
            ]);
            $product->yarnMaterials()->sync($request->input('yarn_material_ids', []));

            // Handle images with duplicate prevention
            if ($request->hasFile('images')) {
                $storedPaths = [];
                foreach ($request->file('images') as $image) {
                    $path = $image->store('products', 'public');
                    if (!in_array($path, $storedPaths)) {
                        $storedPaths[] = $path;
                        $product->images()->create(['url' => '/storage/' . $path]);
                    }
                }
            }

            return back()->with('success', 'Product created successfully!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return back()->withErrors(['error' => 'Duplicate product ID or SKU generated. Please try again.']);
            }
            \Log::error('Product store DB error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'A database error occurred. Please try again.']);
        } catch (\Exception $e) {
            \Log::error('Product store general error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to save product. Please try again.']);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255',
                'colors' => 'required|array|min:10',
                'colors.*.name' => 'required|string|max:80',
                'colors.*.hex' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'colors.*.pantone_id' => 'nullable|integer|exists:pantone_references,id',
                'yarn_material_ids' => 'nullable|array',
                'yarn_material_ids.*' => ['integer', 'distinct', Rule::exists('materials', 'id')->where(fn ($query) => $query->where('category', 'Yarn'))],
                'new_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:51200',
            ]);

            // Update product (keep existing product_id/sku)
            $product->update([
                'name' => $request->name,
                'colors' => $request->colors ?? [],
            ]);
            $product->yarnMaterials()->sync($request->input('yarn_material_ids', []));

            // Handle newly added images
            if ($request->hasFile('new_images')) {
                $storedPaths = [];
                foreach ($request->file('new_images') as $image) {
                    $path = $image->store('products', 'public');
                    if (!in_array($path, $storedPaths)) {
                        $storedPaths[] = $path;
                        $product->images()->create(['url' => '/storage/' . $path]);
                    }
                }
            }

            return back()->with('success', 'Product updated successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Product update error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update product: ' . $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);

            foreach ($product->images as $image) {
                $storagePath = str_replace('/storage/', '', $image->url);
                Storage::disk('public')->delete($storagePath);
            }

            $product->delete();

            return back()->with('success', 'Product deleted.');
        } catch (\Exception $e) {
            \Log::error('Product delete error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to delete product: ' . $e->getMessage()]);
        }
    }

    public function destroyImage($imageId)
    {
        try {
            $image = ProductImage::findOrFail($imageId);
            $storagePath = str_replace('/storage/', '', $image->url);
            Storage::disk('public')->delete($storagePath);
            $image->delete();

            return back()->with('success', 'Image removed.');
        } catch (\Exception $e) {
            \Log::error('Image delete error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to delete image: ' . $e->getMessage()]);
        }
    }
}
