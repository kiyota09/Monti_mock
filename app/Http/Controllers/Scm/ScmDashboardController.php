<?php

namespace App\Http\Controllers\Scm;

use App\Http\Controllers\Controller;
use App\Models\Ord\SalesOrder;
use App\Models\Scm\MaterialRequest;
use App\Models\Scm\PurchaseInvoice;
use App\Models\Scm\ScmPurchaseOrder;
use App\Models\Pro\VendorRegistration;
use App\Models\War\WarehouseReceiving;
use Inertia\Inertia;

class ScmDashboardController extends Controller
{
    /**
     * SCM Command Center — pipeline snapshot across the whole
     * plan → source → deliver chain. Read-only aggregates only;
     * every action lives on its own page.
     */
    public function index()
    {
        $openSalesOrders = SalesOrder::whereIn('status', ['pushed_to_scm', 'inv_check', 'inv_checked', 'in_production'])
            ->with('client')
            ->orderBy('created_at', 'asc')
            ->take(8)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'jo_number' => $o->jo_number ?? 'JO-'.$o->id,
                'client_name' => $o->client->company_name ?? 'N/A',
                'quantity' => $o->quantity,
                'status' => $o->status,
                'created_at' => $o->created_at,
            ]);

        $pendingRequests = MaterialRequest::where('status', 'pending')->count();
        $activePOs = ScmPurchaseOrder::where('received', false)->count();
        $overdueInvoices = PurchaseInvoice::where('status', 'unpaid')
            ->where('due_date', '<', now()->toDateString())
            ->count();
        $pendingVendors = VendorRegistration::where('status', 'pending')->count();

        $recentReceivings = WarehouseReceiving::with('warehouse')
            ->orderBy('received_at', 'desc')
            ->take(6)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'receiving_number' => $r->receiving_number,
                'warehouse_name' => $r->warehouse->name ?? '—',
                'status' => $r->status,
                'received_at' => $r->received_at,
            ]);

        return Inertia::render('Dashboard/SCM/Dashboard', [
            'stats' => [
                'openSalesOrders' => $openSalesOrders->count(),
                'pendingRequests' => $pendingRequests,
                'activePOs' => $activePOs,
                'overdueInvoices' => $overdueInvoices,
                'pendingVendors' => $pendingVendors,
            ],
            'openSalesOrders' => $openSalesOrders,
            'recentReceivings' => $recentReceivings,
        ]);
    }
}
