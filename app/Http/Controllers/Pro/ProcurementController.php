<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Mail\SupplierInvoicePaid;
use App\Models\Inv\Warehouse;
use App\Models\Pro\Supplier;
use App\Models\Pro\SupplierProduct;
use App\Models\Scm\MaterialRequest;
use App\Models\Scm\ProcurementPayment;
use App\Models\Scm\PurchaseInvoice;
use App\Models\Scm\RequestForQuotation;
use App\Models\Scm\RfqResponse;
use App\Models\Scm\ScmPurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class ProcurementController extends Controller
{
    /**
     * Display pending material requests forwarded from SCM.
     */
    public function materialRequests()
    {
        $warehouses = Warehouse::orderBy('name')->get()->map(fn ($w) => [
            'id' => $w->id,
            'name' => $w->name,
            'location' => $w->location,
        ]);

        $suppliers = Supplier::whereHas('vendorRegistration', fn ($q) => $q->where('status', 'approved'))
            ->with('vendorRegistration.requirements')
            ->orderBy('business_name')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'business_name' => $s->business_name,
                'representative_name' => $s->representative_name ?? '',
                'email' => $s->email,
                'phone_number' => $s->phone_number,
                'address' => $s->address ?? '',
                'rating' => $s->rating ?? 5.0,
                'status' => 'Official Vendor',
                'categories' => $s->categories ? json_decode($s->categories, true) : ['Fabric', 'Trim', 'Chemicals', 'Packaging'],
                // Monti inventory material IDs this supplier carries
                // (available products) — RFQ modal filters on these.
                'product_material_ids' => self::productMaterialIds($s),
            ]);

        $requests = MaterialRequest::with('material')
            ->where('status', 'forwarded')
            ->orderByRaw("FIELD(urgency, 'High', 'Medium', 'Low')")
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'req_number' => $r->req_number,
                'material_id' => $r->material_id,
                'material_name' => $r->material?->name ?? $r->material_name,
                'category' => $r->material?->category ?? $r->category,
                'unit' => $r->material?->unit ?? $r->unit,
                'required_qty' => $r->required_qty,
                'urgency' => $r->urgency,
                'requested_by' => $r->requested_by,
                'requested_at' => $r->requested_at,
                'notes' => $r->notes,
                'status' => $r->status,
            ]);

        return Inertia::render('Dashboard/PRO/mat_req', [
            'materialRequests' => $requests,
            'warehouses' => $warehouses,
            'suppliers' => $suppliers,
            'stats' => ['total' => $requests->count()],
        ]);
    }

    /**
     * IDs of Monti inventory materials a supplier carries (available
     * products only) — lets the RFQ modal show exactly the suppliers
     * stocking the requested material.
     */
    public static function productMaterialIds(Supplier $supplier): array
    {
        return $supplier->products()
            ->where('is_available', true)
            ->pluck('material_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Store a new RFQ and notify selected suppliers.
     */
    public function createRFQ(Request $request)
    {
        $validated = $request->validate([
            'mr_id' => 'required|exists:material_requests,id',
            'deadline' => 'required|date|after_or_equal:today',
            'payment_terms' => 'required|string',
            'notes' => 'nullable|string',
            'selected_suppliers' => 'required|array|min:1',
            'selected_suppliers.*' => 'exists:suppliers,id',
        ]);

        DB::beginTransaction();
        try {
            $mr = MaterialRequest::findOrFail($validated['mr_id']);

            // Security: every selected supplier must carry the requested
            // material in its available product catalog. The UI filters to
            // matches, but this guards direct POSTs too.
            $carryingIds = SupplierProduct::whereIn('supplier_id', $validated['selected_suppliers'])
                ->where('material_id', $mr->material_id)
                ->where('is_available', true)
                ->pluck('supplier_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $offenders = Supplier::whereIn('id', $validated['selected_suppliers'])
                ->get(['id', 'business_name'])
                ->reject(fn ($s) => in_array((int) $s->id, $carryingIds, true));
            if ($offenders->isNotEmpty()) {
                DB::rollBack();

                return redirect()->back()->withErrors([
                    'error' => 'RFQ blocked: ' . $offenders->pluck('business_name')->implode(', ')
                        . ' do(es) not carry ' . ($mr->material?->name ?? $mr->material_name)
                        . ' in their product catalog.',
                ]);
            }

            RequestForQuotation::create([
                'rfq_number' => $this->generateRFQNumber(),
                'mr_ref' => $mr->req_number,
                'mr_id' => $mr->id,
                'material_id' => $mr->material_id,
                'material_name' => $mr->material_name,
                'category' => $mr->category,
                'unit' => $mr->unit,
                'required_qty' => $mr->required_qty,
                'deadline' => $validated['deadline'],
                'payment_terms' => $validated['payment_terms'],
                'notes' => $validated['notes'],
                'sent_at' => now(),
                'status' => 'sent',
                'supplier_ids' => json_encode($validated['selected_suppliers']),
            ]);

            // Update MR status so it disappears from the pending queue
            $mr->update(['status' => 'rfq_sent']);

            DB::commit();

            return redirect()->back()->with('success', 'RFQ successfully dispatched to suppliers.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('RFQ creation failed: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'Critical Error: Could not generate RFQ.']);
        }
    }

    /**
     * Display all sent RFQs and their incoming quotations.
     */
    public function supplierQuotations()
    {
        $rfqs = RequestForQuotation::with(['responses.supplier'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($rfq) => [
                'id' => $rfq->id,
                'rfq_number' => $rfq->rfq_number,
                'mr_ref' => $rfq->mr_ref,
                'material_name' => $rfq->material_name,
                'category' => $rfq->category,
                'unit' => $rfq->unit,
                'required_qty' => $rfq->required_qty,
                'deadline' => $rfq->deadline,
                'status' => $rfq->status,
                'notes' => $rfq->notes,
                'responses' => $rfq->responses->map(fn ($res) => [
                    'id' => $res->id,
                    'supplier_name' => $res->supplier?->business_name ?? $res->supplier_name,
                    'unit_price' => $res->unit_price,
                    'total_price' => $res->total_price,
                    'lead_time' => $res->lead_time,
                    'payment_terms' => $res->payment_terms,
                    'status' => $res->status,
                ]),
            ]);

        return Inertia::render('Dashboard/PRO/supp_quo', ['rfqs' => $rfqs]);
    }

    /**
     * Accept a quote, generate a Draft PO with 12% VAT, and decline competitors.
     */
    public function acceptQuotation(Request $request, $responseId)
    {
        $validated = $request->validate(['rfq_id' => 'required|exists:request_for_quotations,id']);

        DB::beginTransaction();
        try {
            $response = RfqResponse::with('rfq')->findOrFail($responseId);
            $rfq = $response->rfq;
            $supplier = Supplier::findOrFail($response->supplier_id);

            // 1. Accept this specific response
            $response->update(['status' => 'accepted']);

            // 2. Automatically decline other competing quotes for this RFQ
            RfqResponse::where('rfq_id', $rfq->id)
                ->where('id', '!=', $responseId)
                ->where('status', 'pending_review')
                ->update(['status' => 'declined']);

            // 3. Financial Calculations (VAT 12%)
            $subtotal = $response->total_price;
            $taxRate = 12; // Updated to 12% VAT
            $taxAmount = $subtotal * ($taxRate / 100);
            $grandTotal = $subtotal + $taxAmount;

            // 4. Create the Purchase Order (finance-pending: FIN must approve
            // before PRO can send it — see FinDashboardController).
            $po = ScmPurchaseOrder::create([
                'po_number' => $this->generatePONumber(),
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->business_name,
                'rfq_ref' => $rfq->rfq_number,
                'rfq_id' => $rfq->id,
                'issued_date' => now(),
                'expected_delivery' => $response->lead_time,
                'status' => 'draft',
                'finance_status' => 'pending',
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
                'notes' => 'Quotation accepted via Procurement Manager.',
                'received' => false,
            ]);

            // 5. Add line item to PO
            $po->items()->create([
                'material_id' => $rfq->material_id,
                'material_name' => $rfq->material_name,
                'qty' => $rfq->required_qty,
                'unit' => $rfq->unit,
                'unit_price' => $response->unit_price,
                'total' => $response->total_price,
            ]);

            // 6. Close out the RFQ
            $rfq->update(['status' => 'responded']);

            DB::commit();

            return redirect()->back()->with('success', 'Quotation accepted. PO generated — awaiting finance approval.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Accept quotation failed: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'Failed to process acceptance.']);
        }
    }

    /**
     * Manually decline a quote with a reason.
     */
    public function declineQuotation(Request $request, $responseId)
    {
        $validated = $request->validate(['reason' => 'nullable|string|max:500']);
        try {
            $response = RfqResponse::findOrFail($responseId);
            $response->update([
                'status' => 'declined',
                'decline_reason' => $validated['reason'],
            ]);

            return redirect()->back()->with('success', 'Quotation declined.');
        } catch (\Exception $e) {
            Log::error('Decline quotation failed: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'Failed to decline quotation.']);
        }
    }

    /**
     * View Purchase Orders and Invoices.
     */
    public function receipt()
    {
        $purchaseOrders = ScmPurchaseOrder::with('items')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($po) => [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'supplier_name' => $po->supplier_name,
                'status' => $po->status,
                'finance_status' => $po->finance_status ?? 'pending',
                'finance_remarks' => $po->finance_remarks,
                'has_rfq' => $po->rfq_id !== null,
                'grand_total' => $po->grand_total,
                'items' => $po->items->map(fn ($item) => [
                    'material_name' => $item->material_name,
                    'qty' => $item->qty,
                    'total' => $item->total,
                ]),
            ]);

        $invoices = PurchaseInvoice::orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'po_number' => $inv->po_number,
                'supplier_name' => $inv->supplier_name,
                'invoice_date' => $inv->invoice_date,
                'due_date' => $inv->due_date,
                'amount' => $inv->amount,
                'status' => $inv->status,
                'amount_paid' => ProcurementPayment::where('invoice_id', $inv->id)
                    ->where('status', 'cleared')
                    ->sum('amount'),
            ]);

        return Inertia::render('Dashboard/PRO/receipt', [
            'purchaseOrders' => $purchaseOrders,
            'invoices' => $invoices,
        ]);
    }

    /**
     * Send PO to supplier (Change status from Draft to Sent).
     * Only finance-approved draft POs may be sent.
     */
    public function sendPurchaseOrder($poId)
    {
        try {
            $po = ScmPurchaseOrder::findOrFail($poId);
            if ($po->status !== 'draft') {
                return redirect()->back()->withErrors(['error' => 'Only draft POs can be sent.']);
            }
            if (($po->finance_status ?? 'pending') !== 'approved') {
                return redirect()->back()->withErrors(['error' => 'Finance approval is required before sending this PO.']);
            }
            $po->update(['status' => 'sent']);

            return redirect()->back()->with('success', 'Purchase Order sent to supplier.');
        } catch (\Exception $e) {
            Log::error('Send PO failed: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'Failed to send PO.']);
        }
    }

    /**
     * Return a finance-declined PO's material request to the queue so PRO
     * can run a fresh RFQ round. The declined PO stays as history.
     */
    public function returnToRequests($poId)
    {
        try {
            $po = ScmPurchaseOrder::with('rfq')->findOrFail($poId);
            if (($po->finance_status ?? 'pending') !== 'declined') {
                return redirect()->back()->withErrors(['error' => 'Only finance-declined POs can be returned to Material Requests.']);
            }
            $mr = $po->rfq?->mr_id ? MaterialRequest::find($po->rfq->mr_id) : null;
            if (! $mr) {
                return redirect()->back()->withErrors(['error' => 'No linked material request found for this PO.']);
            }

            $mr->update(['status' => 'forwarded']);

            return redirect()->back()->with('success', "Material request {$mr->req_number} is back in Material Requests for a fresh RFQ round.");
        } catch (\Exception $e) {
            Log::error('Return to requests failed: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'Failed to return to Material Requests.']);
        }
    }

    /**
     * Order tracking: every MontiTextile purchase order to suppliers with
     * timeline state, items, invoices and payments for the tracking modal.
     */
    public function tracking()
    {
        $orders = ScmPurchaseOrder::with(['items', 'invoices'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($po) {
                $invoices = $po->invoices->map(fn ($inv) => [
                    'invoice_number' => $inv->invoice_number,
                    'amount' => $inv->amount,
                    'status' => $inv->status,
                    'paid' => ProcurementPayment::where('invoice_id', $inv->id)
                        ->where('status', 'cleared')
                        ->sum('amount'),
                ])->values();

                return [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'supplier_name' => $po->supplier_name,
                    'status' => $po->status,
                    'finance_status' => $po->finance_status ?? 'pending',
                    'finance_remarks' => $po->finance_remarks,
                    'finance_decided_at' => $po->finance_decided_at,
                    'grand_total' => $po->grand_total,
                    'issued_date' => $po->issued_date,
                    'expected_delivery' => $po->expected_delivery,
                    'created_at' => $po->created_at?->format('Y-m-d H:i'),
                    'updated_at' => $po->updated_at?->format('Y-m-d H:i'),
                    'items' => $po->items->map(fn ($item) => [
                        'material_name' => $item->material_name,
                        'qty' => $item->qty,
                        'unit' => $item->unit,
                        'unit_price' => $item->unit_price,
                        'total' => $item->total,
                    ])->values(),
                    'invoices' => $invoices,
                ];
            });

        return Inertia::render('Dashboard/PRO/tracking', [
            'purchaseOrders' => $orders,
        ]);
    }

    /**
     * Pay a supplier invoice, record the payment, and notify the supplier by email.
     */
    public function payInvoice(Request $request, $invoiceId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string|in:bank_transfer,check,cash,gcash,other',
            'paid_date' => 'required|date',
            'bank_reference' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $invoice = PurchaseInvoice::findOrFail($invoiceId);

            if (in_array($invoice->status, ['paid', 'cancelled'])) {
                return redirect()->back()->withErrors(['error' => 'This invoice is already '.$invoice->status.'.']);
            }

            $payment = ProcurementPayment::create([
                'payment_number' => $this->generatePaymentNumber(),
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'supplier_name' => $invoice->supplier_name,
                'paid_date' => $validated['paid_date'],
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'bank_reference' => $validated['bank_reference'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'status' => 'cleared',
            ]);

            // Recalculate invoice status from all cleared payments so far
            $totalPaid = ProcurementPayment::where('invoice_id', $invoice->id)
                ->where('status', 'cleared')
                ->sum('amount');

            $invoice->update([
                'status' => $totalPaid >= $invoice->amount ? 'paid' : 'partial',
            ]);

            // Notify the supplier by email that their invoice has been paid
            if ($invoice->supplier_id) {
                $supplier = Supplier::find($invoice->supplier_id);
                if ($supplier && $supplier->email) {
                    Mail::to($supplier->email)->send(new SupplierInvoicePaid($invoice, $payment));
                }
            }

            DB::commit();

            return redirect()->back()->with('success', "Payment recorded. {$invoice->supplier_name} has been notified by email.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Invoice payment failed: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'Failed to process payment.']);
        }
    }

    /**
     * ID Generators
     */
    private function generateRFQNumber(): string
    {
        $year = now()->format('Y');
        $count = RequestForQuotation::whereYear('created_at', $year)->count() + 1;

        return 'RFQ-'.$year.'-'.str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    private function generatePONumber(): string
    {
        $year = now()->format('Y');
        $count = ScmPurchaseOrder::whereYear('created_at', $year)->count() + 1;

        return 'SCMPO-'.$year.'-'.str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    private function generatePaymentNumber(): string
    {
        $year = now()->format('Y');
        $count = ProcurementPayment::whereYear('created_at', $year)->count() + 1;

        return 'PAY-'.$year.'-'.str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
