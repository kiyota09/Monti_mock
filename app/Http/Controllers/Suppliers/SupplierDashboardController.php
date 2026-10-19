<?php

namespace App\Http\Controllers\Suppliers;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Fin\FinBill;
use App\Models\Inv\Material;
use App\Models\Pro\SupplierProduct;
use App\Models\Scm\ProcurementPayment;
use App\Models\Scm\PurchaseInvoice;
use App\Models\Scm\RequestForQuotation;
use App\Models\Scm\RfqResponse;
use App\Models\Scm\ScmPurchaseOrder;
use App\Support\PaymentMethodLabel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierDashboardController extends Controller
{
    public function index()
    {
        $supplier = auth('supplier')->user();
        $supplierId = $supplier->id;

        $allRfqs = RequestForQuotation::with(['responses' => function ($q) use ($supplierId) {
            $q->where('supplier_id', $supplierId);
        }])
            ->orderBy('created_at', 'desc')
            ->get();

        $rfqs = $allRfqs->filter(function ($rfq) use ($supplierId) {
            $ids = is_string($rfq->supplier_ids) ? json_decode($rfq->supplier_ids, true) : $rfq->supplier_ids;
            if (! is_array($ids)) return false;
            return in_array($supplierId, $ids) || in_array((string) $supplierId, $ids);
        })->values()->map(fn ($rfq) => [
            'id' => $rfq->id,
            'rfq_number' => $rfq->rfq_number,
            'material_id' => $rfq->material_id,
            'material_name' => $rfq->material_name,
            'category' => $rfq->category,
            'unit' => $rfq->unit,
            'required_qty' => (int) $rfq->required_qty,
            'deadline' => $rfq->deadline,
            'delivery_address' => $rfq->delivery_address ?? 'Main Warehouse',
            'payment_terms' => $rfq->payment_terms,
            'notes' => $rfq->notes,
            'status' => $rfq->status,
            'my_response' => $rfq->responses->first(),
        ]);

        return Inertia::render('Supplier/supplierDashboard', [
            'auth' => [
                'user' => $supplier,
                'supplier' => $supplier,
            ],
            'stats' => [
                'activeRFQs' => $rfqs->where('deadline', '>=', now()->toDateString())->count(),
                'pendingResponses' => $rfqs->whereNull('my_response')->count(),
                'submittedQuotes' => $rfqs->whereNotNull('my_response')->count(),
            ],
            'rfqs' => $rfqs,
            // Catalog prices keyed by material id — the quotation modal
            // auto-fills the unit price from My Products.
            'catalogPrices' => SupplierProduct::where('supplier_id', $supplierId)
                ->where('is_available', true)
                ->whereNotNull('unit_price')
                ->pluck('unit_price', 'material_id')
                ->map(fn ($p) => (float) $p)
                ->toArray(),
        ]);
    }

    public function submitQuotation(Request $request, $rfqId)
    {
        $supplier = auth('supplier')->user();

        $validated = $request->validate([
            'unit_price' => 'required|numeric|min:0.01',
            'lead_time' => 'required|string|max:255',
            'validity_date' => 'required|date|after_or_equal:today',
            'payment_terms' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $rfq = RequestForQuotation::findOrFail($rfqId);

        $totalPrice = $validated['unit_price'] * $rfq->required_qty;

        RfqResponse::create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->business_name,
            'unit_price' => $validated['unit_price'],
            'total_price' => $totalPrice,
            'lead_time' => $validated['lead_time'],
            'validity_date' => $validated['validity_date'],
            'payment_terms' => $validated['payment_terms'],
            'notes' => $validated['notes'],
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        $existingResponses = RfqResponse::where('rfq_id', $rfq->id)->count();
        $rfq->update(['status' => $existingResponses >= 1 ? 'responded' : 'partial_response']);

        return redirect()->back()->with('success', 'Quotation submitted successfully!');
    }

    /**
     * Product catalog: raw materials this supplier carries, grouped by
     * Monti category (Yarn / Dye / Supplies / Packaging). Names, units
     * and categories always come from the inventory materials table.
     */
    public function products()
    {
        $supplier = auth('supplier')->user();

        $products = SupplierProduct::with('material')
            ->where('supplier_id', $supplier->id)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'material_id' => $p->material_id,
                'name' => $p->material?->name ?? '—',
                'mat_id' => $p->material?->mat_id ?? '',
                'category' => $p->material?->category ?? 'Supplies',
                'unit' => $p->material?->unit ?? 'Kg',
                'unit_price' => $p->unit_price,
                'is_available' => (bool) $p->is_available,
            ]);

        $listedIds = $products->pluck('material_id')->all();
        $catalog = Material::whereNotIn('id', $listedIds ?: [0])
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'mat_id', 'name', 'unit', 'category']);

        return Inertia::render('Supplier/supplierProducts', [
            'auth' => [
                'user' => $supplier,
                'supplier' => $supplier,
            ],
            'products' => $products,
            'catalog' => $catalog,
        ]);
    }

    public function storeProduct(Request $request)
    {
        $supplier = auth('supplier')->user();

        $validated = $request->validate([
            'material_id' => 'required|exists:materials,id',
            'unit_price' => 'nullable|numeric|min:0',
        ]);

        $exists = SupplierProduct::where('supplier_id', $supplier->id)
            ->where('material_id', $validated['material_id'])
            ->exists();
        if ($exists) {
            return back()->withErrors(['material_id' => 'This material is already in your catalog.']);
        }

        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'material_id' => $validated['material_id'],
            'unit_price' => $validated['unit_price'] ?? null,
            'is_available' => true,
        ]);

        return back()->with('success', 'Product added to your catalog.');
    }

    public function toggleProduct(SupplierProduct $product)
    {
        abort_unless($product->supplier_id === auth('supplier')->id(), 403);

        $product->update(['is_available' => ! $product->is_available]);

        return back()->with('success', $product->is_available ? 'Product marked available.' : 'Product marked unavailable.');
    }

    public function destroyProduct(SupplierProduct $product)
    {
        abort_unless($product->supplier_id === auth('supplier')->id(), 403);

        $product->delete();

        return back()->with('success', 'Product removed from your catalog.');
    }

    public function purchaseOrders()
    {
        $supplier = auth('supplier')->user();

        $orders = ScmPurchaseOrder::where('supplier_id', $supplier->id)
            ->where('status', '!=', 'draft')
            ->with(['items', 'invoices.payments'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Supplier/supplierOrders', [
            'auth' => [
                'user' => $supplier,
                'supplier' => $supplier,
            ],
            'orders' => $orders,
        ]);
    }

    /**
     * Payments Monti Textiles has made to this supplier, grouped by the
     * supplier's purchase invoices. Mirrors the FIN Payables receipt view:
     * payments are pulled from BOTH ledgers (cleared procurement payments
     * plus finance bill payments on the linked bill) so the receipts always
     * sum to the paid total shown.
     */
    public function payments()
    {
        $supplier = auth('supplier')->user();

        $invoices = PurchaseInvoice::where('supplier_id', $supplier->id)
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();

        $invoiceIds = $invoices->pluck('id');

        $procPayments = $invoiceIds->isEmpty()
            ? collect()
            : ProcurementPayment::whereIn('invoice_id', $invoiceIds)
                ->where('status', 'cleared')
                ->orderByDesc('paid_date')->orderByDesc('id')
                ->get()->groupBy('invoice_id');

        $bills = $invoiceIds->isEmpty()
            ? collect()
            : FinBill::whereIn('purchase_invoice_id', $invoiceIds)
                ->with(['payments' => fn ($q) => $q->orderByDesc('paid_at')->orderByDesc('id')])
                ->get()->groupBy('purchase_invoice_id');

        $recorderIds = $bills
            ->flatMap(fn ($group) => $group->flatMap(fn ($bill) => $bill->payments->pluck('recorded_by')))
            ->filter()->unique()->values();
        $recorders = $recorderIds->isEmpty()
            ? collect()
            : User::whereIn('id', $recorderIds)->pluck('name', 'id');

        $rows = $invoices->map(function ($inv) use ($procPayments, $bills, $recorders) {
            $paid = 0.0;
            $receipts = collect();

            // 1) Cleared procurement payments against this supplier invoice.
            foreach ($procPayments->get($inv->id, collect()) as $p) {
                $paid += (float) $p->amount;
                $receipts->push([
                    'id' => 'PRO-' . $p->id,
                    'source' => 'Procurement · ' . $p->payment_number,
                    'receipt_no' => $p->payment_number,
                    'amount' => round((float) $p->amount, 2),
                    'paid_at' => $p->paid_date?->format('Y-m-d'),
                    'method' => PaymentMethodLabel::label($p->method),
                    'reference' => $p->bank_reference ?: '—',
                    'recorded_by' => 'Monti Textiles · Accounts Payable',
                    'created_at' => $p->created_at?->toIso8601String(),
                ]);
            }

            // 2) Finance bill payments recorded on the linked FIN bill.
            foreach ($bills->get($inv->id, collect()) as $bill) {
                foreach ($bill->payments as $p) {
                    $paid += (float) $p->amount;
                    $receipts->push([
                        'id' => 'FIN-' . $p->id,
                        'source' => 'Finance ledger',
                        'receipt_no' => 'RCPT-' . str_pad((string) $p->id, 5, '0', STR_PAD_LEFT),
                        'amount' => round((float) $p->amount, 2),
                        'paid_at' => $p->paid_at?->format('Y-m-d'),
                        'method' => PaymentMethodLabel::label($p->method),
                        'reference' => $p->reference ?: '—',
                        'recorded_by' => $recorders[$p->recorded_by] ?? 'Monti Textiles · Finance',
                        'created_at' => $p->created_at?->toIso8601String(),
                    ]);
                }
            }

            $paid = round($paid, 2);
            $balance = round((float) $inv->amount - $paid, 2);
            $status = $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
            if ($balance > 0 && $inv->due_date && $inv->due_date->isPast()) {
                $status = 'overdue';
            }

            $receipts = $receipts
                ->sortByDesc(fn ($r) => ($r['paid_at'] ?? '') . $r['id'])
                ->values();

            return [
                'id' => $inv->id,
                'bill_no' => $inv->invoice_number,
                'supplier' => $inv->supplier_name,
                'category' => $inv->po_number ? 'PO ' . $inv->po_number : 'Purchase invoice',
                'amount' => round((float) $inv->amount, 2),
                'paid' => $paid,
                'due_date' => $inv->due_date?->format('Y-m-d'),
                'status' => $status,
                'notes' => $inv->payment_terms,
                'created_at' => $inv->invoice_date?->format('Y-m-d'),
                'receipts' => $receipts,
                'receipt_count' => $receipts->count(),
            ];
        })->values();

        return Inertia::render('Supplier/supplierPayments', [
            'auth' => [
                'user' => $supplier,
                'supplier' => $supplier,
            ],
            'invoices' => $rows,
            'stats' => [
                'invoiced' => round($rows->sum('amount'), 2),
                'received' => round($rows->sum('paid'), 2),
                'outstanding' => round($rows->sum('amount') - $rows->sum('paid'), 2),
                'paidCount' => $rows->where('status', 'paid')->count(),
                'openCount' => $rows->whereIn('status', ['unpaid', 'partial', 'overdue'])->count(),
            ],
        ]);
    }

    /**
     * Supplier can only update to 'production' or 'shipping'
     */
    public function updateOrderStatus(Request $request, $id)
    {
    //    dd($request->all());
        $validated = $request->validate([
            'status' => 'required|in:production,shipping',
        ]);

        $po = ScmPurchaseOrder::where('supplier_id', auth('supplier')->id())->findOrFail($id);
        $po->update(['status' => $validated['status']]);

        return redirect()->back()->with('success', 'Order status updated to '.$validated['status']);
    }

    public function createInvoice(Request $request, $id)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|unique:purchase_invoices,invoice_number',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $po = ScmPurchaseOrder::where('supplier_id', auth('supplier')->id())->findOrFail($id);

        PurchaseInvoice::create([
            'invoice_number' => $validated['invoice_number'],
            'po_id' => $po->id,
            'po_number' => $po->po_number,
            'supplier_id' => auth('supplier')->id(),
            'supplier_name' => auth('supplier')->user()->business_name,
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'],
            'amount' => $validated['amount'],
            'payment_terms' => 'Standard',
            'status' => 'unpaid',
            'received_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Invoice generated and sent to SCM Accounting.');
    }
}