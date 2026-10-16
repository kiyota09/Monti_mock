<?php

namespace App\Http\Controllers\Ord;

use App\Http\Controllers\Controller;
use App\Models\Crm\Client;
use App\Models\Inv\Product;
use App\Models\Ord\PurchaseOrder;
use App\Models\Ord\SalesOrder;
use App\Services\Ord\OrderLifecycleService;
use App\Support\NotifiesExecutive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use InvalidArgumentException;

class OrdOrdersController extends Controller
{
    public function __construct(private OrderLifecycleService $lifecycle) {}

    /**
     * Unified order worklist: client POs + job-order SOs with pipeline
     * grouping, client/search/status filters.
     */
    public function index(Request $request)
    {
        $group = $request->get('group', 'all');
        $status = $request->get('status');
        $clientId = $request->get('client_id');
        $search = trim((string) $request->get('search', ''));

        $poQuery = PurchaseOrder::with('client')->latest();
        $soQuery = SalesOrder::with(['client', 'bomRecord.product', 'bomRecord.client'])->latest();

        if ($clientId) {
            $poQuery->where('client_id', $clientId);
            $soQuery->where('client_id', $clientId);
        }
        if ($status) {
            $poQuery->where('status', $status);
            $soQuery->where('status', $status);
        }
        if ($search !== '') {
            $poQuery->where(function ($q) use ($search) {
                $q->whereRaw('po_number LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('control_number LIKE ?', ["%{$search}%"]);
            });
            $soQuery->where(function ($q) use ($search) {
                $q->whereRaw('jo_number LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('control_number LIKE ?', ["%{$search}%"]);
            });
        }

        $pos = $poQuery->limit(300)->get()->map(fn ($po) => $this->poRow($po));
        $sos = $soQuery->limit(300)->get()->map(fn ($so) => $this->soRow($so));

        $orders = $pos->concat($sos)->sortByDesc('created_at')->values();

        if ($group !== 'all') {
            $orders = $orders->filter(fn ($o) => $o['group'] === $group)->values();
        }

        return Inertia::render('Dashboard/ORD/Orders', [
            'orders' => $orders,
            'filters' => ['group' => $group, 'status' => $status, 'client_id' => $clientId, 'search' => $search],
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
    public function show(string $type, int $id)
    {
        $type = strtoupper($type);
        abort_unless(in_array($type, ['PO', 'SO'], true), 404);

        if ($type === 'PO') {
            $order = PurchaseOrder::with(['client', 'items.product', 'statusHistory.changedBy', 'queue'])->findOrFail($id);

            return Inertia::render('Dashboard/ORD/Show', [
                'orderType' => 'PO',
                'order' => [...$this->poRow($order), 'notes' => $order->notes, 'queue' => $order->queue],
                'lines' => $order->items->map(fn ($i) => [
                    'id' => $i->id,
                    'product' => $i->product?->name ?? 'N/A',
                    'quantity' => $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                    'line_total' => (float) $i->line_total,
                ]),
                'transitions' => OrderLifecycleService::allowedPoTransitions($order->status),
                'history' => $this->historyRows($order),
            ]);
        }

        $order = SalesOrder::with([
            'client', 'recipe', 'bomRecord.product', 'bomRecord.client',
            'manufacturingOrder', 'statusHistory.changedBy', 'returns',
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
                'return_number' => $r->return_number, 'type' => $r->type,
                'quantity' => (float) $r->quantity, 'status' => $r->status, 'reason' => $r->reason,
            ]),
        ]);
    }

    /**
     * Manual client PO intake (walk-in / email / phone orders).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'delivery_date' => 'nullable|date|after_or_equal:today',
            'expected_ship_date' => 'nullable|date|after_or_equal:today',
            'priority' => 'nullable|in:normal,rush,urgent',
            'notes' => 'nullable|string|max:2000',
        ]);

        $po = DB::transaction(function () use ($data) {
            $subtotal = collect($data['items'])->sum(fn ($i) => $i['quantity'] * $i['unit_price']);
            $discount = (float) ($data['discount_amount'] ?? 0);

            $po = PurchaseOrder::create([
                'client_id' => $data['client_id'],
                'po_number' => $this->lifecycle->nextPoNumber(),
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total_amount' => max(0, $subtotal - $discount),
                'status' => 'credit_review',
                'delivery_date' => $data['delivery_date'] ?? null,
                'expected_ship_date' => $data['expected_ship_date'] ?? $data['delivery_date'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $po->items()->create($item);
            }

            $this->lifecycle->recordHistory($po, 'PO', null, 'credit_review', auth()->id(), 'Manual PO intake by '.auth()->user()?->name);

            return $po;
        });

        NotifiesExecutive::push('credit', "PO {$po->po_number} pending credit review", "Order total {$po->total_amount} awaits executive credit decision.", 'ceo.approvals');

        return redirect()->route('ord.orders.show', ['type' => 'PO', 'id' => $po->id])
            ->with('success', "Purchase order {$po->po_number} created and queued for credit review.");
    }

    /**
     * Generate job-order SO lines from an approved PO (one SO per PO item).
     */
    public function createSalesOrders(Request $request, int $id)
    {
        $po = PurchaseOrder::with('items.product')->findOrFail($id);

        if (! in_array($po->status, ['approved', 'released_to_production'], true)) {
            return back()->withErrors(['error' => 'Sales orders can only be generated from an approved purchase order.']);
        }

        $data = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'lines.*.yarn_type' => 'required|string|max:255',
            'lines.*.color' => 'required|string|max:255',
            'lines.*.design' => 'nullable|string|max:255',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.expected_ship_date' => 'nullable|date',
        ]);

        $created = DB::transaction(function () use ($po, $data) {
            $count = 0;
            foreach ($data['lines'] as $line) {
                $joNumber = $this->lifecycle->nextJoNumber();
                $so = SalesOrder::create([
                    'purchase_order_id' => $po->po_number,
                    'client_id' => $po->client_id,
                    'jo_number' => $joNumber,
                    'control_number' => $this->lifecycle->nextControlNumber($joNumber),
                    'yarn_type' => $line['yarn_type'],
                    'color' => $line['color'],
                    'design' => $line['design'] ?? null,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'total_amount' => $line['quantity'] * $line['unit_price'],
                    'expected_ship_date' => $line['expected_ship_date'] ?? $po->expected_ship_date,
                    'priority' => $po->priority ?? 'normal',
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                    'confirmed_by' => auth()->id(),
                    'created_by' => auth()->id(),
                ]);
                $this->lifecycle->recordHistory($so, 'SO', 'pending', 'confirmed', auth()->id(), "Generated from {$po->po_number}");
                $count++;
            }

            if ($po->status === 'approved') {
                $from = $po->status;
                $po->update(['status' => 'released_to_production']);
                $this->lifecycle->recordHistory($po, 'PO', $from, 'released_to_production', auth()->id(), "Released with {$count} job order(s)");
            }

            return $count;
        });

        return back()->with('success', "{$created} job order(s) generated from {$po->po_number}.");
    }

    /**
     * Generic guarded transition for PO/SO (confirm, plan, hold, resume, cancel…).
     */
    public function transition(Request $request, string $type, int $id)
    {
        $data = $request->validate([
            'to' => 'required|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            if (strtoupper($type) === 'PO') {
                $this->lifecycle->transitionPurchaseOrder(PurchaseOrder::findOrFail($id), $data['to'], $request->user(), $data['notes'] ?? null);
            } else {
                $this->lifecycle->transitionSalesOrder(SalesOrder::findOrFail($id), $data['to'], $request->user(), $data['notes'] ?? null);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Order status updated.');
    }

    /**
     * Payment posting with receipt evidence. ORD managers + CEO only.
     * (Replaces the dead `$this->authorize('ceo-only')` gate, which was
     * never defined and rejected every request with 403.)
     */
    public function updatePayment(Request $request)
    {
        $this->ensureCanPostPayments($request->user());

        $request->validate([
            'order_id' => 'required|integer',
            'type' => 'required|in:PO,SO',
            'payment_status' => 'required|in:unpaid,partially_paid,paid',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $model = $request->type === 'PO' ? PurchaseOrder::class : SalesOrder::class;
        $order = $model::findOrFail($request->order_id);

        if ($request->hasFile('receipt')) {
            if ($order->receipt_file && Storage::disk('public')->exists($order->receipt_file)) {
                Storage::disk('public')->delete($order->receipt_file);
            }
            $order->receipt_file = $request->file('receipt')->store('receipts', 'public');
        }

        $order->payment_status = $request->payment_status;
        $order->save();

        $this->lifecycle->recordHistory(
            $order, $request->type, null, $order->status,
            $request->user()->id, 'Payment marked '.$request->payment_status
        );

        return redirect()->back()->with('success', 'Payment status updated successfully.');
    }

    public function downloadReceipt(Request $request, $type, $id)
    {
        $this->ensureCanPostPayments($request->user());

        $model = strtoupper($type) === 'PO' ? PurchaseOrder::class : SalesOrder::class;
        $order = $model::findOrFail($id);

        if (! $order->receipt_file || ! Storage::disk('public')->exists($order->receipt_file)) {
            abort(404, 'Receipt not found.');
        }

        return Storage::disk('public')->download($order->receipt_file);
    }

    private function ensureCanPostPayments($user): void
    {
        if ($user->role === 'ORD' && in_array($user->position, ['manager', 'secretary', 'special_officer'], true)) {
            return;
        }
        if (in_array($user->position, ['secretary', 'special_officer'], true)
            && $user->moduleAccess->pluck('module')->contains('ORD')) {
            return;
        }

        abort(403, 'Only the ORD manager can post payments.');
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
