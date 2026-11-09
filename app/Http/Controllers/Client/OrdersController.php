<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\Ord\PurchaseOrder;

class OrdersController extends Controller
{
    /**
     * Display the partner's orders.
     * Route Name: client.orders
     */
    public function orders()
    {
        $client = Auth::guard('client')->user();
        $ordersQuery = PurchaseOrder::where('client_id', $client->id);

        $stats = [
            'totalOrders' => (clone $ordersQuery)->count(),
            'pendingApproval' => (clone $ordersQuery)->where('status', 'pending_client_approval')->count(),
            'completed' => (clone $ordersQuery)->where('status', 'approved')->count(),
        ];

        $orders = (clone $ordersQuery)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Client/Orders', [
            'client' => $client,
            'orders' => $orders,
            'stats' => $stats,
        ]);
    }

    public function show($id)
    {
        $order = PurchaseOrder::with(['items.product', 'items.recipe'])
            ->where('client_id', Auth::guard('client')->id())
            ->findOrFail($id);

        return Inertia::render('Client/OrderShow', [
            'order' => $order,
        ]);
    }

    /**
     * Handle purchase order acceptance.
     * Route Name: client.orders.accept
     */
    public function acceptPurchaseOrder(Request $request, $id)
    {
        $order = \App\Models\Ord\PurchaseOrder::where('client_id', Auth::guard('client')->id())
            ->findOrFail($id);

        if ($order->status !== 'pending_client_approval') {
            return back()->withErrors(['error' => 'This order can no longer be accepted.']);
        }

        $order->update(['status' => 'approved']);

        return back()->with('message', "Order {$order->po_number} accepted successfully.");
    }
}
