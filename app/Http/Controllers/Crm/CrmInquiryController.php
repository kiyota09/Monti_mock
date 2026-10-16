<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Eco\Inquiry;
use App\Models\Crm\FabricSampleRequest;
use App\Models\Eco\ConversationMessage;
use App\Models\Eco\ConversationAttachment;
use App\Models\Eco\EcoQuotation;
use App\Models\Eco\EcoQuotationItem;
use App\Models\Man\BomRecord;
use App\Models\Ord\SalesOrder;
use App\Models\Inv\Material;
use App\Support\ConversationRealtime;
use App\Support\InquiryHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $inquiries = Inquiry::with(['client', 'product'])->latest()->get();
        return Inertia::render('Dashboard/CRM/Inquiry', ['inquiries' => $inquiries]);
    }

    /**
     * Display the specific inquiry and conversation page.
     * Passes `parsedProducts`, `recipes`, and `materials` for the UI.
     * The route key is hashed (see App\Support\InquiryHash).
     */
    public function show($key)
    {
        $inquiry = Inquiry::with(['client', 'product', 'messages' => function ($query) {
            $query->with(['attachments', 'sampleRequest'])->oldest();
        }, 'sampleRequests.product'])->findOrFail(InquiryHash::decodeOrFail((string) $key));

        $quotations = EcoQuotation::with('items')
            ->where('inquiry_id', $inquiry->id)
            ->latest()
            ->get();

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
        $materials = Material::select('id', 'mat_id', 'name', 'unit')->get();

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

        return Inertia::render('Dashboard/CRM/InquiryShow', [
            'inquiry'        => $inquiry,
            'quotations'     => $quotations,
            // Latest client-accepted quotation: the job-order modal prices
            // each color tier (White / Light / Dark) from these unit prices.
            'approvedQuotation' => $quotations->firstWhere('status', 'accepted'),
            // Every accepted quotation: a conversation may hold more than one
            // (e.g. revised quotes), so the job-order modal lets CRM pick
            // exactly which one prices the order. Latest first.
            'acceptedQuotations' => $quotations->where('status', 'accepted')->values(),
            'parsedProducts' => $this->extractProductsFromInquiry($inquiry),
            'allProducts'    => \App\Models\Inv\Product::select('id', 'name', 'sku')->get(),
            'recipes'        => $recipes,
            'sampleRecipes'  => $sampleRecipes,
            'materials'      => $materials,
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
     * Issue a quotation with White / Light Colors / Dark Colors pricing per product.
     */
    public function issueQuotation(Request $request, Inquiry $inquiry)
    {
        $validated = $request->validate([
            'items'               => 'required|array|min:1',
            'items.*.fabric'      => 'required|string|max:255',
            'items.*.design'      => 'nullable|string|max:255',
            'items.*.kilos'       => 'required|numeric|min:0',
            'items.*.price_white' => 'required|numeric|min:0',
            'items.*.price_light' => 'required|numeric|min:0',
            'items.*.price_dark'  => 'required|numeric|min:0',
            'vat_type'            => 'required|in:inclusive,exclusive',
            'payment_terms'       => 'required|string|max:255',
            'notes'               => 'nullable|string',
        ]);

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

                foreach ($colorTiers as $tier) {
                    $unitPrice  = (float) $item[$tier['key']];
                    $itemTotal  = round($kilos * $unitPrice * $vatMultiplier, 2);
                    $grandTotal += $itemTotal;

                    $quotation->items()->create([
                        'fabric'     => $item['fabric'],
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

            $productCount = count($validated['items']);
            $productList  = collect($validated['items'])
                ->map(fn($i) => "• {$i['fabric']}")
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
            // Meetings can only be set for today or a future date —
            // backdated meetings make no sense and break the timeline.
            'scheduled_at' => 'required|date|after_or_equal:today',
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
                $total = $item['kilos'] * $item['unit_price'];

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