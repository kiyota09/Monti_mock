<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Eco\Inquiry;
use App\Models\Crm\FabricSampleRequest;
use App\Models\Core\Notification;
use App\Models\Core\User;
use App\Models\Eco\ConversationMessage;
use App\Models\Eco\ConversationAttachment;
use App\Models\Eco\EcoQuotation;
use App\Models\Eco\EcoQuotationItem;
use App\Models\Eco\EcoProductVariantPrice;
use App\Models\Man\BomRecord;
use App\Models\Ord\SalesOrder;
use App\Models\Ord\PurchaseOrder;
use App\Models\Inv\Material;
use App\Support\ConversationRealtime;
use App\Support\InquiryHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CrmInquiryController extends Controller
{
    /**
     * Credit snapshot for one client (ECO credit-check drawer).
     * {client} is implicitly bound (hashed keys resolve via
     * Client::resolveRouteBinding).
     */
    public function creditCheck(\App\Models\Crm\Client $client)
    {
        $client->load(['creditAccount', 'purchaseOrders']);

        $orders = $client->purchaseOrders->map(fn ($o) => [
            'id' => $o->id,
            'po_number' => $o->po_number,
            'total_amount' => (float) $o->total_amount,
            'status' => $o->status,
            'created_at' => $o->created_at?->format('Y-m-d'),
        ]);

        return response()->json([
            'client' => [
                'id' => $client->id,
                'company_name' => $client->company_name,
                'contact_person' => $client->contact_person,
                'credit_limit' => (float) ($client->credit_limit ?? 0),
            ],
            'credit_account' => $client->creditAccount ? [
                'outstanding_balance' => (float) $client->creditAccount->outstanding_balance,
                'is_good_payer' => (bool) $client->creditAccount->is_good_payer,
            ] : null,
            'orders_count' => $orders->count(),
            'orders' => $orders,
        ]);
    }

    /**
     * Display a listing of the inquiries.
     */
    public function index()
    {
        $inquiries = Inquiry::with([
            'client.logo',
            'product.images' => fn ($query) => $query->orderBy('id'),
            'latestMessage',
        ])->latest()->get();
        return Inertia::render('Dashboard/CRM/Inquiry', ['inquiries' => $inquiries]);
    }

    /**
     * Display the specific inquiry and conversation page.
     * Passes `parsedProducts`, `recipes`, and `materials` for the UI.
     * The route key is hashed (see App\Support\InquiryHash).
     */
    public function show($key)
    {
        $inquiry = Inquiry::with(['client', 'product.images', 'messages' => function ($query) {
            $query->with(['attachments', 'sampleRequest'])->oldest();
        }, 'sampleRequests.product'])
            ->findOrFail(InquiryHash::decodeOrFail((string) $key));

        $inquiry->messages->each(function ($message) {
            $sample = $message->sampleRequest;
            if (! $sample) return;
            $sample->setAttribute('sample_fabric_design', $sample->fabricDesignForSample());
            $sample->setAttribute('sample_yarn_type', $sample->yarnTypeForSample());
            $sample->setAttribute('pantone_colors', collect($sample->pantone_colors)->map(fn ($color) => is_array($color) ? collect($color)->except(['formula', 'pantone_formula', 'recipe', 'recipe_id'])->all() : $color)->all());
            $sample->makeHidden(['formula', 'pantone_formula', 'recipe_id', 'formulations']);
        });
        $inquiry->sampleRequests->each(function (FabricSampleRequest $sample) {
            $sample->setAttribute('sample_fabric_design', $sample->fabricDesignForSample());
            $sample->setAttribute('sample_yarn_type', $sample->yarnTypeForSample());
            $sample->setAttribute('pantone_colors', collect($sample->pantone_colors)->map(fn ($color) => is_array($color) ? collect($color)->except(['formula', 'pantone_formula', 'recipe', 'recipe_id'])->all() : $color)->all());
            $sample->makeHidden(['formula', 'pantone_formula', 'recipe_id', 'formulations']);
        });

        $parsedProducts = collect($this->extractProductsFromInquiry($inquiry));
        // Client product inquiries also store their selected product in the
        // selections payload. Resolve MOQ from that canonical ID so quoting
        // does not depend on a serialized relationship being present.
        $selectedProductId = (int) (($inquiry->product_selections['product_id'] ?? null) ?: $inquiry->product_id);
        $selectedProduct = $selectedProductId
            ? \App\Models\Inv\Product::query()->find($selectedProductId, ['id', 'name', 'minimum_order_quantity'])
            : null;
        $inquiry->setAttribute('selected_product_minimum_order_quantity', $selectedProduct?->minimum_order_quantity);
        $selectedProductMinimumOrderQuantity = $selectedProduct?->minimum_order_quantity;
        $productSkus = $parsedProducts->pluck('sku')->filter()->unique()->values();
        $productNames = $parsedProducts->pluck('name')->filter()->unique()->values();
        $matchedProducts = collect();
        if ($productSkus->isNotEmpty() || $productNames->isNotEmpty()) {
            $matchedProducts = \App\Models\Inv\Product::query()
                ->with(['images' => fn ($query) => $query->orderBy('id')])
                ->where(function ($query) use ($productSkus, $productNames) {
                    if ($productSkus->isNotEmpty()) $query->whereIn('sku', $productSkus);
                    if ($productNames->isNotEmpty()) {
                        $method = $productSkus->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                        $query->{$method}('name', $productNames);
                    }
                })
                ->get(['id', 'name', 'sku', 'minimum_order_quantity'])
                ->values();
        }
        $productsBySku = $matchedProducts->filter(fn ($product) => filled($product->sku))
            ->keyBy(fn ($product) => mb_strtoupper(trim($product->sku)));
        $productsByName = $matchedProducts->keyBy(fn ($product) => mb_strtolower(trim($product->name)));
        $parsedProducts = $parsedProducts->map(function ($parsed) use ($productsBySku, $productsByName) {
            $product = filled($parsed['sku'] ?? null)
                ? $productsBySku->get(mb_strtoupper(trim($parsed['sku'])))
                : null;
            $product ??= $productsByName->get(mb_strtolower(trim($parsed['name'])));

            return [
                ...$parsed,
                'id' => $product?->id ?? ($parsed['id'] ?? null),
                'minimum_order_quantity' => $product?->minimum_order_quantity,
                'images' => $product?->images->map(fn ($image) => ['id' => $image->id, 'url' => $image->url])->values() ?? collect(),
            ];
        })->values();

        $quotations = EcoQuotation::with('items.product')
            ->where('inquiry_id', $inquiry->id)
            ->latest()
            ->get();
        $selectionPricing = [];
        $productSelections = $inquiry->product_selections ?? [];
        if (! empty($productSelections['yarns']) && ! empty($productSelections['pantones'])) {
            $selectionPricing = EcoProductVariantPrice::query()
                ->where('product_id', $productSelections['product_id'] ?? $inquiry->product_id)
                ->whereIn('material_id', collect($productSelections['yarns'])->pluck('id'))
                ->whereIn('color_group', collect($productSelections['pantones'])->pluck('color_category')->filter()->unique())
                ->get(['material_id', 'color_group', 'price'])
                ->mapWithKeys(fn ($price) => [$price->material_id.':'.$price->color_group => $price->price === null ? null : (float) $price->price])
                ->all();
        }
        $acceptedFabricOptions = $quotations
            ->where('status', 'accepted')
            ->flatMap(fn ($quotation) => $quotation->items->map(fn ($item) => [
                'quotation_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'yarn_type' => $item->fabric_type,
                'fabric' => $item->fabric,
                'design' => $item->design,
                'color' => $item->color,
                'fabric_label' => collect([$item->fabric_type ? 'Yarn: ' . trim($item->fabric_type) : null, $item->fabric, $item->design, $item->color])
                    ->filter(fn ($value) => filled($value))
                    ->map(fn ($value) => trim((string) $value))
                    ->unique(fn ($value) => mb_strtolower($value))
                    ->implode(' · '),
            ]))
            ->values();

        // ─── FIX: Fetch ALL recipes for this client and explicitly map fields ───
        // We use ->get() without a select() restriction, then manually map to
        // guarantee product_id is always present in the Inertia JSON payload,
        // regardless of any $hidden / $visible settings on the BomRecord model.
        $recipes = BomRecord::with('product')
            ->where('client_id', $inquiry->client_id)
            ->get()
            ->map(fn ($r) => [
                'id'           => $r->id,
                'client_id'    => $r->client_id,
                'product_id'   => (int) $r->product_id,   // cast to int — critical for Vue comparison
                'yarn_type'    => $r->yarn_type,
                'dye_color'    => $r->dye_color,
                'weave_design' => $r->weave_design,
                'product'      => $r->product
                    ? ['id' => (int) $r->product->id, 'name' => $r->product->name, 'sku' => $r->product->sku ?? '']
                    : null,
            ])
            ->values();

        // Debug: Log only non-sensitive metadata
        Log::info('CrmInquiryController@show - Recipes for client', [
            'client_id'     => $inquiry->client_id,
            'recipes_count' => $recipes->count(),
        ]);

        // Get all raw materials for recipe creation
        $materials = Material::select('id', 'mat_id', 'name', 'category', 'yarn_tier', 'unit')->get();

        // Lab recipes formulated for THIS conversation (dyeing lab chemist via
        // fabric sample rounds) so the job-order modal can surface and
        // pre-select them distinctly from older client recipes.
        $sampleRecipes = FabricSampleRequest::with(['recipe.product', 'product'])
            ->where('inquiry_id', $inquiry->id)
            ->whereNotNull('recipe_id')
            ->whereNotIn('status', [FabricSampleRequest::STATUS_CANCELLED])
            ->latest()
            ->get()
            ->map(fn ($s) => [
                'sample_id' => $s->id,
                'sample_code' => $s->code,
                'sample_status' => $s->status,
                'fabric_name' => $s->fabric_name,
                'recipe' => $s->recipe ? [
                    'id' => $s->recipe->id,
                    'product_id' => $s->recipe->product_id,
                    'yarn_type' => $s->recipe->yarn_type,
                    'dye_color' => $s->recipe->dye_color,
                    'weave_design' => $s->recipe->weave_design,
                    'product' => $s->recipe->product
                        ? ['id' => (int) $s->recipe->product->id, 'name' => $s->recipe->product->name]
                        : null,
                ] : null,
            ])
            ->filter(fn ($s) => $s['recipe'] !== null)
            ->values();

        $approvedRecipeSamples = FabricSampleRequest::query()
            ->with('recipe:id,product_id,yarn_type,dye_color,weave_design')
            ->where('inquiry_id', $inquiry->id)
            ->where('status', FabricSampleRequest::STATUS_APPROVED)
            ->whereNotNull('recipe_id')
            ->get();
        $clientPurchaseOrders = PurchaseOrder::with(['items.product', 'items.quotationItem'])
            ->where('inquiry_id', $inquiry->id)->latest()->get()
            ->map(fn (PurchaseOrder $po) => [
                'id' => $po->id, 'po_number' => $po->po_number, 'status' => $po->status,
                'total_amount' => (float) $po->total_amount,
                'pushed' => $po->salesOrders()->exists(),
                'items' => $po->items->map(function ($item) use ($approvedRecipeSamples) {
                    $options = $approvedRecipeSamples->filter(function ($sample) use ($item) {
                        if ((int) $sample->product_id !== (int) $item->product_id) return false;
                        return collect($sample->pantone_colors ?? [])->contains(fn ($color) =>
                            mb_strtolower(trim((string) ($color['pantone_name'] ?? $color['label'] ?? ''))) === mb_strtolower(trim((string) $item->color))
                            && mb_strtolower(trim((string) ($color['yarn_type'] ?? ''))) === mb_strtolower(trim((string) $item->fabric_type)));
                    })->map(fn ($sample) => [
                        'id' => (int) $sample->recipe_id,
                        'label' => $sample->code.' · '.($sample->recipe?->dye_color ?: $sample->color_description).' · '.$sample->recipe?->yarn_type,
                    ])->unique('id')->values();

                    return [
                        'id' => $item->id, 'product_id' => $item->product_id,
                        'product_name' => $item->product?->name, 'fabric_type' => $item->fabric_type,
                        'color' => $item->color, 'quantity' => (float) $item->quantity,
                        'recipe_id' => $item->recipe_id ? (int) $item->recipe_id : null,
                        'saved_recipe_id' => $item->recipe_id ? (int) $item->recipe_id : null,
                        'recipe_options' => $options,
                    ];
                })->values(),
            ])->values();

        return Inertia::render('Dashboard/CRM/InquiryShow', [
            'inquiry'        => $inquiry,
            'clientPurchaseOrders' => $clientPurchaseOrders,
            'selectedProductMinimumOrderQuantity' => $selectedProductMinimumOrderQuantity,
            'selectionPricing' => $selectionPricing,
            'quotations'     => $quotations,
            // Latest client-accepted quotation: the job-order modal prices
            // each color tier (White / Light / Dark) from these unit prices.
            'approvedQuotation' => $quotations->firstWhere('status', 'accepted'),
            // Every accepted quotation: a conversation may hold more than one
            // (e.g. revised quotes), so the job-order modal lets CRM pick
            // exactly which one prices the order. Latest first.
            'acceptedQuotations' => $quotations->where('status', 'accepted')->values(),
            'parsedProducts' => $parsedProducts,
            'acceptedFabricOptions' => $acceptedFabricOptions,
            'allProducts'    => \App\Models\Inv\Product::select('id', 'name', 'sku')->get(),
            'recipes'        => $recipes,
            'sampleRecipes'  => $sampleRecipes,
            'materials'      => $materials,
            'quotationFabrics' => Material::query()
                ->where('category', 'Yarn')
                ->orderBy('name')
                ->orderBy('yarn_tier')
                ->get(['id', 'name', 'yarn_tier'])
                ->map(fn (Material $material) => [
                    'id' => $material->id,
                    'name' => $material->display_name,
                    'base_name' => $material->name,
                    'tier' => $material->yarn_tier,
                ])
                ->values(),
        ]);
    }

    /**
     * Extract the list of inquired products from an inquiry.
     */
    private function extractProductsFromInquiry(Inquiry $inquiry): array
    {
        // Single-product inquiry
        if ($inquiry->product && $inquiry->product->name !== 'Bulk Inquiry') {
            return [[
                'name' => $inquiry->product->name,
                'sku'  => $inquiry->product->sku,
                'id'   => $inquiry->product->id,
            ]];
        }

        // Bulk inquiry: parse "• Product Name | SKU: xxx | Category: yyy" lines
        $products = [];
        $lines    = explode("\n", $inquiry->initial_message ?? '');

        foreach ($lines as $line) {
            $line = trim($line);
            if (!str_starts_with($line, '•')) {
                continue;
            }

            $content  = preg_replace('/^•\s*/', '', $line);
            $parts    = explode(' | ', $content);
            $name     = trim($parts[0] ?? $content);

            // Extract SKU if present (format: "SKU: xxx")
            $skuPart  = collect($parts)->first(fn($p) => str_starts_with(trim($p), 'SKU:'));
            $sku      = $skuPart ? trim(str_replace('SKU:', '', $skuPart)) : '';

            if ($name) {
                $products[] = ['name' => $name, 'sku' => $sku, 'id' => null];
            }
        }

        return $products;
    }

    /**
     * Issue a quotation with independent color-tier pricing per selected fabric type.
     */
    public function issueQuotation(Request $request, Inquiry $inquiry)
    {
        $selections = $inquiry->product_selections ?? [];
        $specificSelectionQuote = ! empty($selections['pantones']) && ! empty($selections['yarns']);
        $rules = [
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'nullable|integer|exists:products,id',
            'items.*.fabric'      => 'required|string|max:255',
            'items.*.fabric_type_id' => [
                'required',
                'integer',
                Rule::exists('materials', 'id')->where(fn ($query) => $query->where('category', 'Yarn')),
            ],
            'items.*.design'      => 'nullable|string|max:255',
            'items.*.kilos'       => 'required|numeric|min:0',
            'vat_type'            => 'required|in:inclusive,exclusive',
            'payment_terms'       => 'required|string|max:255',
            'notes'               => 'nullable|string',
        ];
        if ($specificSelectionQuote) {
            $rules['items.*.pantone_id'] = 'required|integer|exists:pantone_references,id';
            $rules['items.*.unit_price'] = 'required|numeric|min:0';
        } else {
            $rules['items.*.price_white'] = 'required|numeric|min:0';
            $rules['items.*.price_light'] = 'required|numeric|min:0';
            $rules['items.*.price_dark'] = 'required|numeric|min:0';
        }
        $validated = $request->validate($rules);

        // MOQ is a product minimum, so enforce the saved setting server-side
        // as well as pre-filling the quotation form. This also protects quotes
        // submitted from a stale browser tab.
        $productIds = collect($validated['items'])->pluck('product_id')->filter()->unique()->values();
        $minimums = $productIds->isNotEmpty()
            ? \App\Models\Inv\Product::query()->whereIn('id', $productIds)->pluck('minimum_order_quantity', 'id')
            : collect();
        $globalMinimum = \App\Models\Inv\Product::query()->whereNotNull('minimum_order_quantity')->orderBy('id')->value('minimum_order_quantity');
        $minimumErrors = [];
        foreach ($validated['items'] as $index => $item) {
            $minimum = $minimums->get($item['product_id'] ?? null) ?? $globalMinimum;
            if ($minimum !== null && (float) $item['kilos'] < (float) $minimum) {
                $minimumErrors["items.{$index}.kilos"] = 'Quantity must be at least the minimum order quantity of '.rtrim(rtrim(number_format((float) $minimum, 2, '.', ''), '0'), '.').' kg.';
            }
        }
        if ($minimumErrors) {
            throw \Illuminate\Validation\ValidationException::withMessages($minimumErrors);
        }

        if ($specificSelectionQuote) {
            $allowedPantones = collect($selections['pantones'])->keyBy(fn ($pantone) => (int) $pantone['id']);
            $allowedYarns = collect($selections['yarns'])->keyBy(fn ($yarn) => (int) $yarn['id']);
            $actualPairs = collect($validated['items'])->map(function ($item) use ($allowedPantones, $allowedYarns, $selections) {
                $pantoneId = (int) $item['pantone_id'];
                $yarnId = (int) $item['fabric_type_id'];
                if (! $allowedPantones->has($pantoneId) || ! $allowedYarns->has($yarnId)
                    || (int) ($item['product_id'] ?? 0) !== (int) ($selections['product_id'] ?? 0)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => 'Quotation lines must use the product, Pantone colors, and yarn types selected by the client.',
                    ]);
                }

                return $pantoneId.':'.$yarnId;
            });
            $expectedPairs = $allowedPantones->keys()->crossJoin($allowedYarns->keys())
                ->map(fn ($pair) => $pair[0].':'.$pair[1]);
            if ($actualPairs->unique()->count() !== $actualPairs->count()
                || $actualPairs->sort()->values()->all() !== $expectedPairs->sort()->values()->all()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => 'Include one quotation line for each selected Pantone color and yarn combination.',
                ]);
            }
        }

        $fabricTypes = Material::query()
            ->where('category', 'Yarn')
            ->whereIn('id', collect($validated['items'])->pluck('fabric_type_id')->unique())
            ->get()
            ->keyBy('id');

        $vatMultiplier = $validated['vat_type'] === 'inclusive' ? 1.12 : 1.0;

        $colorTiers = [
            ['key' => 'price_white', 'label' => 'White'],
            ['key' => 'price_light', 'label' => 'Light Colors'],
            ['key' => 'price_dark',  'label' => 'Dark Colors'],
        ];

        DB::beginTransaction();
        try {
            $quotation = EcoQuotation::create([
                'client_id'        => $inquiry->client_id,
                'inquiry_id'       => $inquiry->id,
                'quotation_number' => 'ECO-QT-' . date('Y') . '-' . strtoupper(Str::random(5)),
                'vat_type'         => $validated['vat_type'],
                'payment_terms'    => $validated['payment_terms'],
                'notes'            => $validated['notes'] ?? null,
                // 'grand_total'      => 0,
                'status'           => 'sent',
            ]);

            $grandTotal = 0;

            foreach ($validated['items'] as $item) {
                $kilos = (float) $item['kilos'];

                if ($specificSelectionQuote) {
                    $pantone = $allowedPantones->get((int) $item['pantone_id']);
                    $unitPrice = (float) $item['unit_price'];
                    $itemTotal = round($kilos * $unitPrice * $vatMultiplier, 2);
                    $grandTotal += $itemTotal;
                    $quotation->items()->create([
                        'product_id' => $selections['product_id'],
                        'fabric' => $item['fabric'],
                        'fabric_type_id' => $item['fabric_type_id'],
                        'fabric_type' => $fabricTypes->get((int) $item['fabric_type_id'])?->display_name,
                        'design' => $item['design'] ?? null,
                        'color' => $pantone['name'],
                        'kilos' => $kilos,
                        'unit_price' => $unitPrice,
                        'price' => $itemTotal,
                    ]);
                    continue;
                }

                foreach ($colorTiers as $tier) {
                    $unitPrice  = (float) $item[$tier['key']];
                    $itemTotal  = round($kilos * $unitPrice * $vatMultiplier, 2);
                    $grandTotal += $itemTotal;

                    $quotation->items()->create([
                        'product_id' => $item['product_id'] ?? null,
                        'fabric'     => $item['fabric'],
                        'fabric_type_id' => $item['fabric_type_id'],
                        'fabric_type' => $fabricTypes->get((int) $item['fabric_type_id'])?->display_name,
                        'design'     => $item['design'] ?? null,
                        'color'      => $tier['label'],
                        'kilos'      => $kilos,
                        'unit_price' => $unitPrice,
                        'price'      => $itemTotal,
                    ]);
                }
            }

            $quotation->update(['grand_total' => $grandTotal]);

            $vatLabel = $validated['vat_type'] === 'inclusive'
                ? 'VAT Inclusive (+12%)'
                : 'VAT Exclusive';

            $productCount = collect($validated['items'])->pluck('fabric')->unique()->count();
            $productList  = collect($validated['items'])
                ->map(fn ($i) => "• {$i['fabric']} — ".($fabricTypes->get((int) $i['fabric_type_id'])?->display_name ?? 'Fabric'))
                ->unique()
                ->implode("\n");

            ConversationMessage::create([
                'inquiry_id'      => $inquiry->id,
                'sender_type'     => 'eco',
                'message'         => "📋 Quotation Issued: {$quotation->quotation_number}\n"
                    . "Products ({$productCount}):\n{$productList}\n"
                    . "Terms: {$validated['payment_terms']} | {$vatLabel}",
                'is_system_event' => true,
            ]);

            $inquiry->update([
                'status'          => 'quotation_sent',
                'last_message_at' => now(),
            ]);

            DB::commit();
            return back()->with('success', 'Quotation sent successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Quotation Error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Database Error: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Handle sending a standard chat message with optional file attachments.
     * Returns JSON when called via axios (real-time mode), otherwise
     * falls back to the classic Inertia redirect for backwards compat.
     */
    public function sendMessage(Request $request, Inquiry $inquiry)
    {
        $request->validate([
            'message'  => 'required_without:files|nullable|string|max:2000',
            'files.*'  => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,zip|max:10240',
        ]);

        $message = ConversationMessage::create([
            'inquiry_id'  => $inquiry->id,
            'sender_type' => 'eco',
            'message'     => $request->message ?? '',
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('eco_attachments', 'public');
                ConversationAttachment::create([
                    'conversation_message_id' => $message->id,
                    'file_path'               => $path,
                    'file_name'               => $file->getClientOriginalName(),
                    'file_type'               => $file->getMimeType(),
                ]);
            }
        }

        $inquiry->update(['last_message_at' => now()]);

        // CRM just sent something → they are no longer "typing".
        ConversationRealtime::clearTyping($inquiry->id, 'eco');

        $message->load('attachments');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => $message], 201);
        }

        return back();
    }

    /**
     * Real-time polling feed for the CRM side.
     * GET ?after=<lastMessageId> → only newer messages are returned.
     * Also reports whether the CLIENT is currently typing.
     */
    public function feed(Request $request, Inquiry $inquiry)
    {
        $after = (int) $request->query('after', 0);
        $messages = ConversationRealtime::feed($inquiry->id, $after);

        return response()->json([
            'messages' => $messages,
            'typing'   => ConversationRealtime::isTyping($inquiry->id, 'client'),
        ]);
    }

    /**
     * Typing heartbeat for the CRM side. Called (debounced) while staff type.
     */
    public function typing(Request $request, Inquiry $inquiry)
    {
        ConversationRealtime::markTyping($inquiry->id, 'eco');

        return response()->json(['ok' => true]);
    }

    /**
     * Schedule a meeting for an inquiry.
     * NOTE: {inquiry} is implicitly bound (hashed keys resolve via
     * Inquiry::resolveRouteBinding), so this receives the model directly —
     * do NOT hash-decode it again (that path 500s on the model instance).
     */
    public function setMeeting(Request $request, Inquiry $inquiry)
    {
        $request->validate([
            // Require a future time so same-day meetings cannot be backdated.
            'scheduled_at' => 'required|date|after:now',
            'location'     => 'nullable|string|max:255',
            'type'         => 'nullable|string|in:video,phone,onsite',
        ]);

        $meetingData = [
            'scheduled_at' => $request->scheduled_at,
            'location'     => $request->location ?? 'Not specified',
            'type'         => $request->type ?? 'video',
        ];

        ConversationMessage::create([
            'inquiry_id'      => $inquiry->id,
            'sender_type'     => 'eco',
            'message'         => "Meeting scheduled: {$meetingData['type']} at {$meetingData['location']} on {$meetingData['scheduled_at']}",
            'meeting_data'    => $meetingData,
            'is_system_event' => true,
        ]);

        $inquiry->update(['last_message_at' => now()]);
        return redirect()->back()->with('success', 'Meeting scheduled successfully.');
    }

    /**
     * Reject the inquiry.
     */
    public function reject(Request $request, Inquiry $inquiry)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $inquiry->update([
                'status'          => 'rejected',
                'last_message_at' => now(),
            ]);

            $reasonText = $request->reason ? "\nReason: " . $request->reason : '';
            ConversationMessage::create([
                'inquiry_id'      => $inquiry->id,
                'sender_type'     => 'eco',
                'message'         => "Inquiry Rejected by CRM Manager." . $reasonText,
                'is_system_event' => true,
            ]);

            DB::commit();
            return back()->with('success', 'Inquiry has been rejected.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to reject: ' . $e->getMessage()]);
        }
    }

    /**
     * Request a fabric sample from the dyeing lab, from inside the
     * conversation. The lab chemist picks it up on the shade page.
     */
    public function requestSample(Request $request, Inquiry $inquiry)
    {
        $validated = $request->validate([
            'product_id'        => 'required|exists:products,id',
            'color_description' => 'required|string|max:2000',
            'notes'             => 'nullable|string|max:2000',
            'urgency'           => 'nullable|in:low,normal,high,urgent',
        ]);

        $product = \App\Models\Inv\Product::findOrFail($validated['product_id']);

        $round = FabricSampleRequest::where('inquiry_id', $inquiry->id)->count() + 1;

        $sample = FabricSampleRequest::create([
            'code'              => 'FSR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5)),
            'inquiry_id'        => $inquiry->id,
            'client_id'         => $inquiry->client_id,
            'product_id'        => $product->id,
            'fabric_name'       => $product->name,
            'color_description' => $validated['color_description'],
            'notes'             => $validated['notes'] ?? null,
            'urgency'           => $validated['urgency'] ?? 'normal',
            'status'            => FabricSampleRequest::STATUS_REQUESTED,
            'requested_by'      => Auth::id(),
        ]);

        ConversationMessage::create([
            'inquiry_id'        => $inquiry->id,
            'sender_type'       => 'eco',
            'message'           => "🧪 Fabric Sample Requested (Round {$round}): {$product->name}\n"
                . "Colors: {$validated['color_description']}"
                . (! empty($validated['notes']) ? "\nNotes: {$validated['notes']}" : ''),
            'is_system_event'   => true,
            // Internal Monti process — the client only sees the result once
            // CRM forwards the formulated sample.
            'visible_to_client' => false,
            'sample_request_id' => $sample->id,
        ]);

        $inquiry->update(['last_message_at' => now()]);

        return back()->with('success', "Sample request {$sample->code} sent to the dyeing lab.");
    }

    /** Send every client-selected Pantone × yarn combination to the lab queue. */
    public function createSelectedRecipes(Request $request, Inquiry $inquiry)
    {
        $validated = $request->validate(['notes' => 'nullable|string|max:2000']);
        $selection = $inquiry->product_selections ?? [];
        $pantones = collect($selection['pantones'] ?? []);
        $yarns = collect($selection['yarns'] ?? []);
        $productId = (int) (($selection['product_id'] ?? null) ?: $inquiry->product_id);
        abort_unless($productId && $pantones->isNotEmpty() && $yarns->isNotEmpty(), 422, 'This inquiry has no selected Pantone colors and yarns.');
        $product = \App\Models\Inv\Product::findOrFail($productId);
        $created = [];

        DB::transaction(function () use ($inquiry, $pantones, $yarns, $product, $validated, &$created) {
            foreach ($pantones as $pantone) {
                $pantoneId = (int) ($pantone['id'] ?? 0);
                $reference = \App\Models\Man\PantoneReference::find($pantoneId);
                abort_unless($reference, 422, 'A selected Pantone color is no longer available.');
                foreach ($yarns as $yarn) {
                    $yarnId = (int) ($yarn['id'] ?? 0);
                    $material = Material::query()->whereKey($yarnId)->where('category', 'Yarn')->first();
                    abort_unless($material, 422, 'A selected yarn type is no longer available.');
                    $alreadyRequested = FabricSampleRequest::query()->where('inquiry_id', $inquiry->id)
                        ->whereNotIn('status', [FabricSampleRequest::STATUS_CANCELLED])
                        ->get()->first(fn ($sample) => collect($sample->pantone_colors ?? [])->contains(fn ($color) =>
                            (int) ($color['pantone_reference_id'] ?? 0) === $pantoneId
                            && (int) ($color['yarn_material_id'] ?? 0) === $yarnId));
                    if ($alreadyRequested) continue;

                    $formula = \App\Models\Man\PantoneFabricFormula::query()
                        ->where('pantone_reference_id', $pantoneId)->where('yarn_material_id', $yarnId)->first();
                    $color = [
                        'label' => $reference->name, 'pantone_reference_id' => $reference->id,
                        'pantone_name' => $reference->name, 'hex' => $reference->hex,
                        'color_category' => $reference->color_category, 'yarn_material_id' => $material->id,
                        'yarn_type' => $material->display_name,
                    ];
                    if ($formula) $color['formula'] = $formula->combinations;
                    $code = 'FSR-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
                    $sample = FabricSampleRequest::create([
                        'code' => $code, 'inquiry_id' => $inquiry->id, 'client_id' => $inquiry->client_id,
                        'product_id' => $product->id, 'fabric_name' => 'Yarn: '.$material->display_name.' · '.$product->name.' · '.$reference->name,
                        'request_method' => 'pantone', 'color_description' => $reference->name,
                        'texture' => $inquiry->product_selections['texture'] ?? null,
                        'gsm' => $inquiry->product_selections['gsm'] ?? null,
                        'pantone_reference_id' => $reference->id, 'pantone_colors' => [$color],
                        'notes' => trim(implode("\n", array_filter([
                            'Client request: '.mb_substr((string) $inquiry->initial_message, 0, 1400),
                            filled($validated['notes'] ?? null) ? 'CRM lab instructions: '.$validated['notes'] : null,
                        ]))),
                        'urgency' => 'normal', 'status' => FabricSampleRequest::STATUS_REQUESTED, 'requested_by' => Auth::id(),
                    ]);
                    ConversationMessage::create([
                        'inquiry_id' => $inquiry->id, 'sender_type' => 'eco',
                        'message' => "Recipe and swatch requested for {$product->name} · {$reference->name} · {$material->display_name} ({$code}).",
                        'is_system_event' => true, 'visible_to_client' => false, 'sample_request_id' => $sample->id,
                    ]);
                    User::query()->where('manufacturing_role', 'dyeing_lab_chemist')->pluck('id')->each(fn ($userId) => Notification::notify(
                        (int) $userId, 'manufacturing', 'New client recipe request',
                        "{$code}: formulate {$reference->name} for {$material->display_name} ({$product->name}).",
                        'man.staff.dyeing-lab-chemist.page', Auth::id()
                    ));
                    $created[] = $sample->id;
                }
            }
            $inquiry->update(['last_message_at' => now()]);
        });

        return back()->with('success', $created
            ? count($created).' Pantone and yarn recipe request(s) sent to the Dyeing Lab Chemist.'
            : 'All requested Pantone and yarn combinations already have active lab requests.');
    }

    /** Save an approved lab recipe on each matching client PO line. */
    public function saveClientPurchaseOrderRecipes(Request $request, Inquiry $inquiry, PurchaseOrder $purchaseOrder)
    {
        abort_unless((int) $purchaseOrder->inquiry_id === (int) $inquiry->id, 404);
        abort_if($purchaseOrder->salesOrders()->exists(), 422, 'This purchase order has already been passed to ECO Push Center.');
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'distinct', 'exists:purchase_order_items,id'],
            'items.*.recipe_id' => ['required', 'integer', 'exists:bom_records,id'],
        ]);
        $lines = $purchaseOrder->items()->whereIn('id', collect($data['items'])->pluck('purchase_order_item_id'))->get()->keyBy('id');
        abort_unless($lines->count() === count($data['items']) && $lines->count() === $purchaseOrder->items()->count(), 422, 'Select and link a recipe for every PO line.');
        $approvedSamples = FabricSampleRequest::query()->where('inquiry_id', $inquiry->id)
            ->where('status', FabricSampleRequest::STATUS_APPROVED)->whereNotNull('recipe_id')->get();

        DB::transaction(function () use ($data, $lines, $approvedSamples) {
            foreach ($data['items'] as $row) {
                $line = $lines->get((int) $row['purchase_order_item_id']);
                $recipeId = (int) $row['recipe_id'];
                $isMatchingApprovedRecipe = $approvedSamples->contains(function ($sample) use ($line, $recipeId) {
                    if ((int) $sample->recipe_id !== $recipeId || (int) $sample->product_id !== (int) $line->product_id) return false;
                    return collect($sample->pantone_colors ?? [])->contains(fn ($color) =>
                        mb_strtolower(trim((string) ($color['pantone_name'] ?? $color['label'] ?? ''))) === mb_strtolower(trim((string) $line->color))
                        && mb_strtolower(trim((string) ($color['yarn_type'] ?? ''))) === mb_strtolower(trim((string) $line->fabric_type)));
                });
                if (! $isMatchingApprovedRecipe) {
                    throw ValidationException::withMessages([
                        'items' => "The selected recipe is not an approved match for {$line->product_id}, {$line->color}, and {$line->fabric_type}.",
                    ]);
                }
                $line->update(['recipe_id' => $recipeId]);
            }
        });

        return back()->with('success', 'Approved recipes are linked to every client purchase order line.');
    }

    /** Convert an approved client PO into pending ECO Push Center lines. */
    public function pushClientPurchaseOrderToEco(Inquiry $inquiry, PurchaseOrder $purchaseOrder)
    {
        abort_unless((int) $purchaseOrder->inquiry_id === (int) $inquiry->id, 404);
        abort_if($purchaseOrder->salesOrders()->exists(), 422, 'This purchase order has already been passed to ECO Push Center.');
        $samples = FabricSampleRequest::query()->where('inquiry_id', $inquiry->id)
            ->where('status', FabricSampleRequest::STATUS_APPROVED)->whereNotNull('recipe_id')->get();
        $selections = $inquiry->product_selections ?? [];
        foreach (collect($selections['pantones'] ?? [])->crossJoin(collect($selections['yarns'] ?? [])) as [$pantone, $yarn]) {
            $isApproved = $samples->contains(fn ($sample) => collect($sample->pantone_colors ?? [])->contains(fn ($color) =>
                (int) ($color['pantone_reference_id'] ?? 0) === (int) ($pantone['id'] ?? 0)
                && (int) ($color['yarn_material_id'] ?? 0) === (int) ($yarn['id'] ?? 0)));
            abort_unless($isApproved, 422, 'Every client-selected color and yarn combination must have an approved recipe before pushing this order.');
        }

        $purchaseOrder->load(['items.product', 'items.quotationItem.quotation']);
        $resolved = [];
        foreach ($purchaseOrder->items as $item) {
            $sample = $samples->first(function ($sample) use ($item) {
                if (! $item->recipe_id || (int) $sample->recipe_id !== (int) $item->recipe_id || (int) $sample->product_id !== (int) $item->product_id) return false;
                foreach ($sample->pantone_colors ?? [] as $color) {
                    $sameColor = mb_strtolower(trim((string) ($color['pantone_name'] ?? $color['label'] ?? ''))) === mb_strtolower(trim((string) $item->color));
                    $sameYarn = mb_strtolower(trim((string) ($color['yarn_type'] ?? ''))) === mb_strtolower(trim((string) $item->fabric_type));
                    if ($sameColor && $sameYarn) return true;
                }
                return false;
            });
            abort_unless($sample?->recipe_id, 422, "CRM must link an approved recipe to {$item->product?->name}, {$item->color}, and {$item->fabric_type} before this PO can be pushed.");
            $resolved[] = [$item, $sample];
        }

        DB::transaction(function () use ($purchaseOrder, $inquiry, $resolved) {
            $joNumber = 'JO-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
            foreach ($resolved as [$item, $sample]) {
                $subtotal = round((float) $item->quantity * (float) $item->unit_price, 2);
                $quotation = $item->quotationItem?->quotation;
                $tax = $quotation?->vat_type === 'inclusive' ? round($subtotal * .12, 2) : 0;
                $control = 'CTL-'.now()->format('y').strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', (string) $item->color), 0, 3)).'-'.strtoupper(Str::random(5));
                SalesOrder::create([
                    'purchase_order_id' => $purchaseOrder->po_number, 'client_id' => $purchaseOrder->client_id,
                    'jo_number' => $joNumber, 'control_number' => $control, 'color' => $item->color,
                    'quantity' => $item->quantity, 'unit_price' => $item->unit_price, 'subtotal' => $subtotal,
                    'tax_rate' => $tax > 0 ? 12 : 0, 'tax_amount' => $tax, 'total_amount' => $subtotal + $tax,
                    'yarn_type' => $item->fabric_type, 'design' => $item->design ?: $item->fabric,
                    'recipe_id' => $sample->recipe_id, 'status' => 'pending', 'created_by' => Auth::id(),
                ]);
            }
            ConversationMessage::create([
                'inquiry_id' => $inquiry->id, 'sender_type' => 'eco', 'is_system_event' => true,
                'message' => "Client purchase order {$purchaseOrder->po_number} passed to the ECO Push Center with ".count($resolved).' approved product, color, and yarn line(s).',
            ]);
            $inquiry->update(['last_message_at' => now()]);
        });

        return back()->with('success', "Purchase order {$purchaseOrder->po_number} is now in the ECO Push Center.");
    }

    public function deliverSampleToClient(Request $request, Inquiry $inquiry, FabricSampleRequest $sampleRequest)
    {
        abort_unless($sampleRequest->inquiry_id === $inquiry->id, 404);
        abort_unless($sampleRequest->status === FabricSampleRequest::STATUS_AWAITING_CRM_REVIEW, 422, 'This fabric sample is not ready to be sent.');

        $formulation = $sampleRequest->formulations()->where('status', 'awaiting_crm')->latest('version')->first();
        abort_unless($formulation, 422, 'The Dyeing Lab Chemist has not submitted a sample image yet.');
        $validated = $request->validate([
            'delivery_courier' => 'required|string|max:120',
            'delivery_tracking_reference' => 'required|string|max:160',
            'delivery_notes' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($inquiry, $sampleRequest, $formulation, $validated) {
            $sampleRequest->update([
                ...$validated,
                'status' => FabricSampleRequest::STATUS_FORWARDED,
                'delivery_notes' => $validated['delivery_notes'] ?? null,
                'sample_image_path' => $formulation->attachment_path,
                'delivery_sent_at' => now(),
                'client_noted_at' => null,
                'client_received_at' => null,
                'recipe_id' => null,
            ]);
            $formulation->update(['status' => 'forwarded_to_client']);

            $message = ConversationMessage::create([
                'inquiry_id' => $inquiry->id,
                'sender_type' => 'eco',
                'message' => "A physical fabric swatch is on its way for {$sampleRequest->fabric_name}. Courier: {$validated['delivery_courier']}. Tracking reference: {$validated['delivery_tracking_reference']}." . (filled($validated['delivery_notes'] ?? null) ? "\nNotes: {$validated['delivery_notes']}" : ''),
                'is_system_event' => false,
                'visible_to_client' => true,
                'sample_request_id' => $sampleRequest->id,
            ]);
            ConversationAttachment::create([
                'conversation_message_id' => $message->id,
                'file_path' => $formulation->attachment_path,
                'file_name' => $formulation->attachment_name,
                'file_type' => $formulation->attachment_mime,
            ]);
            $sampleRequest->update(['delivery_message_id' => $message->id]);
            if ($formulation->submitted_by) {
                Notification::notify(
                    (int) $formulation->submitted_by,
                    'manufacturing',
                    'Fabric swatch sent to client',
                    "{$sampleRequest->code} was sent via {$validated['delivery_courier']} (tracking {$validated['delivery_tracking_reference']}).",
                    'man.staff.dyeing-lab-chemist.page',
                    auth()->id()
                );
            }
            $inquiry->update(['last_message_at' => now()]);
        });

        return back()->with('success', 'Fabric swatch delivery details sent to the client.');
    }

    public function deliverSamplesToClient(Request $request, Inquiry $inquiry)
    {
        $validated = $request->validate([
            'sample_request_ids' => 'required|array|min:2|max:50',
            'sample_request_ids.*' => 'required|integer|distinct|exists:fabric_sample_requests,id',
            'delivery_courier' => 'required|string|max:120',
            'delivery_tracking_reference' => 'required|string|max:160',
            'delivery_notes' => 'nullable|string|max:1000',
        ]);

        $samples = FabricSampleRequest::query()->where('inquiry_id', $inquiry->id)
            ->whereIn('id', $validated['sample_request_ids'])->get()->keyBy('id');
        abort_unless($samples->count() === count($validated['sample_request_ids']), 422, 'Select samples from this client inquiry only.');

        $formulations = [];
        foreach ($samples as $sample) {
            abort_unless($sample->status === FabricSampleRequest::STATUS_AWAITING_CRM_REVIEW, 422, "{$sample->code} is not ready to send.");
            $formulation = $sample->formulations()->where('status', 'awaiting_crm')->latest('version')->first();
            abort_unless($formulation, 422, "{$sample->code} does not have a sample image yet.");
            $formulations[$sample->id] = $formulation;
        }

        $groupCode = 'SHP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        DB::transaction(function () use ($inquiry, $samples, $formulations, $validated, $groupCode) {
            foreach ($samples as $sample) {
                $formulation = $formulations[$sample->id];
                $sample->update([
                    'delivery_courier' => $validated['delivery_courier'],
                    'delivery_tracking_reference' => $validated['delivery_tracking_reference'],
                    'delivery_group_code' => $groupCode,
                    'delivery_notes' => $validated['delivery_notes'] ?? null,
                    'status' => FabricSampleRequest::STATUS_FORWARDED,
                    'sample_image_path' => $formulation->attachment_path,
                    'delivery_sent_at' => now(),
                    'client_noted_at' => null,
                    'client_received_at' => null,
                    'recipe_id' => null,
                ]);
                $formulation->update(['status' => 'forwarded_to_client']);

                $message = ConversationMessage::create([
                    'inquiry_id' => $inquiry->id,
                    'sender_type' => 'eco',
                    'message' => "A physical fabric swatch ({$sample->code}) is on its way with other samples in shipment {$groupCode}. Courier: {$validated['delivery_courier']}. Tracking reference: {$validated['delivery_tracking_reference']}." . (filled($validated['delivery_notes'] ?? null) ? "\nNotes: {$validated['delivery_notes']}" : ''),
                    'is_system_event' => false,
                    'visible_to_client' => true,
                    'sample_request_id' => $sample->id,
                ]);
                ConversationAttachment::create([
                    'conversation_message_id' => $message->id,
                    'file_path' => $formulation->attachment_path,
                    'file_name' => $formulation->attachment_name,
                    'file_type' => $formulation->attachment_mime,
                ]);
                $sample->update(['delivery_message_id' => $message->id]);
                if ($formulation->submitted_by) {
                    Notification::notify((int) $formulation->submitted_by, 'manufacturing', 'Fabric swatches sent to client',
                        "{$sample->code} was included in shipment {$groupCode} via {$validated['delivery_courier']} (tracking {$validated['delivery_tracking_reference']}).",
                        'man.staff.dyeing-lab-chemist.page', auth()->id());
                }
            }
            $inquiry->update(['last_message_at' => now()]);
        });

        return back()->with('success', count($samples) . ' fabric samples sent together in shipment ' . $groupCode . '.');
    }

    public function receiveSample(Request $request, Inquiry $inquiry, FabricSampleRequest $sampleRequest)
    {
        abort_unless($sampleRequest->inquiry_id === $inquiry->id, 404);
        abort_unless(in_array($sampleRequest->status, ['awaiting_receipt', 'client_submitted'], true), 422, 'This sample request has already been received.');
        $sourceRequests = collect([$sampleRequest]);
        if ($sampleRequest->status === 'awaiting_receipt' && $sampleRequest->request_method === 'image' && $sampleRequest->client_reference_path && ! $sampleRequest->parent_id) {
            $sourceRequests = FabricSampleRequest::query()
                ->where('inquiry_id', $inquiry->id)
                ->where('request_method', 'image')
                ->where('client_reference_path', $sampleRequest->client_reference_path)
                ->where('status', 'awaiting_receipt')
                ->whereNull('parent_id')
                ->get();
        }
        $validated = $request->validate([
            'colors' => 'required|array|min:1|max:30',
            'colors.*.fabric_name' => 'required|string|max:255',
            'colors.*.pantone_code' => 'required|string|max:120',
            'colors.*.pantone_color' => 'required|string|max:120',
            'colors.*.texture' => 'required|in:Hard,Medium,Soft',
            'colors.*.gsm' => 'required|integer|min:1|max:10000',
            'colors.*.reference_type' => 'required|in:hex,image',
            'colors.*.color_hex' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.*.color_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $referenceErrors = [];
        foreach ($validated['colors'] as $index => $color) {
            if ($color['reference_type'] === 'hex' && !preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($color['color_hex'] ?? ''))) {
                $referenceErrors["colors.{$index}.color_hex"] = 'Choose a valid hex color.';
            }
            if ($color['reference_type'] === 'image') {
                if (!$request->hasFile("colors.{$index}.color_image")) {
                    $referenceErrors["colors.{$index}.color_image"] = 'Upload an image for this color.';
                }
            }
        }
        if ($referenceErrors) {
            return back()->withErrors($referenceErrors);
        }
        foreach ($validated['colors'] as $index => &$color) {
            if ($color['reference_type'] === 'image') {
                $color['color_image_path'] = $request->file("colors.{$index}.color_image")->store('fabric_sample_color_references', 'public');
            }
        }
        unset($color);

        DB::transaction(function () use ($sampleRequest, $sourceRequests, $validated) {
            $children = collect();
            foreach ($validated['colors'] as $color) {
                $children->push(FabricSampleRequest::create([
                    'code' => 'FSR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5)),
                    'inquiry_id' => $sampleRequest->inquiry_id,
                    'client_id' => $sampleRequest->client_id,
                    'product_id' => $sampleRequest->product_id,
                    'fabric_name' => trim($color['fabric_name']),
                    'texture' => $color['texture'],
                    'gsm' => $color['gsm'],
                    'color_hex' => $color['reference_type'] === 'hex' ? $color['color_hex'] : null,
                    'color_image_path' => $color['color_image_path'] ?? null,
                    'request_method' => $sampleRequest->request_method,
                    'carrier_name' => $sampleRequest->carrier_name,
                    'client_reference_path' => $sampleRequest->client_reference_path,
                    'color_description' => trim($color['pantone_color']) . ' (' . trim($color['pantone_code']) . ')',
                    'notes' => $sampleRequest->notes,
                    'urgency' => $sampleRequest->urgency,
                    'status' => 'crm_review',
                    'parent_id' => $sampleRequest->id,
                ]));
            }
            $sourceRequests->each(fn ($sourceRequest) => $sourceRequest->update(['status' => 'received']));
            ConversationMessage::create([
                'inquiry_id' => $sampleRequest->inquiry_id,
                'sender_type' => 'eco',
                'message' => 'Monti Textile received the fabric sample request ' . $sampleRequest->code . ' for ' . $sampleRequest->fabric_name . '. CRM recorded ' . $children->count() . ' Pantone color(s).',
                'is_system_event' => true,
                'visible_to_client' => true,
                'sample_request_id' => $sampleRequest->id,
            ]);
        });

        return back()->with('success', 'Sample receipt recorded. Review the Pantone colors and pass them to Color Request.');
    }

    public function passSampleToLab(Inquiry $inquiry, FabricSampleRequest $sampleRequest)
    {
        abort_unless($sampleRequest->inquiry_id === $inquiry->id, 404);
        abort_unless(in_array($sampleRequest->status, ['client_submitted', 'crm_review'], true), 422, 'This color is not awaiting CRM review.');
        $sampleRequest->update(['status' => FabricSampleRequest::STATUS_REQUESTED]);
        ConversationMessage::create([
            'inquiry_id' => $inquiry->id,
            'sender_type' => 'eco',
            'message' => 'Color request ' . $sampleRequest->code . ' was reviewed by CRM and passed to the Dyeing Lab Chemist: ' . $sampleRequest->fabric_name . ' · ' . $sampleRequest->color_description,
            'is_system_event' => true,
            'visible_to_client' => true,
            'sample_request_id' => $sampleRequest->id,
        ]);
        $inquiry->update(['last_message_at' => now()]);
        return back()->with('success', 'Color request passed to the Dyeing Lab Chemist.');
    }

    /**
     * Create a recipe from an approved attachment.
     */
    public function createRecipeFromAttachment(Request $request, ConversationAttachment $attachment)
    {
        $inquiry = $attachment->message->inquiry;

        $validated = $request->validate([
            'client_id'    => 'required|exists:clients,id',
            'product_id'   => 'required|exists:products,id',
            'yarn_type'    => 'required|string|max:255',
            'dye_color'    => 'required|string|max:255',
            'weave_design' => 'required|string|max:255',
            'materials'    => 'required|array|min:1',
            'materials.*'  => 'exists:materials,id',
        ]);

        try {
            // Convert materials array to an object with quantity = 1 (placeholder)
            $materialsData = [];
            foreach ($validated['materials'] as $materialId) {
                $materialsData[$materialId] = 1;
            }

            $recipe = BomRecord::updateOrCreate(
                [
                    'client_id'  => $validated['client_id'],
                    'product_id' => $validated['product_id'],
                ],
                [
                    'yarn_type'    => $validated['yarn_type'],
                    'dye_color'    => $validated['dye_color'],
                    'weave_design' => $validated['weave_design'],
                    'materials'    => json_encode($materialsData),
                ]
            );

            // System message
            ConversationMessage::create([
                'inquiry_id'      => $inquiry->id,
                'sender_type'     => 'eco',
                'message'         => "📋 Recipe " . ($recipe->wasRecentlyCreated ? 'created' : 'updated') . ": {$recipe->product->name} ({$recipe->yarn_type})",
                'is_system_event' => true,
            ]);

            return back()->with('success', 'Recipe saved successfully.');
        } catch (\Exception $e) {
            Log::error('Recipe creation failed: ' . $e->getMessage(), [
                'attachment_id' => $attachment->id,
                'validated'     => $validated,
            ]);
            return back()->withErrors(['error' => 'Failed to save recipe: ' . $e->getMessage()]);
        }
    }

    /**
     * Create a Job Order from a Purchase Order attachment.
     */
    public function createJobOrderFromPO(Request $request, ConversationAttachment $attachment)
    {
        $inquiry = $attachment->message->inquiry;

        $validated = $request->validate([
            'po_number' => 'required|string|max:255',
            'items'     => 'required|array|min:1',
            'items.*.product_id'  => 'required|exists:products,id',
            'items.*.color'       => 'required|string|max:255',
            'items.*.recipe_id'   => 'nullable|exists:bom_records,id',
            'items.*.kilos'       => 'required|numeric|min:0',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.description' => 'nullable|string|max:500',
        ]);

        try {
            // Generate JO Number
            $joNumber = 'JO-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));

            $createdOrders = [];

            foreach ($validated['items'] as $item) {
                $product = \App\Models\Inv\Product::find($item['product_id']);
                $subtotal = round($item['kilos'] * $item['unit_price'], 2);
                $tax = round($subtotal * 0.12, 2);
                $total = $subtotal + $tax;

                $colorCode = strtoupper(substr($item['color'], 0, 3));
                $controlNumber = 'CTL-' . now()->format('y') . $colorCode . '-' . strtoupper(Str::random(5));

                $salesOrder = SalesOrder::create([
                    'purchase_order_id' => $validated['po_number'],
                    'client_id'         => $inquiry->client_id,
                    'jo_number'         => $joNumber,
                    'control_number'    => $controlNumber,
                    'color'             => $item['color'],
                    'quantity'          => $item['kilos'],
                    'yarn_type'         => $product->name,
                    'design'            => $item['description'] ?? '',
                    'unit_price'        => $item['unit_price'],
                    'subtotal'          => $subtotal,
                    'tax_rate'          => 12,
                    'tax_amount'        => $tax,
                    'total_amount'      => $total,
                    'status'            => 'pending',
                    'recipe_id'         => $item['recipe_id'] ?? null,
                ]);

                $createdOrders[] = $salesOrder;
            }

            // System message
            $itemCount = count($validated['items']);
            ConversationMessage::create([
                'inquiry_id'      => $inquiry->id,
                'sender_type'     => 'eco',
                'message'         => "📦 Job Order {$joNumber} created from PO {$validated['po_number']} with {$itemCount} item(s).",
                'is_system_event' => true,
            ]);

            return back()->with('success', "Job Order {$joNumber} created and sent to Push Center.");
        } catch (\Exception $e) {
            Log::error('Job Order creation failed: ' . $e->getMessage(), [
                'attachment_id' => $attachment->id,
                'validated'     => $validated,
            ]);
            return back()->withErrors(['error' => 'Failed to create Job Order: ' . $e->getMessage()]);
        }
    }
}
