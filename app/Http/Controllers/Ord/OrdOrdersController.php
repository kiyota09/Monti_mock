<?php

namespace App\Http\Controllers\Ord;

use App\Http\Controllers\Controller;
use App\Models\Crm\Client;
use App\Models\Inv\Product;
use App\Models\Ord\OrderStatusHistory;
use App\Models\Ord\PurchaseOrder;
use App\Models\Ord\SalesOrder;
use App\Services\Ord\OrderLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

class OrdOrdersController extends Controller
{
    public function __construct(private OrderLifecycleService $lifecycle) {}

    /**
     * Unified order worklist: client POs + job-order SOs with pipeline
     * grouping, client/search/status filters, global group counts and
     * server-side pagination.
     */
    public function index(Request $request)
    {
        $filters = [
            'group' => (string) $request->get('group', 'all'),
            'status' => (string) $request->get('status', ''),
            'client_id' => $request->get('client_id'),
            'search' => trim((string) $request->get('search', '')),
            'type' => strtoupper((string) $request->get('type', '')),
            'priority' => (string) $request->get('priority', ''),
            'payment_status' => (string) $request->get('payment_status', ''),
            'from' => (string) $request->get('from', ''),
            'to' => (string) $request->get('to', ''),
            'sort' => (string) $request->get('sort', 'created_at'),
            'dir' => strtolower((string) $request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];

        if (! in_array($filters['type'], ['PO', 'SO'], true)) {
            $filters['type'] = '';
        }
        if (! in_array($filters['group'], array_keys($this->groupOptions()), true)) {
            $filters['group'] = 'all';
        }
        if (! in_array($filters['sort'], ['created_at', 'expected_ship_date', 'total', 'number', 'status'], true)) {
            $filters['sort'] = 'created_at';
        }

        $rows = collect();

        if ($filters['type'] !== 'SO') {
            $rows = $rows->merge(
                $this->poQuery($filters)->get()->map(fn ($po) => $this->poRow($po))
            );
        }
        if ($filters['type'] !== 'PO') {
            $rows = $rows->merge(
                $this->soQuery($filters)->get()->map(fn ($so) => $this->soRow($so))
            );
        }

        $rows = $this->sortRows($rows, $filters['sort'], $filters['dir']);

        // Counts are computed before the group filter so every tab shows the
        // true workload for the current search/client/status scope.
        $groupCounts = ['all' => $rows->count()];
        foreach ($rows as $row) {
            $groupCounts[$row['group']] = ($groupCounts[$row['group']] ?? 0) + 1;
        }

        $summary = [
            'count' => $rows->count(),
            'total' => (float) $rows->sum('total'),
            'open' => $rows->whereNotIn('status', OrderLifecycleService::TERMINAL)->count(),
            'attention' => $rows->where('group', 'attention')->count(),
        ];

        if ($filters['group'] !== 'all') {
            $rows = $rows->where('group', $filters['group'])->values();
        }

        $perPage = 25;
        $page = max(1, (int) $request->get('page', 1));
        $orders = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => route('ord.orders'), 'query' => $request->query()],
        );

        return Inertia::render('Dashboard/ORD/Orders', [
            'orders' => $orders,
            'groupCounts' => $groupCounts,
            'summary' => $summary,
            'filters' => $filters,
            'groups' => $this->groupOptions(),
            'soStatuses' => array_keys(OrderLifecycleService::SO_TRANSITIONS),
            'poStatuses' => array_keys(OrderLifecycleService::PO_TRANSITIONS),
            'clients' => Client::orderBy('company_name')->get(['id', 'company_name']),
            'products' => Product::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * 360° order detail: header, lines, pipeline, production, payments, history.
     */
    public function show(Request $request, string $type, int $id)
    {
        $type = strtoupper($type);
        abort_unless(in_array($type, ['PO', 'SO'], true), 404);

        $canPostPayment = $this->userCanPostPayments($request->user());

        if ($type === 'PO') {
            $order = PurchaseOrder::with([
                'client', 'items.product', 'statusHistory.changedBy', 'queue', 'salesOrders',
            ])->findOrFail($id);

            return Inertia::render('Dashboard/ORD/Show', [
                'orderType' => 'PO',
                'order' => [
                    ...$this->poRow($order),
                    'notes' => $order->notes,
                    'queue' => $order->queue,
                    'can_generate_so' => $order->status === 'approved',
                ],
                'lines' => $order->items->map(fn ($i) => [
                    'id' => $i->id,
                    'purchase_order_item_id' => $i->id,
                    'product' => $i->product?->name ?? 'N/A',
                    'quantity' => (float) $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                    'line_total' => (float) $i->line_total,
                ]),
                'transitions' => OrderLifecycleService::allowedPoTransitions($order->status),
                'history' => $this->historyRows($order),
                'linkedOrders' => $order->salesOrders->map(fn ($so) => $this->soRow($so)),
                'canPostPayment' => $canPostPayment,
            ]);
        }

        $order = SalesOrder::with([
            'client', 'recipe', 'bomRecord.product', 'bomRecord.client',
            'manufacturingOrder', 'statusHistory.changedBy', 'returns.resolvedBy',
        ])->findOrFail($id);

        return Inertia::render('Dashboard/ORD/Show', [
            'orderType' => 'SO',
            'order' => [...$this->soRow($order),
                'yarn_type' => $order->yarn_type,
                'color' => $order->color,
                'design' => $order->design,
                'recipe' => $order->recipe ? [
                    'id' => $order->recipe->id,
                    'yarn_type' => $order->recipe->yarn_type,
                    'dye_color' => $order->recipe->dye_color,
                    'weave_design' => $order->recipe->weave_design,
                ] : null,
                'manufacturing' => $order->manufacturingOrder ? [
                    'id' => $order->manufacturingOrder->id,
                    'status' => $order->manufacturingOrder->status,
                    'total' => $order->manufacturingOrder->total_quantity,
                    'remaining' => $order->manufacturingOrder->remaining_quantity,
                ] : null,
            ],
            'lines' => [[
                'id' => $order->id,
                'product' => $order->bomRecord?->product?->name ?? $order->design ?? 'Custom fabric',
                'quantity' => (float) $order->quantity,
                'unit_price' => (float) $order->unit_price,
                'line_total' => (float) $order->total_amount,
            ]],
            'transitions' => OrderLifecycleService::allowedSoTransitions($order->status),
            'history' => $this->historyRows($order),
            'returns' => $order->returns->map(fn ($r) => [
                'return_number' => $r->return_number,
                'type' => $r->type,
                'quantity' => (float) $r->quantity,
                'status' => $r->status,
                'reason' => $r->reason,
                'resolution_notes' => $r->resolution_notes,
                'resolved_by' => $r->resolvedBy?->name,
                'resolved_at' => $r->resolved_at?->format('M d, Y H:i'),
            ]),
            'linkedOrders' => [],
            'canPostPayment' => $canPostPayment,
        ]);
    }

    /**
     * Manual client PO intake (walk-in / email / phone orders).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|decimal:0,2|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'delivery_date' => 'nullable|date|after_or_equal:today',
            'expected_ship_date' => 'nullable|date|after_or_equal:today',
            'priority' => 'nullable|in:normal,rush,urgent',
            'notes' => 'nullable|string|max:2000',
        ]);

        $subtotal = collect($data['items'])->sum(fn ($i) => $i['quantity'] * $i['unit_price']);
        $discount = (float) ($data['discount_amount'] ?? 0);
        $taxable = round(max(0, $subtotal - $discount), 2);
        $tax = round($taxable * 0.12, 2);

        if ($discount > $subtotal) {
            return back()->withErrors(['discount_amount' => 'Discount cannot exceed the order subtotal.']);
        }

        $po = DB::transaction(function () use ($data, $subtotal, $discount, $taxable, $tax) {
            $po = PurchaseOrder::create([
                'client_id' => $data['client_id'],
                'po_number' => $this->lifecycle->nextPoNumber(),
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_rate' => 12,
                'tax_amount' => $tax,
                'total_amount' => $taxable + $tax,
                'status' => 'credit_review',
                'delivery_date' => $data['delivery_date'] ?? null,
                'expected_ship_date' => $data['expected_ship_date'] ?? $data['delivery_date'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'notes' => $data['notes'] ?? null,
                'payment_status' => 'unpaid',
            ]);

            foreach ($data['items'] as $item) {
                $po->items()->create($item);
            }

            $this->lifecycle->recordHistory($po, 'PO', null, 'credit_review', auth()->id(), 'Manual PO intake by '.auth()->user()?->name);

            return $po;
        });

        return redirect()->route('ord.orders.show', ['type' => 'PO', 'id' => $po->id])
            ->with('success', "Purchase order {$po->po_number} created and queued for credit review.");
    }

    /**
     * Generate job-order SO lines from an approved PO (one SO per PO item).
     */
    public function createSalesOrders(Request $request, int $id)
    {
        $data = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.purchase_order_item_id' => [
                'required',
                Rule::exists('purchase_order_items', 'id')->where('purchase_order_id', $id),
            ],
            'lines.*.yarn_type' => 'required|string|max:255',
            'lines.*.color' => 'required|string|max:255',
            'lines.*.design' => 'nullable|string|max:255',
            'lines.*.quantity' => 'required|numeric|decimal:0,2|min:0.01',
            'lines.*.expected_ship_date' => 'nullable|date',
        ]);

        $actor = $request->user();
        $result = DB::transaction(function () use ($id, $data, $actor) {
            // Serialize conversion requests against the PO row. The status
            // check must happen under this lock or two requests can both
            // observe "approved" and create duplicate job orders.
            $po = PurchaseOrder::with('items.product')->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($po->status !== 'approved') {
                throw ValidationException::withMessages(['error' => 'Sales orders can only be generated once, from an approved purchase order.']);
            }

            $items = $po->items->keyBy('id');
            $allocated = collect($data['lines'])->groupBy('purchase_order_item_id');

            foreach ($allocated as $itemId => $itemLines) {
                if (! $items->has((int) $itemId)) {
                    throw ValidationException::withMessages(['lines' => "A selected line does not belong to {$po->po_number}."]);
                }
                $item = $items->get((int) $itemId);
                $quantity = round($itemLines->sum(fn ($line) => (float) $line['quantity']), 2);
                if ($quantity > (float) $item->quantity + 0.001) {
                    throw ValidationException::withMessages(['lines' => "Allocated quantity for {$item->product?->name} exceeds the PO quantity of {$item->quantity} kg."]);
                }
            }

            foreach ($items as $item) {
                $quantity = round($allocated->get((string) $item->id, collect())->sum(fn ($line) => (float) $line['quantity']), 2);
                if (abs($quantity - (float) $item->quantity) > 0.001) {
                    throw ValidationException::withMessages(['lines' => "Job orders must allocate the full PO quantity of {$item->product?->name}: {$item->quantity} kg required, {$quantity} kg allocated."]);
                }
            }

            $grossTotal = round(collect($data['lines'])->sum(function ($line) use ($items) {
                $item = $items->get((int) $line['purchase_order_item_id']);
                return (float) $line['quantity'] * (float) $item->unit_price;
            }), 2);
            $targetSubtotal = round(max(0, (float) $po->subtotal - (float) $po->discount_amount), 2);
            $targetTax = round((float) $po->tax_amount, 2);
            $targetTotal = round((float) $po->total_amount, 2);
            if ($grossTotal <= 0 && $targetTotal > 0) {
                throw ValidationException::withMessages(['lines' => 'The approved PO has a payable total but no positive line value.']);
            }
            $discountFactor = $grossTotal > 0 ? $targetSubtotal / $grossTotal : 0;
            $count = 0;
            $allocatedSubtotal = 0.0;
            $allocatedTax = 0.0;
            $lastLine = array_key_last($data['lines']);
            foreach ($data['lines'] as $index => $line) {
                $item = $items->get((int) $line['purchase_order_item_id']);
                $grossLine = (float) $line['quantity'] * (float) $item->unit_price;
                $lineSubtotal = $index === $lastLine
                    ? round($targetSubtotal - $allocatedSubtotal, 2)
                    : round($grossLine * $discountFactor, 2);
                $lineTax = $index === $lastLine
                    ? round($targetTax - $allocatedTax, 2)
                    : round($lineSubtotal * 0.12, 2);
                $lineTotal = round($lineSubtotal + $lineTax, 2);
                $allocatedSubtotal = round($allocatedSubtotal + $lineSubtotal, 2);
                $allocatedTax = round($allocatedTax + $lineTax, 2);
                $joNumber = $this->lifecycle->nextJoNumber();
                $so = SalesOrder::create([
                    'purchase_order_id' => $po->po_number,
                    'client_id' => $po->client_id,
                    'jo_number' => $joNumber,
                    'control_number' => $this->lifecycle->nextControlNumber($joNumber),
                    'yarn_type' => $line['yarn_type'],
                    'color' => $line['color'],
                    'design' => $line['design'] ?? '',
                    'quantity' => $line['quantity'],
                    // The client approved the PO price. Keep its line price
                    // and allocate any PO-level discount across generated JOs.
                    'unit_price' => $item->unit_price,
                    'subtotal' => $lineSubtotal,
                    'tax_rate' => 12,
                    'tax_amount' => $lineTax,
                    'total_amount' => $lineTotal,
                    'expected_ship_date' => $line['expected_ship_date'] ?? $po->expected_ship_date,
                    'priority' => $po->priority ?? 'normal',
                    'status' => 'confirmed',
                    'payment_status' => 'unpaid',
                    'confirmed_at' => now(),
                    'confirmed_by' => $actor?->id,
                    'created_by' => $actor?->id,
                ]);
                $this->lifecycle->recordHistory($so, 'SO', 'pending', 'confirmed', $actor?->id, "Generated from {$po->po_number}");
                $count++;
            }

            $this->lifecycle->transitionPurchaseOrder(
                $po,
                'released_to_production',
                $actor,
                "Released with {$count} job order(s)"
            );

            return ['count' => $count, 'po_number' => $po->po_number];
        }, 3);

        return back()->with('success', "{$result['count']} job order(s) generated from {$result['po_number']}.");
    }

    /**
     * Generic guarded transition for PO/SO (confirm, plan, hold, resume, cancel…).
     */
    public function transition(Request $request, string $type, int $id)
    {
        $data = $request->validate([
            'to' => 'required|string|max:50',
            'notes' => 'nullable|string|max:255',
        ]);

        if (in_array($data['to'], ['on_hold', 'cancelled', 'returned'], true) && blank($data['notes'] ?? null)) {
            return back()->withErrors(['notes' => 'A reason is required for hold, cancellation and returns.']);
        }

        try {
            if (strtoupper($type) === 'PO') {
                $order = PurchaseOrder::findOrFail($id);
                $this->lifecycle->transitionPurchaseOrder($order, $data['to'], $request->user(), $data['notes'] ?? null);
                $label = $order->po_number;
            } else {
                $order = SalesOrder::findOrFail($id);
                $this->lifecycle->transitionSalesOrder($order, $data['to'], $request->user(), $data['notes'] ?? null);
                $label = $order->jo_number;
            }
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', "{$label} moved to ".str_replace('_', ' ', $data['to']).'.');
    }

    /**
     * Payment posting with receipt evidence. ORD managers + executives only.
     */
    public function updatePayment(Request $request)
    {
        $this->ensureCanPostPayments($request->user());

        $request->validate([
            'order_id' => 'required|integer',
            'type' => 'required|in:PO,SO',
            'payment_status' => 'required|in:unpaid,partially_paid,paid',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        $model = $request->type === 'PO' ? PurchaseOrder::class : SalesOrder::class;
        $order = $model::findOrFail($request->order_id);

        DB::transaction(function () use ($request, $order) {
            $oldReceipt = $order->receipt_file;
            $oldReceiptDisk = $oldReceipt && Storage::disk('local')->exists($oldReceipt) ? 'local' : 'public';
            $newReceipt = null;

            if ($request->hasFile('receipt')) {
                // Payment evidence is private. Downloading still goes
                // through this controller's page-permission checked route.
                $newReceipt = $request->file('receipt')->store('receipts', 'local');
                $order->receipt_file = $newReceipt;
            }

            $order->payment_status = $request->payment_status;
            $order->save();

            $this->lifecycle->recordHistory(
                $order, $request->type, $order->status, $order->status,
                $request->user()->id, 'Payment status set to '.str_replace('_', ' ', $request->payment_status)
            );

            // If history creation or a later write rolls back, keep the old
            // evidence file. Remove it only after the transaction commits.
            if ($newReceipt && $oldReceipt && $oldReceipt !== $newReceipt) {
                DB::afterCommit(function () use ($oldReceipt, $oldReceiptDisk) {
                    if (Storage::disk($oldReceiptDisk)->exists($oldReceipt)) {
                        Storage::disk($oldReceiptDisk)->delete($oldReceipt);
                    }
                });
            }
        });

        return redirect()->back()->with('success', 'Payment status updated successfully.');
    }

    public function downloadReceipt(Request $request, $type, $id)
    {
        $type = strtoupper((string) $type);
        abort_unless(in_array($type, ['PO', 'SO'], true), 404);
        $model = $type === 'PO' ? PurchaseOrder::class : SalesOrder::class;
        $order = $model::findOrFail($id);

        if (! $order->receipt_file) {
            abort(404, 'Receipt not found.');
        }

        $disk = Storage::disk('local')->exists($order->receipt_file) ? 'local' : 'public';
        if (! Storage::disk($disk)->exists($order->receipt_file)) {
            abort(404, 'Receipt not found.');
        }

        return Storage::disk($disk)->download($order->receipt_file);
    }

    private function poQuery(array $filters)
    {
        return PurchaseOrder::with('client')
            ->when($filters['client_id'], fn ($q, $v) => $q->where('client_id', $v))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['priority'] !== '', fn ($q) => $q->where('priority', $filters['priority']))
            ->when($filters['payment_status'] !== '', fn ($q) => $q->where('payment_status', $filters['payment_status']))
            ->when($filters['from'] !== '', fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(function ($inner) use ($search) {
                    $inner->where('po_number', 'like', "%{$search}%")
                        ->orWhere('control_number', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($c) => $c->where('company_name', 'like', "%{$search}%"));
                });
            });
    }

    private function soQuery(array $filters)
    {
        $aliases = collect(OrderLifecycleService::SO_ALIASES)
            ->filter(fn ($canonical) => $canonical === $filters['status'])
            ->keys()
            ->all();

        return SalesOrder::with(['client', 'bomRecord.product', 'bomRecord.client'])
            ->when($filters['client_id'], fn ($q, $v) => $q->where('client_id', $v))
            ->when($filters['status'] !== '', function ($q) use ($filters, $aliases) {
                $q->whereIn('status', array_values(array_unique([$filters['status'], ...$aliases])));
            })
            ->when($filters['priority'] !== '', fn ($q) => $q->where('priority', $filters['priority']))
            ->when($filters['payment_status'] !== '', fn ($q) => $q->where('payment_status', $filters['payment_status']))
            ->when($filters['from'] !== '', fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(function ($inner) use ($search) {
                    $inner->where('jo_number', 'like', "%{$search}%")
                        ->orWhere('control_number', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($c) => $c->where('company_name', 'like', "%{$search}%"));
                });
            });
    }

    private function sortRows($rows, string $sort, string $dir)
    {
        return $rows->sortBy(function ($row) use ($sort) {
            return match ($sort) {
                'total' => (float) ($row['total'] ?? 0),
                'expected_ship_date' => $row['expected_ship_date'] ?? '',
                'number' => $row['number'] ?? '',
                'status' => $row['status'] ?? '',
                default => $row['created_at'] ?? '',
            };
        }, SORT_REGULAR, $dir === 'desc')->values();
    }

    private function userCanPostPayments($user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->role === 'ORD' && in_array($user->position, ['manager', 'secretary', 'special_officer'], true)) {
            return true;
        }

        return in_array($user->position, ['secretary', 'special_officer'], true)
            && $user->moduleAccess?->pluck('module')->contains('ORD');
    }

    private function ensureCanPostPayments($user): void
    {
        abort_unless($this->userCanPostPayments($user), 403, 'Only the ORD manager can post payments.');
    }

    private function groupOptions(): array
    {
        return [
            'all' => 'All orders',
            'intake' => 'Intake',
            'confirmed' => 'Confirmed',
            'production' => 'In production',
            'ready' => 'Ready to ship',
            'transit' => 'In transit',
            'done' => 'Delivered / completed',
            'attention' => 'Needs attention',
            'cancelled' => 'Cancelled',
        ];
    }

    private function poRow($po): array
    {
        return [
            'id' => $po->id,
            'type' => 'PO',
            'number' => $po->po_number,
            'reference' => $po->control_number,
            'client_id' => $po->client_id,
            'client_name' => $po->client?->company_name ?? 'N/A',
            'total' => (float) $po->total_amount,
            'status' => $po->status,
            'group' => OrderLifecycleService::poGroup($po->status),
            'transitions' => OrderLifecycleService::allowedPoTransitions($po->status),
            'payment_status' => $po->payment_status ?? 'unpaid',
            'receipt_file' => $po->receipt_file,
            'priority' => $po->priority ?? 'normal',
            'expected_ship_date' => $po->expected_ship_date?->format('Y-m-d'),
            'date' => $po->created_at?->format('Y-m-d'),
            'created_at' => $po->created_at?->format('Y-m-d H:i'),
        ];
    }

    private function soRow($so): array
    {
        $clientName = $so->client?->company_name
            ?? $so->bomRecord?->client?->company_name
            ?? 'N/A';

        return [
            'id' => $so->id,
            'type' => 'SO',
            'number' => $so->jo_number,
            'reference' => $so->control_number,
            'client_id' => $so->client_id,
            'client_name' => $clientName,
            'product_name' => $so->bomRecord?->product?->name ?? $so->design ?? 'Custom fabric',
            'total' => (float) $so->total_amount,
            'status' => $so->status,
            'group' => OrderLifecycleService::soGroup($so->status),
            'transitions' => OrderLifecycleService::allowedSoTransitions($so->status),
            'payment_status' => $so->payment_status ?? 'unpaid',
            'receipt_file' => $so->receipt_file,
            'priority' => $so->priority ?? 'normal',
            'quantity' => (float) $so->quantity,
            'expected_ship_date' => $so->expected_ship_date?->format('Y-m-d'),
            'date' => $so->created_at?->format('Y-m-d'),
            'created_at' => $so->created_at?->format('Y-m-d H:i'),
        ];
    }

    private function historyRows($order): array
    {
        return $order->statusHistory->map(fn ($h) => [
            'from' => $h->from_status,
            'to' => $h->to_status,
            'by' => $h->changedBy?->name ?? 'System',
            'notes' => $h->notes,
            'at' => $h->created_at?->format('M d, Y H:i'),
        ])->toArray();
    }
}
