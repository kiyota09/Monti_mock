<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Inv\Product;
use App\Models\Eco\Inquiry;
use App\Models\Eco\ConversationMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClientProductsController extends Controller
{
    public function index()
    {
        $products = Product::where('status', 'Active')
            ->with('images')
            ->get()
            ->map(function ($p) {
                // Safely parse the colors array
                $colorsArray = [];
                if (!empty($p->colors)) {
                    $colorsArray = is_string($p->colors) ? json_decode($p->colors, true) : $p->colors;
                }

                return [
                    'id' => $p->id,
                    'product_id' => $p->product_id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'category' => $p->category ?? 'Uncategorized',
                    'colors' => $colorsArray,
                    'colorName' => $p->color_name ?? null,
                    'colorHex' => $p->color_hex ?? null,
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
        $request->validate(['message' => 'required|string']);

        $client = Auth::guard('client')->user();

        $inquiry = Inquiry::create([
            'client_id' => $client->id,
            'product_id' => $product->id,
            'initial_message' => $request->message,
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        ConversationMessage::create([
            'inquiry_id' => $inquiry->id,
            'sender_type' => 'client',
            'message' => $request->message,
        ]);

        return redirect()->route('client.conversation.show', $inquiry)
            ->with('success', 'Inquiry sent. You can now continue the conversation.');
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

        return redirect()->route('client.conversation.show', $inquiry)
            ->with('success', 'Bulk inquiry sent. Our team will contact you shortly.');
    }
}