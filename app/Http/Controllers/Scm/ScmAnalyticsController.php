<?php

namespace App\Http\Controllers\Scm;

use App\Http\Controllers\Controller;
use App\Models\Pro\Supplier;
use App\Models\Scm\MaterialRequest;
use App\Models\Scm\PurchaseInvoice;
use App\Models\Scm\RequestForQuotation;
use App\Models\Scm\RfqResponse;
use App\Models\Scm\ScmPurchaseOrder;
use Inertia\Inertia;

class ScmAnalyticsController extends Controller
{
    /**
     * SCM Analytics — supplier scorecard (spend, POs, quotes, overdue
     * invoices) plus the request → RFQ → PO → invoice funnel.
     * Read-only aggregates; no writes.
     */
    public function index()
    {
        // PO counts + spend per supplier (Supplier has no purchaseOrders
        // relation — approval status lives on VendorRegistration).
        $poStats = ScmPurchaseOrder::selectRaw('supplier_id, COUNT(*) as pos, COALESCE(SUM(grand_total),0) as spend')
            ->groupBy('supplier_id')
            ->get()
            ->keyBy('supplier_id');

        $suppliers = Supplier::with('vendorRegistration')
            ->get()
            ->map(function ($s) use ($poStats) {
                $stat = $poStats[$s->id] ?? null;
                $invoiceQuery = PurchaseInvoice::where('supplier_id', $s->id);

                return [
                    'id' => $s->id,
                    'business_name' => $s->business_name,
                    'status' => $s->vendorRegistration->status ?? '—',
                    'purchase_orders' => (int) ($stat->pos ?? 0),
                    'total_spend' => (float) ($stat->spend ?? 0),
                    'quotes_submitted' => RfqResponse::where('supplier_id', $s->id)->count(),
                    'overdue_invoices' => (clone $invoiceQuery)
                        ->where('status', 'unpaid')
                        ->where('due_date', '<', now()->toDateString())
                        ->count(),
                ];
            })
            ->sortByDesc('total_spend')
            ->values();

        return Inertia::render('Dashboard/SCM/Analytics', [
            'summary' => [
                'suppliers' => $suppliers->count(),
                'totalSpend' => $suppliers->sum('total_spend'),
                'purchaseOrders' => ScmPurchaseOrder::count(),
                'quotes' => RfqResponse::count(),
            ],
            'funnel' => [
                'requests' => MaterialRequest::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
                'rfqs' => RequestForQuotation::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
                'purchaseOrders' => ScmPurchaseOrder::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
                'invoices' => PurchaseInvoice::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
            ],
            'suppliers' => $suppliers,
        ]);
    }
}
