<?php

namespace App\Http\Controllers\Inv;

use App\Http\Controllers\Controller;
use App\Models\Inv\Product;
use App\Models\Inv\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function product()
    {
        $products = Product::with(['images'])
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
                'colors' => 'nullable|array',
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
                'colors' => 'nullable|array',
                'new_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:51200',
            ]);

            // Update product (keep existing product_id/sku)
            $product->update([
                'name' => $request->name,
                'colors' => $request->colors ?? [],
            ]);

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