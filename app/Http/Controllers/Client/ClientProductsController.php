<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Inv\Product;
use App\Models\Man\PantoneReference;
use App\Models\Eco\Inquiry;
use App\Models\Eco\ConversationMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClientProductsController extends Controller
{
    public function index()
    {
        $pantoneReferences = PantoneReference::query()->get(['id', 'color_category'])->keyBy('id');
        $products = Product::where('status', 'Active')
            ->with(['images', 'yarnMaterials'])
            ->get()
            ->map(function ($p) use ($pantoneReferences) {
                // Safely parse the colors array
                $colorsArray = [];
                if (!empty($p->colors)) {
                    $colorsArray = is_string($p->colors) ? json_decode($p->colors, true) : $p->colors;
                }
                $colorsArray = collect($colorsArray)->map(function ($color) use ($pantoneReferences) {
                    if (! is_array($color) || empty($color['pantone_id'])) return $color;
                    $color['color_category'] = $pantoneReferences->get((int) $color['pantone_id'])?->color_category;
                    return $color;
                })->values()->all();

                return [
                    'id' => $p->id,
                    'product_id' => $p->product_id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'category' => $p->category ?? 'Uncategorized',
                    'colors' => $colorsArray,
                    'colorName' => $p->color_name ?? null,
                    'colorHex' => $p->color_hex ?? null,
                    'yarnMaterials' => $p->yarnMaterials->map(fn ($material) => [
                        'id' => $material->id,
                        'mat_id' => $material->mat_id,
                        'name' => $material->name,
                        'display_name' => $material->display_name,
                        'yarn_tier' => $material->yarn_tier,
                        'unit' => $material->unit,
                    ])->values()->toArray(),
                    'images' => $p->images->map(function ($img) {
                        $raw = $img->url ?? '';
                        if (empty($raw)) {
                            return null;
                        }
                        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
                            $url = $raw;
                        } else {
                            // Normalize: '/storage/x' stays root-relative; bare
                            // 'products/x' becomes '/storage/products/x' so the
                            // <img> tag resolves regardless of stored format.
                            $clean = ltrim(str_replace('storage/', '', $raw), '/');
                            $url = $clean === '' ? null : '/storage/' . $clean;
                        }
                        return $url ? ['id' => $img->id, 'url' => $url] : null;
                    })->filter()->values()->toArray(),
                    'stock_on_hand' => $p->stock_on_hand ?? 0,
                ];
            });

        return Inertia::render('Client/Products', ['products' => $products]);
    }

    public function inquire(Request $request, Product $product)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'pantone_ids' => 'required|array|min:1',
            'pantone_ids.*' => 'required|integer|distinct|exists:pantone_references,id',
            'yarn_material_ids' => 'required|array|min:1',
            'yarn_material_ids.*' => ['required', 'integer', 'distinct', \Illuminate\Validation\Rule::exists('materials', 'id')->where(fn ($query) => $query->where('category', 'Yarn'))],
            'gsm' => 'nullable|integer|min:1|max:10000',
            'texture' => 'nullable|in:Hard,Medium,Soft',
        ]);

        $availablePantoneIds = collect($product->colors ?? [])
            ->pluck('pantone_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();
        $selectedPantoneIds = collect($validated['pantone_ids'])->map(fn ($id) => (int) $id);
        if ($selectedPantoneIds->diff($availablePantoneIds)->isNotEmpty()) {
            return back()->withErrors(['pantone_ids' => 'Choose Pantone colors available for this product.']);
        }

        $availableYarnIds = $product->yarnMaterials()->pluck('materials.id')->map(fn ($id) => (int) $id);
        $selectedYarnIds = collect($validated['yarn_material_ids'])->map(fn ($id) => (int) $id);
        if ($selectedYarnIds->diff($availableYarnIds)->isNotEmpty()) {
            return back()->withErrors(['yarn_material_ids' => 'Choose yarn types available for this product.']);
        }

        $pantonesById = PantoneReference::query()
            ->whereIn('id', $selectedPantoneIds)
            ->get(['id', 'name', 'hex', 'color_category'])
            ->keyBy('id');
        $yarnsById = $product->yarnMaterials()
            ->whereIn('materials.id', $selectedYarnIds)
            ->get(['materials.id', 'materials.mat_id', 'materials.name', 'materials.yarn_tier'])
            ->keyBy('id');

        $productSelections = [
            'product_id' => (int) $product->id,
            'gsm' => $validated['gsm'] ?? null,
            'texture' => $validated['texture'] ?? null,
            'pantones' => $selectedPantoneIds->map(fn ($id) => $pantonesById->get($id))
                ->filter()
                ->map(fn (PantoneReference $pantone) => [
                    'id' => (int) $pantone->id,
                    'name' => $pantone->name,
                    'hex' => $pantone->hex,
                    'color_category' => $pantone->color_category,
                ])->values()->all(),
            'yarns' => $selectedYarnIds->map(fn ($id) => $yarnsById->get($id))
                ->filter()
                ->map(fn ($yarn) => [
                    'id' => (int) $yarn->id,
                    'name' => $yarn->display_name,
                    'base_name' => $yarn->name,
                    'mat_id' => $yarn->mat_id,
                    'yarn_tier' => $yarn->yarn_tier,
                ])->values()->all(),
        ];

        $client = Auth::guard('client')->user();

        $inquiry = Inquiry::create([
            'client_id' => $client->id,
            'product_id' => $product->id,
            'initial_message' => $validated['message'],
            'product_selections' => $productSelections,
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        ConversationMessage::create([
            'inquiry_id' => $inquiry->id,
            'sender_type' => 'client',
            'message' => $request->message,
        ]);

        return redirect()->route('client.products')
            ->with('success', 'Inquiry sent. You can now continue the conversation.')
            ->with('inquiry_redirect_url', route('client.conversation.show', $inquiry));
    }

    /**
     * Handle bulk inquiry for multiple products.
     * Builds a full, detailed initial message so the ECO team
     * sees every product's name, SKU, category, and available colors.
     */
    public function bulkInquire(Request $request)
    {
        $request->validate([
            'product_ids'   => 'required|array|min:1',
            'product_ids.*' => 'exists:products,id',
            'message'       => 'nullable|string|max:1000',
        ]);

        $client   = Auth::guard('client')->user();
        $products = Product::whereIn('id', $request->product_ids)->get();

        // Create or retrieve a sentinel product for bulk inquiries
        $bulkProduct = Product::firstOrCreate(
            ['name' => 'Bulk Inquiry'],
            [
                'product_id' => 'BULK-' . strtoupper(uniqid()),
                'sku'        => 'BULK-SKU',
                'status'     => 'Active',
                'category'   => 'Bulk',
            ]
        );

        // ── Build a detailed, human-readable product list ──────────────────
        $productLines = $products->map(function ($p) {
            // Resolve colors from JSON or scalar fields
            $colors     = is_string($p->colors) ? json_decode($p->colors, true) : ($p->colors ?? []);
            $colorNames = '';

            if (is_array($colors) && count($colors) > 0) {
                $names      = array_values(array_filter(array_column($colors, 'name')));
                $colorNames = implode(', ', $names);
            } elseif (!empty($p->color_name)) {
                $colorNames = $p->color_name;
            }

            $line = "• {$p->name}";
            $line .= " | SKU: {$p->sku}";

            if ($p->category && $p->category !== 'Uncategorized') {
                $line .= " | Category: {$p->category}";
            }

            if ($colorNames) {
                $line .= "\n  Available Colors: {$colorNames}";
            }

            return $line;
        })->implode("\n\n");

        $count  = $products->count();
        $header = "📦 BULK PRODUCT INQUIRY — {$count} Product(s)";
        $footer = "Please provide pricing, availability, and estimated delivery timeline for the above products.";

        // Compose the full initial message
        $initialMessage = "{$header}\n\n{$productLines}";

        // Append the client's optional personal note (if any)
        if (!empty($request->message)) {
            $initialMessage .= "\n\n💬 Additional Note:\n{$request->message}";
        }

        $initialMessage .= "\n\n{$footer}";
        // ───────────────────────────────────────────────────────────────────

        $inquiry = Inquiry::create([
            'client_id'       => $client->id,
            'product_id'      => $bulkProduct->id,
            'initial_message' => $initialMessage,
            'status'          => 'open',
            'last_message_at' => now(),
        ]);

        ConversationMessage::create([
            'inquiry_id'  => $inquiry->id,
            'sender_type' => 'client',
            'message'     => $initialMessage,
        ]);

        return redirect()->route('client.products')
            ->with('success', 'Bulk inquiry sent. Our team will contact you shortly.')
            ->with('inquiry_redirect_url', route('client.conversation.show', $inquiry));
    }
}
