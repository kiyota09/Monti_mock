<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Crm\FabricSampleRequest;
use App\Models\Core\Notification;
use App\Models\Man\BomRecord;
use App\Models\Eco\ConversationAttachment;
use App\Models\Eco\ConversationMessage;
use App\Models\Eco\Inquiry;
use App\Models\Eco\EcoQuotation;
use App\Models\Eco\EcoQuotationItem;
use App\Models\Ord\PurchaseOrder;
use App\Support\ConversationRealtime;
use App\Services\Ord\OrderLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ClientConversationController extends Controller
{
    public function index()
    {
        $inquiries = Inquiry::where('client_id', Auth::guard('client')->id())
            ->with('product')
            ->latest()
            ->get();

        return Inertia::render('Client/ConversationList', ['inquiries' => $inquiries]);
    }

    public function show(Inquiry $inquiry)
    {
        $this->authorizeClient($inquiry);

        // Lab→CRM handoffs stay hidden until the CRM team forwards them.
        $inquiry->load(['messages' => function ($query) {
            $query->with(['attachments', 'sampleRequest.rounds'])->where('visible_to_client', true)->oldest();
        }, 'product']);

        foreach ($inquiry->messages as $message) {
            $sample = $message->sampleRequest;
            if ($sample) {
                $sample->setAttribute('sample_fabric_design', $sample->fabricDesignForSample());
                $sample->setAttribute('sample_yarn_type', $sample->yarnTypeForSample());
                $sample->setAttribute('pantone_colors', collect($sample->pantone_colors)->map(fn ($color) => is_array($color) ? collect($color)->except(['formula', 'pantone_formula', 'recipe', 'recipe_id'])->all() : $color)->all());
                $sample->makeHidden(['formula', 'pantone_formula', 'recipe_id', 'formulations']);
                $sample->rounds->each(function ($round) {
                    $round->setAttribute('sample_fabric_design', $round->fabricDesignForSample());
                    $round->setAttribute('sample_yarn_type', $round->yarnTypeForSample());
                    $round->setAttribute('pantone_colors', collect($round->pantone_colors)->map(fn ($color) => is_array($color) ? collect($color)->except(['formula', 'pantone_formula', 'recipe', 'recipe_id'])->all() : $color)->all());
                    $round->makeHidden(['formula', 'pantone_formula', 'recipe_id', 'formulations']);
                });
            }
            if (! $sample || ! str_starts_with((string) $message->message, 'Fabric sample request submitted')) {
                continue;
            }
            $designs = collect([$sample]);
            if ($sample->request_method === 'image' && $sample->client_reference_path && ! $sample->parent_id) {
                $designs = FabricSampleRequest::query()
                    ->where('inquiry_id', $inquiry->id)
                    ->where('request_method', 'image')
                    ->where('client_reference_path', $sample->client_reference_path)
                    ->whereNull('parent_id')
                    ->orderBy('id')
                    ->get();
            }
            $sample->setAttribute('submitted_designs', $designs->map(fn ($design) => [
                'id' => $design->id,
                'code' => $design->code,
                'fabric_name' => $design->fabric_name,
                'request_method' => $design->request_method,
                'carrier_name' => $design->carrier_name,
                'color_description' => $design->color_description,
                'texture' => $design->texture,
                'gsm' => $design->gsm,
                'color_hex' => $design->color_hex,
                'color_image_path' => $design->color_image_path,
                'client_reference_path' => $design->client_reference_path,
            ])->values()->all());
        }

        $quotations = EcoQuotation::with('items.product')
            ->where('inquiry_id', $inquiry->id)
            ->latest()
            ->get();

        $fabricOptions = collect();
        if ($inquiry->product && $inquiry->product->name !== 'Bulk Inquiry') {
            $fabricOptions->push($inquiry->product->only(['id', 'name', 'sku']));
        } else {
            preg_match_all('/(?:•|â€¢)\s*(.*?)\s*\|\s*SKU:\s*([^|\r\n]+)/u', (string) $inquiry->initial_message, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $product = \App\Models\Inv\Product::query()
                    ->where('sku', trim($match[2]))->orWhere('name', trim($match[1]))
                    ->first(['id', 'name', 'sku']);
                if ($product) $fabricOptions->push($product->only(['id', 'name', 'sku']));
            }
        }

        $fabricOptions = $quotations
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

        $sampleRequests = FabricSampleRequest::query()->where('inquiry_id', $inquiry->id)->orderBy('id')->get()
            ->map(function (FabricSampleRequest $sample) {
                return [
                    'id' => $sample->id, 'code' => $sample->code, 'product_id' => $sample->product_id,
                    'fabric_name' => $sample->fabric_name, 'sample_fabric_design' => $sample->fabricDesignForSample(),
                    'sample_yarn_type' => $sample->yarnTypeForSample(), 'color_description' => $sample->color_description,
                    'pantone_colors' => collect($sample->pantone_colors)->map(fn ($color) => is_array($color) ? collect($color)->except(['formula', 'pantone_formula', 'recipe', 'recipe_id'])->all() : $color)->all(),
                    'status' => $sample->status, 'sample_image_path' => in_array($sample->status, ['forwarded', 'approved', 'adjustment_requested'], true) ? $sample->sample_image_path : null,
                    'delivery_courier' => $sample->delivery_courier, 'delivery_tracking_reference' => $sample->delivery_tracking_reference,
                    'delivery_notes' => $sample->delivery_notes, 'delivery_sent_at' => $sample->delivery_sent_at,
                    'client_noted_at' => $sample->client_noted_at, 'client_received_at' => $sample->client_received_at,
                    'adjustment_notes' => $sample->adjustment_notes,
                ];
            })->values();

        return Inertia::render('Client/ConversationShow', [
            'inquiry' => $inquiry,
            'quotations' => $quotations,
            'fabricOptions' => $fabricOptions,
            'sampleRequests' => $sampleRequests,
        ]);
    }

    public function requestFabricSample(Request $request, Inquiry $inquiry)
    {
        $this->authorizeClient($inquiry);
        $validated = $request->validate([
            'fabric_designs' => 'required|array|min:1|max:30',
            'fabric_designs.*.quotation_item_id' => 'required|integer|exists:eco_quotation_items,id',
            'fabric_designs.*.colors' => 'exclude_unless:request_method,pantone|required|array|min:1|max:30',
            'fabric_designs.*.colors.*.pantone_code' => 'exclude_unless:request_method,pantone|required|string|max:120',
            'fabric_designs.*.colors.*.pantone_color' => 'exclude_unless:request_method,pantone|required|string|max:120',
            'fabric_designs.*.colors.*.texture' => 'exclude_unless:request_method,pantone|required|in:Hard,Medium,Soft',
            'fabric_designs.*.colors.*.gsm' => 'exclude_unless:request_method,pantone|required|integer|min:1|max:10000',
            'fabric_designs.*.colors.*.reference_type' => 'exclude_unless:request_method,pantone|required|in:hex,image',
            'fabric_designs.*.colors.*.color_hex' => 'exclude_unless:request_method,pantone|nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'fabric_designs.*.colors.*.color_image' => 'exclude_unless:request_method,pantone|nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'request_method' => 'required|in:carrier,image,pantone',
            'carrier_name' => 'required_if:request_method,carrier|nullable|string|max:120',
            'reference_file' => 'required_if:request_method,image|nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:20480',
            'notes' => 'nullable|string|max:2000',
        ]);

        $referenceErrors = [];
        if ($validated['request_method'] === 'pantone') {
            foreach ($validated['fabric_designs'] as $fabricIndex => $design) {
                foreach ($design['colors'] ?? [] as $colorIndex => $color) {
                    $field = "fabric_designs.{$fabricIndex}.colors.{$colorIndex}";
                    if ($color['reference_type'] === 'hex' && !preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($color['color_hex'] ?? ''))) {
                        $referenceErrors["{$field}.color_hex"] = 'Choose a valid hex color.';
                    }
                    if ($color['reference_type'] === 'image' && !$request->hasFile("{$field}.color_image")) {
                        $referenceErrors["{$field}.color_image"] = 'Upload an image for this color.';
                    }
                }
            }
        }
        if ($referenceErrors) {
            return back()->withErrors($referenceErrors);
        }

        $allowed = $this->fabricOptionsFor($inquiry);
        $resolvedDesigns = [];
        foreach ($validated['fabric_designs'] as $index => $design) {
            $quotationItem = EcoQuotationItem::query()
                ->with('product')
                ->whereKey($design['quotation_item_id'])
                ->whereHas('quotation', fn ($query) => $query
                    ->where('inquiry_id', $inquiry->id)
                    ->where('client_id', Auth::guard('client')->id())
                    ->where('status', 'accepted'))
                ->first();

            abort_unless($quotationItem, 403);

            $product = $quotationItem->product ?? $allowed->first(function ($candidate) use ($quotationItem) {
                $fabric = mb_strtolower(trim((string) $quotationItem->fabric));
                return $fabric !== '' && (
                    $fabric === mb_strtolower(trim((string) $candidate->name))
                    || $fabric === mb_strtolower(trim((string) $candidate->sku))
                );
            });

            if (!$product) {
                return back()->withErrors(["fabric_designs.{$index}.quotation_item_id" => 'The selected quotation fabric is no longer available. Please contact the CRM team.']);
            }

            $fabricName = trim(implode(' — ', array_filter([
                $quotationItem->fabric,
                $quotationItem->design,
                $quotationItem->color,
            ])));

            $resolvedDesigns[] = [
                'form_index' => $index,
                'product' => $product,
                'fabric_name' => $fabricName ?: $product->name,
                'colors' => $design['colors'] ?? [],
            ];
        }

        $filePath = $request->file('reference_file')?->store('fabric_sample_references', 'public');
        $created = collect();
        foreach ($resolvedDesigns as $design) {
            $colors = $validated['request_method'] === 'pantone' ? $design['colors'] : [null];
            foreach ($colors as $colorIndex => $color) {
                $colorImagePath = $color && ($color['reference_type'] ?? null) === 'image'
                    ? $request->file("fabric_designs.{$design['form_index']}.colors.{$colorIndex}.color_image")?->store('fabric_sample_color_references', 'public')
                    : null;
                $created->push(FabricSampleRequest::create([
                    'code' => 'FSR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5)),
                    'inquiry_id' => $inquiry->id,
                    'client_id' => $inquiry->client_id,
                    'product_id' => $design['product']->id,
                    'fabric_name' => $design['fabric_name'],
                    'texture' => $color['texture'] ?? null,
                    'gsm' => $color['gsm'] ?? null,
                    'color_hex' => ($color['reference_type'] ?? null) === 'hex' ? ($color['color_hex'] ?? null) : null,
                    'color_image_path' => $colorImagePath,
                    'request_method' => $validated['request_method'],
                    'carrier_name' => $validated['carrier_name'] ?? null,
                    'client_reference_path' => $filePath,
                    'color_description' => $color ? ($color['pantone_color'] . ' (' . $color['pantone_code'] . ')') : ($validated['notes'] ?? 'Color reference to follow'),
                    'notes' => $validated['notes'] ?? null,
                    'status' => $validated['request_method'] === 'pantone' ? 'client_submitted' : 'awaiting_receipt',
                ]));
            }
        }

        $fabricNames = collect($resolvedDesigns)->pluck('fabric_name')->unique()->implode(', ');

        ConversationMessage::create([
            'inquiry_id' => $inquiry->id,
            'sender_type' => 'client',
            'message' => 'Fabric sample request submitted for ' . $fabricNames . ' (' . $created->count() . ' sample' . ($created->count() === 1 ? '' : 's') . '). Method: ' . $validated['request_method'] . '.',
            'is_system_event' => true,
            'sample_request_id' => $created->first()?->id,
        ]);
        $inquiry->update(['last_message_at' => now()]);

        return back()->with('success', 'Fabric sample request sent to the CRM team.');
    }

    private function fabricOptionsFor(Inquiry $inquiry)
    {
        if ($inquiry->product && $inquiry->product->name !== 'Bulk Inquiry') {
            return collect([$inquiry->product]);
        }
        preg_match_all('/(?:•|â€¢)\s*(.*?)\s*\|\s*SKU:\s*([^|\r\n]+)/u', (string) $inquiry->initial_message, $matches, PREG_SET_ORDER);
        return collect($matches)->map(function ($match) {
            return \App\Models\Inv\Product::query()->where('sku', trim($match[2]))->orWhere('name', trim($match[1]))->first();
        })->filter()->values();
    }

    public function sendMessage(Request $request, Inquiry $inquiry)
    {
        $this->authorizeClient($inquiry);

        $request->validate([
            'message' => 'required_without:files|nullable|string',
            'files.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,zip|max:10240',
        ]);

        $message = ConversationMessage::create([
            'inquiry_id' => $inquiry->id,
            'sender_type' => 'client',
            'message' => $request->message ?? '',
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('eco_attachments', 'public');
                ConversationAttachment::create([
                    'conversation_message_id' => $message->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getMimeType(),
                ]);
            }
        }

        $inquiry->update(['last_message_at' => now()]);

        // Client just sent something → no longer "typing".
        ConversationRealtime::clearTyping($inquiry->id, 'client');

        $message->load('attachments');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => $message], 201);
        }

        return back()->with('success', 'Message sent.');
    }

    /**
     * Real-time polling feed for the client side.
     * GET ?after=<lastMessageId> → only newer messages are returned.
     * Also reports whether the ECO team is currently typing.
     */
    public function feed(Request $request, Inquiry $inquiry)
    {
        $this->authorizeClient($inquiry);

        $after = (int) $request->query('after', 0);
        $messages = ConversationRealtime::feed($inquiry->id, $after, true);

        return response()->json([
            'messages' => $messages,
            'typing'   => ConversationRealtime::isTyping($inquiry->id, 'eco'),
        ]);
    }

    /**
     * Typing heartbeat for the client side. Called (debounced) while typing.
     */
    public function typing(Request $request, Inquiry $inquiry)
    {
        $this->authorizeClient($inquiry);

        ConversationRealtime::markTyping($inquiry->id, 'client');

        return response()->json(['ok' => true]);
    }

    /** Submit an order using the selected quotation's product/color/yarn lines. */
    public function sendPO(Request $request, Inquiry $inquiry)
    {
        $this->authorizeClient($inquiry);

        $data = $request->validate([
            'quotation_id' => ['required', 'integer', 'exists:eco_quotations,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quotation_item_id' => ['required', 'integer', 'distinct', 'exists:eco_quotation_items,id'],
            'items.*.kilos' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $poNumber = DB::transaction(function () use ($data, $inquiry) {
            $quotation = EcoQuotation::query()
                ->whereKey($data['quotation_id'])
                ->where('inquiry_id', $inquiry->id)
                ->where('client_id', Auth::guard('client')->id())
                ->whereIn('status', ['sent', 'accepted'])
                ->with('items.product')
                ->lockForUpdate()
                ->firstOrFail();

            $selections = $inquiry->product_selections ?? [];
            $selectionSets = collect($selections['products'] ?? []);
            if ($selectionSets->isEmpty() && ! empty($selections['pantones']) && ! empty($selections['yarns'])) {
                $selectionSets = collect([[...$selections, 'product_id' => $selections['product_id'] ?? $inquiry->product_id]]);
            }
            if ($selectionSets->isNotEmpty()) {
                $requiredPairs = $selectionSets->flatMap(fn ($set) => collect($set['pantones'] ?? [])->flatMap(fn ($pantone) => collect($set['yarns'] ?? [])->map(fn ($yarn) => [
                    (int) ($set['product_id'] ?? 0), (int) ($pantone['id'] ?? 0), (int) ($yarn['id'] ?? 0),
                ])));
                $approvedPairs = FabricSampleRequest::query()->where('inquiry_id', $inquiry->id)
                    ->where('status', FabricSampleRequest::STATUS_APPROVED)->get()->flatMap(fn ($sample) => collect($sample->pantone_colors ?? [])->map(fn ($color) => [
                        (int) $sample->product_id, (int) ($color['pantone_reference_id'] ?? 0), (int) ($color['yarn_material_id'] ?? 0),
                    ]));
                $missing = $requiredPairs->contains(fn ($pair) => ! $approvedPairs->contains(fn ($approved) => $approved === $pair));
                if ($missing) {
                    throw ValidationException::withMessages(['items' => 'Approve every requested Pantone color and yarn swatch before sending a purchase order.']);
                }
            }

            $quotationItems = $quotation->items->keyBy('id');
            $submittedIds = collect($data['items'])->pluck('quotation_item_id')->map(fn ($id) => (int) $id);
            $allowedIds = $quotationItems->keys()->map(fn ($id) => (int) $id);
            if ($submittedIds->count() !== $quotationItems->count()
                || $submittedIds->unique()->count() !== $quotationItems->count()
                || $submittedIds->diff($allowedIds)->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => 'Order quantities must correspond to every line in the selected quotation.']);
            }

            $orderedLines = [];
            $subtotal = 0.0;
            foreach ($data['items'] as $index => $row) {
                $quoteItem = $quotationItems->get((int) $row['quotation_item_id']);
                $kilos = round((float) $row['kilos'], 2);
                if ($kilos <= 0) continue;

                $productId = $quoteItem->product_id ?: $inquiry->product_id;
                $product = $quoteItem->product ?: ($productId ? \App\Models\Inv\Product::find($productId) : null);
                if (! $product) {
                    throw ValidationException::withMessages(['items' => 'A quoted line is missing its Inventory product. Ask CRM to correct the quotation.']);
                }
                $minimum = (float) ($product->minimum_order_quantity ?? 0);
                if ($minimum > 0 && $kilos < $minimum) {
                    throw ValidationException::withMessages([
                        "items.{$index}.kilos" => "Order at least {$minimum} kg for {$product->name}.",
                    ]);
                }

                $unitPrice = (float) $quoteItem->unit_price;
                $lineSubtotal = round($kilos * $unitPrice, 2);
                $subtotal += $lineSubtotal;
                $orderedLines[] = [
                    'quote_item' => $quoteItem,
                    'product_id' => $product->id,
                    'kilos' => $kilos,
                    'unit_price' => $unitPrice,
                    'line_subtotal' => $lineSubtotal,
                    'product_name' => $product->name,
                ];
            }

            if (! $orderedLines) {
                throw ValidationException::withMessages(['items' => 'Enter kilograms for at least one product and color line.']);
            }

            $taxRate = 12.0;
            $taxAmount = $quotation->vat_type === 'inclusive' ? round($subtotal * ($taxRate / 100), 2) : 0.0;
            $total = $subtotal + $taxAmount;
            $clientNotes = trim((string) ($data['notes'] ?? ''));
            $orderNotes = 'From quotation '.$quotation->quotation_number
                .($clientNotes !== '' ? "\nClient notes: {$clientNotes}" : '');

            $lifecycle = app(OrderLifecycleService::class);
            $order = PurchaseOrder::create([
                'client_id' => Auth::guard('client')->id(),
                'inquiry_id' => $inquiry->id,
                'eco_quotation_id' => $quotation->id,
                'po_number' => $lifecycle->nextPoNumber(),
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total_amount' => $total,
                'status' => 'credit_review',
                'priority' => 'normal',
                'notes' => $orderNotes,
                'payment_status' => 'unpaid',
            ]);

            foreach ($orderedLines as $line) {
                $item = $line['quote_item'];
                $order->items()->create([
                    'product_id' => $line['product_id'],
                    'eco_quotation_item_id' => $item->id,
                    'quantity' => $line['kilos'],
                    'unit_price' => $line['unit_price'],
                    'fabric' => $item->fabric,
                    'fabric_type' => $item->fabric_type,
                    'design' => $item->design,
                    'color' => $item->color,
                    'kilos' => $line['kilos'],
                    'price' => $line['line_subtotal'],
                ]);
            }

            $lifecycle->recordHistory($order, 'PO', null, 'credit_review', null, 'Client order from quotation '.$quotation->quotation_number);
            if ($quotation->status === 'sent') {
                $quotation->update(['status' => 'accepted']);
            }

            $summary = collect($orderedLines)->map(function ($line) {
                $item = $line['quote_item'];
                return "- {$line['product_name']} / ".($item->fabric_type ?: 'Yarn unspecified')." / {$item->color}: {$line['kilos']} kg";
            })->implode("\n");
            ConversationMessage::create([
                'inquiry_id' => $inquiry->id,
                'sender_type' => 'client',
                'message' => "Purchase order {$order->po_number} submitted from quotation {$quotation->quotation_number}.\n{$summary}\nTotal: PHP ".number_format($total, 2),
                'is_system_event' => true,
            ]);

            $inquiry->update(['last_message_at' => now()]);
            return $order->po_number;
        }, 3);

        return back()->with('success', "Purchase order {$poNumber} submitted for review.");
    }

    /**
     * Approve an attachment sent by ECO (or any attachment in the conversation).
     * This is called by the client when they approve a file.
     */
    public function approveAttachment(ConversationAttachment $attachment)
    {
        $inquiry = $attachment->message->inquiry;
        $this->authorizeClient($inquiry);

        $attachment->update(['approved_by_client' => true]);

        // Create a system message to notify ECO
        ConversationMessage::create([
            'inquiry_id' => $inquiry->id,
            'sender_type' => 'client',
            'message' => "✅ Client approved attachment: {$attachment->file_name}",
            'is_system_event' => true,
        ]);

        return back()->with('success', 'Attachment approved.');
    }

    /**
     * Approve a forwarded fabric sample. The client may now send a P.O.
     * through the existing purchase-order flow.
     */
    public function noteSampleDelivery(Request $request, FabricSampleRequest $sampleRequest)
    {
        $inquiry = $sampleRequest->inquiry;
        abort_unless($inquiry, 404);
        $this->authorizeClient($inquiry);
        abort_unless($sampleRequest->status === FabricSampleRequest::STATUS_FORWARDED, 422, 'This fabric sample is not awaiting a delivery acknowledgment.');

        if (! $sampleRequest->client_noted_at) {
            $sampleRequest->update(['client_noted_at' => now()]);
            ConversationMessage::create([
                'inquiry_id' => $inquiry->id,
                'sender_type' => 'client',
                'message' => "Client noted the delivery details for fabric sample {$sampleRequest->code}.",
                'is_system_event' => true,
                'visible_to_client' => false,
                'sample_request_id' => $sampleRequest->id,
            ]);
            $inquiry->update(['last_message_at' => now()]);
        }

        return back()->with('success', 'Delivery update noted. Confirm when the sample arrives.');
    }

    public function receiveDeliveredSample(Request $request, FabricSampleRequest $sampleRequest)
    {
        $inquiry = $sampleRequest->inquiry;
        abort_unless($inquiry, 404);
        $this->authorizeClient($inquiry);
        abort_unless($sampleRequest->status === FabricSampleRequest::STATUS_FORWARDED, 422, 'This fabric sample is not awaiting physical receipt.');
        abort_unless($sampleRequest->client_noted_at, 422, 'Please note the delivery update before confirming receipt.');

        if (! $sampleRequest->client_received_at) {
            $sampleRequest->update(['client_received_at' => now()]);
            ConversationMessage::create([
                'inquiry_id' => $inquiry->id,
                'sender_type' => 'client',
                'message' => "Client confirmed receipt of fabric sample {$sampleRequest->code}.",
                'is_system_event' => true,
                'visible_to_client' => false,
                'sample_request_id' => $sampleRequest->id,
            ]);
            $inquiry->update(['last_message_at' => now()]);
        }

        return back()->with('success', 'Sample receipt confirmed. You can now approve the swatch or request an adjustment.');
    }

    public function approveSample(Request $request, FabricSampleRequest $sampleRequest)
    {
        $inquiry = $sampleRequest->inquiry;
        abort_unless($inquiry, 404);
        $this->authorizeClient($inquiry);

        if ($sampleRequest->status !== FabricSampleRequest::STATUS_FORWARDED) {
            return back()->withErrors(['error' => 'This sample is no longer awaiting approval.']);
        }
        if (! $sampleRequest->client_received_at) {
            return back()->withErrors(['error' => 'Confirm physical receipt before deciding on the sample.']);
        }

        DB::transaction(function () use ($sampleRequest, $inquiry) {
            $sampleRequest->refresh();
            $formulation = $sampleRequest->formulations()->where('status', 'forwarded_to_client')->latest('version')->first();
            if (! $sampleRequest->recipe_id) {
                $formula = $sampleRequest->formula ?? [];
                $liquorRatio = (string) ($formula['liquor_ratio'] ?? '1:10');
                $liquor = preg_match('/(?:^|:)\s*(\d+(?:\.\d+)?)\s*$/', $liquorRatio, $match) ? (float) $match[1] : 10.0;
                $materials = [];
                foreach ([['dyestuffs', 'pct'], ['auxiliary_agents', 'gpl'], ['basic_chemicals', 'gpl'], ['post_treatment', 'gpl']] as [$group, $quantityKey]) {
                    foreach ($formula[$group] ?? [] as $row) {
                        $id = (int) ($row['material_id'] ?? 0);
                        if (! $id) continue;
                        $quantity = (float) ($row[$quantityKey] ?? $row['amount'] ?? 0);
                        $materials[$id] = ($materials[$id] ?? 0) + ($group === 'dyestuffs' ? $quantity / 100 : $quantity * $liquor / 1000);
                    }
                }
                if (! array_key_exists('auxiliary_agents', $formula)) {
                    foreach ($formula['auxiliaries'] ?? [] as $row) {
                        $id = (int) ($row['material_id'] ?? 0);
                        if ($id) $materials[$id] = ($materials[$id] ?? 0) + ((float) ($row['gpl'] ?? 0) * $liquor / 1000);
                    }
                }
                $yarns = $formula['yarns'] ?? [];
                foreach ($yarns as $row) {
                    $id = (int) ($row['material_id'] ?? 0);
                    if ($id) $materials[$id] = ($materials[$id] ?? 0) + (float) ($row['qty'] ?? 0);
                }
                $recipe = BomRecord::create([
                    'client_id' => $sampleRequest->client_id,
                    'product_id' => $sampleRequest->product_id,
                    'fabric_sample_request_id' => $sampleRequest->id,
                    'yarn_type' => collect($yarns)->pluck('name')->filter()->unique()->join(' + ')
                        ?: (string) (collect($sampleRequest->pantone_colors ?? [])->first()['yarn_type'] ?? $sampleRequest->yarnTypeForSample()),
                    'dye_color' => $sampleRequest->color_description,
                    'weave_design' => $formula['weave_design'] ?? $sampleRequest->fabric_name,
                    'materials' => $materials,
                ]);
                $sampleRequest->update(['recipe_id' => $recipe->id, 'status' => FabricSampleRequest::STATUS_APPROVED]);
            } else {
                $sampleRequest->update(['status' => FabricSampleRequest::STATUS_APPROVED]);
            }
            $formulation?->update(['status' => 'client_approved']);

            ConversationMessage::create([
                'inquiry_id' => $inquiry->id,
                'sender_type' => 'client',
                'message' => "✅ Fabric sample approved ({$sampleRequest->code}): {$sampleRequest->fabric_name}. Ready for P.O.",
                'is_system_event' => true,
                'sample_request_id' => $sampleRequest->id,
            ]);

            $inquiry->update(['last_message_at' => now()]);
        });

        return back()->with('success', 'Fabric sample approved. You may now send your P.O.');
    }

    /**
     * Request a color adjustment on a forwarded sample. The CRM team sees
     * this and loops back with a new sample request round.
     */
    public function adjustSample(Request $request, FabricSampleRequest $sampleRequest)
    {
        $inquiry = $sampleRequest->inquiry;
        abort_unless($inquiry, 404);
        $this->authorizeClient($inquiry);

        $validated = $request->validate([
            'notes' => 'required|string|max:2000',
        ]);

        if ($sampleRequest->status !== FabricSampleRequest::STATUS_FORWARDED) {
            return back()->withErrors(['error' => 'This sample is no longer awaiting approval.']);
        }
        if (! $sampleRequest->client_received_at) {
            return back()->withErrors(['error' => 'Confirm physical receipt before requesting an adjustment.']);
        }

        DB::transaction(function () use ($sampleRequest, $inquiry, $validated) {
            $formulation = $sampleRequest->formulations()->where('status', 'forwarded_to_client')->latest('version')->first();
            $sampleRequest->update([
                'status' => FabricSampleRequest::STATUS_ADJUSTMENT_REQUESTED,
                'adjustment_notes' => $validated['notes'],
            ]);
            $formulation?->update([
                'status' => 'client_adjustment_requested',
                'review_notes' => $validated['notes'],
            ]);

            ConversationMessage::create([
                'inquiry_id' => $inquiry->id,
                'sender_type' => 'client',
                'message' => "🎨 Color adjustment requested ({$sampleRequest->code}): {$sampleRequest->fabric_name}\n"
                    . "Notes: {$validated['notes']}",
                'is_system_event' => true,
                'sample_request_id' => $sampleRequest->id,
            ]);

            $chemistId = $sampleRequest->formulations()->latest('version')->value('submitted_by');
            if ($chemistId) {
                Notification::notify(
                    (int) $chemistId,
                    'manufacturing',
                    'Client requested a fabric adjustment',
                    "{$sampleRequest->code}: {$validated['notes']}",
                    'man.staff.dyeing-lab-chemist.page'
                );
            } else {
                \App\Models\Core\User::query()->where('manufacturing_role', 'dyeing_lab_chemist')->pluck('id')->each(fn ($userId) => Notification::notify(
                    (int) $userId,
                    'manufacturing',
                    'Client requested a fabric adjustment',
                    "{$sampleRequest->code}: {$validated['notes']}",
                    'man.staff.dyeing-lab-chemist.page'
                ));
            }

            $inquiry->update(['last_message_at' => now()]);
        });

        return back()->with('success', 'Adjustment requested. The Dyeing Lab Chemist has been notified to reformulate the swatch.');
    }

    /**
     * Accept a quotation (price agreement only, no order created yet).
     */
    public function acceptQuotation(Request $request, EcoQuotation $quotation)
    {
        // Only allow if the quotation belongs to this client and is still 'sent'
        if ($quotation->client_id !== Auth::guard('client')->id() || $quotation->status !== 'sent') {
            abort(403);
        }

        // Update quotation status
        $quotation->update(['status' => 'accepted']);

        // System message
        $messageText = "Quotation {$quotation->quotation_number} has been ACCEPTED by the client.";
        if ($request->notes) {
            $messageText .= "\n\nNotes from client: " . $request->notes;
        }

        $message = ConversationMessage::create([
            'inquiry_id' => $quotation->inquiry_id,
            'sender_type' => 'client',
            'message' => $messageText,
            'is_system_event' => true,
        ]);

        // Handle any attached files (e.g., proof of payment)
        if ($request->hasFile('files')) {
            $request->validate([
                'files.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,zip|max:10240',
            ]);
            foreach ($request->file('files') as $file) {
                $path = $file->store('eco_attachments', 'public');
                ConversationAttachment::create([
                    'conversation_message_id' => $message->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getMimeType(),
                ]);
            }
        }

        return back()->with('success', 'Quotation accepted successfully.');
    }

    /**
     * Trash an accepted quotation the client changed their mind on.
     * Flags it for a fresh quotation — CRM sees the trash tab entry plus a
     * thread message and issues a new quotation via the normal flow.
     */
    public function trashQuotation(Request $request, EcoQuotation $quotation)
    {
        if ($quotation->client_id !== Auth::guard('client')->id() || $quotation->status !== 'accepted') {
            abort(403);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($quotation, $validated) {
            $quotation->update([
                'status' => 'trashed',
                'request_new_quote' => true,
            ]);

            $msg = "Quotation {$quotation->quotation_number} moved to TRASH by the client. A new quotation was requested.";
            if (! empty($validated['notes'])) {
                $msg .= "\n\nClient notes: " . $validated['notes'];
            }

            ConversationMessage::create([
                'inquiry_id' => $quotation->inquiry_id,
                'sender_type' => 'client',
                'message' => $msg,
                'is_system_event' => true,
            ]);
        });

        return back()->with('success', 'Quotation trashed. The CRM team will issue a new one.');
    }

    public function rejectQuotation(Request $request, EcoQuotation $quotation)
    {
        if ($quotation->client_id !== Auth::guard('client')->id() || $quotation->status !== 'sent') {
            abort(403);
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
            'request_new' => 'boolean'
        ]);

        $quotation->update([
            'status' => 'rejected',
            'reject_reason' => $request->reason,
            'request_new_quote' => $request->request_new ?? false,
        ]);

        $msg = "Quotation {$quotation->quotation_number} REJECTED. Reason: {$request->reason}";
        if ($request->request_new) {
            $msg .= " | Client requested a revised quotation.";
        }

        ConversationMessage::create([
            'inquiry_id' => $quotation->inquiry_id,
            'sender_type' => 'client',
            'message' => $msg,
            'is_system_event' => true,
        ]);

        return back()->with('success', 'Quotation rejected.');
    }

    /**
     * Download an accepted quotation as a PDF.
     */
    public function downloadQuotation(EcoQuotation $quotation)
    {
        if ($quotation->client_id !== Auth::guard('client')->id()) {
            abort(403);
        }

        if ($quotation->status !== 'accepted') {
            abort(403, 'Only accepted quotations can be downloaded.');
        }

        $quotation->load(['items', 'inquiry.product']);

        return response()->json([
            'quotation' => $quotation,
            'generated_at' => now()->toDateTimeString(),
        ]);
    }

    public function destroyAttachment(ConversationAttachment $attachment)
    {
        if ($attachment->message->inquiry->client_id !== Auth::guard('client')->id()) {
            abort(403);
        }
        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'File removed.');
    }

    private function authorizeClient(Inquiry $inquiry)
    {
        if ($inquiry->client_id !== Auth::guard('client')->id()) {
            abort(403);
        }
    }
}
